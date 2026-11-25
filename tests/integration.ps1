$ErrorActionPreference = 'Stop'
$root = 'C:\xampp\htdocs\online_voting_system'
$php = 'C:\xampp\php\php.exe'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$testDb = 'online_voting_test_' + [Guid]::NewGuid().ToString('N')
$createdDatabase = $false
$smtpProcess = $null
$phpProcess = $null
$testDirectory = Join-Path $env:TEMP ('online_voting_system_test_' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $testDirectory -Force | Out-Null
$schemaFile = Join-Path $testDirectory ($testDb + '-schema.sql')
$spoolFile = Join-Path $testDirectory ($testDb + '-smtp.txt')
$smtpOut = Join-Path $testDirectory ($testDb + '-smtp.out')
$smtpErr = Join-Path $testDirectory ($testDb + '-smtp.err')
$phpOut = Join-Path $testDirectory ($testDb + '-php.out')
$phpErr = Join-Path $testDirectory ($testDb + '-php.err')
$port = 18000 + (Get-Random -Minimum 0 -Maximum 10000)
$smtpPort = 28000 + (Get-Random -Minimum 0 -Maximum 10000)
$baseUrl = 'http://127.0.0.1:' + $port
$passes = 0

function Assert($condition, [string] $message) {
    if (-not $condition) { throw "FAIL: $message" }
    $script:passes++
    Write-Output "PASS: $message"
}

function Invoke-MySql([string] $query) {
    $result = & $script:mysql --host=127.0.0.1 --port=3306 --user=root --batch --skip-column-names ("--execute=" + $query) 2>&1
    if ($LASTEXITCODE -ne 0) { throw "MySQL test command failed: $($result -join ' ')" }
    return ($result -join "`n").Trim()
}

function Import-MySqlFile([string] $database, [string] $file) {
    $command = '"' + $script:mysql + '" --host=127.0.0.1 --port=3306 --user=root'
    if ($database) { $command += ' --database=' + $database }
    $command += ' < "' + $file + '"'
    $result = & $env:ComSpec /d /s /c $command 2>&1
    if ($LASTEXITCODE -ne 0) { throw "MySQL import failed: $($result -join ' ')" }
}

function Get-Page($webSession, [string] $path, [string] $HostAddress = '127.0.0.1') {
    return Invoke-WebRequest -Uri ("http://{0}:{1}/index.php?page={2}" -f $HostAddress, $script:port, $path) -WebSession $webSession -UseBasicParsing
}

function Post-Page($webSession, [string] $path, [hashtable] $fields, [string] $HostAddress = '127.0.0.1') {
    return Invoke-WebRequest -Uri ("http://{0}:{1}/index.php?page={2}" -f $HostAddress, $script:port, $path) -WebSession $webSession -Method Post -Body $fields -ContentType 'application/x-www-form-urlencoded' -UseBasicParsing
}

function Get-StatusWithHeaders([string] $path, [string] $hostHeader, [string] $forwardedAddress = '') {
    $request = [System.Net.HttpWebRequest]::Create("$script:baseUrl/index.php?page=$path")
    $request.Method = 'GET'
    $request.Host = $hostHeader
    if ($forwardedAddress -ne '') {
        $request.Headers.Add('X-Forwarded-For', $forwardedAddress)
    }
    try {
        $response = $request.GetResponse()
        $status = [int] $response.StatusCode
        $response.Close()
        return $status
    } catch [System.Net.WebException] {
        if ($_.Exception.Response) {
            $status = [int] $_.Exception.Response.StatusCode
            $_.Exception.Response.Close()
            return $status
        }
        throw
    }
}

function Get-Csrf($response) {
    $match = [regex]::Match($response.Content, 'name="_csrf"\s+value="([^"]+)"')
    if (-not $match.Success) { throw 'Could not find CSRF token in page.' }
    return $match.Groups[1].Value
}

function Get-LatestCode([int] $minimumCount) {
    for ($i = 0; $i -lt 50; $i++) {
        if (Test-Path $script:spoolFile) {
            $content = [System.IO.File]::ReadAllText($script:spoolFile)
            $matches = [regex]::Matches($content, 'six-digit verification code is ([0-9]{6})')
            if ($matches.Count -ge $minimumCount) {
                return $matches[$matches.Count - 1].Groups[1].Value
            }
        }
        Start-Sleep -Milliseconds 100
    }
    throw 'Timed out waiting for the local SMTP test server to receive a code.'
}

function Register-Applicant($webSession, [string] $email, [string] $nationalId) {
    $page = Get-Page $webSession 'register'
    $fields = @{
        _csrf = Get-Csrf $page
        action = 'register'
        full_name = 'Integration Applicant'
        national_id = $nationalId
        email = $email
        date_of_birth = '1992-03-04'
        gender = 'Prefer not to say'
        phone = '+12025550199'
        password = 'ApplicantPassword-2026!'
        confirm_password = 'ApplicantPassword-2026!'
    }
    return Post-Page $webSession 'register' $fields
}

function Submit-Otp($webSession, [string] $code) {
    $page = Get-Page $webSession 'verify-otp'
    return Post-Page $webSession 'verify-otp' @{
        _csrf = Get-Csrf $page
        action = 'verify_otp'
        otp = $code
    }
}

function Cleanup {
    if ($script:phpProcess) { Stop-Process -Id $script:phpProcess.Id -Force -ErrorAction SilentlyContinue }
    if ($script:smtpProcess) { Stop-Process -Id $script:smtpProcess.Id -Force -ErrorAction SilentlyContinue }
    if ($script:createdDatabase) {
        try { Invoke-MySql ("DROP DATABASE IF EXISTS " + $script:testDb) | Out-Null } catch { }
    }
    foreach ($path in @($script:schemaFile, $script:spoolFile, $script:smtpOut, $script:smtpErr, $script:phpOut, $script:phpErr)) {
        if (Test-Path -LiteralPath $path) {
            Remove-Item -LiteralPath $path -Force
        }
    }
    if ((Test-Path -LiteralPath $script:testDirectory) -and @(Get-ChildItem -LiteralPath $script:testDirectory -Force).Count -eq 0) {
        Remove-Item -LiteralPath $script:testDirectory -Force
    }
}

try {
    $encKey = & $php -r "echo base64_encode(random_bytes(32));"
    $hmacKey = & $php -r "echo base64_encode(random_bytes(32));"
    foreach ($name in @('APP_ENV','APP_NAME','APP_BASE_URL','APP_TIMEZONE','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','APP_ENCRYPTION_KEY','APP_HMAC_KEY','SMTP_HOST','SMTP_PORT','SMTP_SECURITY','SMTP_USERNAME','SMTP_PASSWORD','SMTP_FROM_EMAIL','SMTP_FROM_NAME','ADMIN_EMAIL')) {
        Set-Item -Path "Env:$name" -Value $null
    }
    $env:APP_ENV = 'development'
    $env:APP_NAME = 'Integration Test Voting System'
    $env:DB_NAME = $testDb
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = '3306'
    $env:DB_USER = 'root'
    $env:DB_PASSWORD = ''
    $env:APP_ENCRYPTION_KEY = $encKey
    $env:APP_HMAC_KEY = $hmacKey
    $env:APP_BASE_URL = $baseUrl
    $env:APP_TIMEZONE = 'UTC'
    $env:SMTP_HOST = '127.0.0.1'
    $env:SMTP_PORT = [string] $smtpPort
    $env:SMTP_SECURITY = 'none'
    $env:SMTP_USERNAME = ''
    $env:SMTP_PASSWORD = 'DummyConfigSecret-NotReal'
    $env:SMTP_FROM_EMAIL = 'test-sender@example.test'
    $env:SMTP_FROM_NAME = 'Integration Test'

    Invoke-MySql ("CREATE DATABASE " + $testDb + " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")
    $script:createdDatabase = $true

    $schema = [System.IO.File]::ReadAllText((Join-Path $root 'database\schema.sql')).Replace('online_voting_system', $testDb)
    [System.IO.File]::WriteAllText($schemaFile, $schema, (New-Object System.Text.UTF8Encoding($false)))
    Import-MySqlFile '' $schemaFile
    Import-MySqlFile $testDb (Join-Path $root 'database\migration_20261105_email_otp_and_rate_limits.sql')

    $fixtureJson = & $php (Join-Path $root 'tests\seed_integration.php')
    if ($LASTEXITCODE -ne 0) { throw 'Could not seed the isolated integration database.' }
    $fixtures = $fixtureJson | ConvertFrom-Json
    $env:TEST_ROOT = $root
    $env:TEST_VOTER_ID = [string] $fixtures.active_missing_verification_id
    $statusMappingCode = @'
require getenv('TEST_ROOT') . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
echo normalize_voter_status('approved') . ':' . normalize_voter_status('pending') . ':' . normalize_voter_status('rejected');
'@
    $statusMapping = & $php -r $statusMappingCode
    Assert ($statusMapping -eq 'unverified:unverified:deactivated') 'Legacy voter statuses remain blocked or inactive'
    $productionGuardCode = @'
require getenv('TEST_ROOT') . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
putenv('APP_ENV=production');
putenv('DB_PASSWORD=');
try {
    assert_production_configuration();
    echo 'allowed';
} catch (RuntimeException $exception) {
    echo 'blocked';
}
'@
    $productionGuard = & $php -r $productionGuardCode
    Assert ($productionGuard -eq 'blocked') 'Production configuration rejects a blank database password'

    $sessionGuardCode = @'
require getenv('TEST_ROOT') . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
$_SESSION['auth_type'] = 'voter';
$_SESSION['auth_id'] = (int) getenv('TEST_VOTER_ID');
$_SESSION['auth_version'] = 0;
echo current_user() === null ? 'blocked' : 'allowed';
'@
    $sessionGuard = & $php -r $sessionGuardCode
    Assert ($sessionGuard -eq 'blocked') 'Existing sessions cannot authorize active voters without email verification'

    $script:smtpProcess = Start-Process -FilePath $php -ArgumentList @(
        (Join-Path $root 'tests\fake_smtp.php'), ('--port=' + [string] $smtpPort), ('--spool=' + $spoolFile)
    ) -PassThru -WindowStyle Hidden -RedirectStandardOutput $smtpOut -RedirectStandardError $smtpErr
    $script:phpProcess = Start-Process -FilePath $php -ArgumentList @(
        '-S', "0.0.0.0:$port", '-t', $root
    ) -PassThru -WindowStyle Hidden -RedirectStandardOutput $phpOut -RedirectStandardError $phpErr

    $ready = $false
    for ($i = 0; $i -lt 50; $i++) {
        try {
            $null = Invoke-WebRequest -Uri "$baseUrl/index.php?page=login" -UseBasicParsing -TimeoutSec 2
            $ready = $true
            break
        } catch { Start-Sleep -Milliseconds 100 }
    }
    Assert $ready 'PHP application and fake SMTP server start'

    $configSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $configPage = Get-Page $configSession 'config-setup'
    Assert ($configPage.StatusCode -eq 200) 'Local configuration validator is available in development'
    Assert (-not $configPage.Content.Contains('<form') -and -not $configPage.Content.Contains('save_local_config')) 'Configuration validator is read-only and has no secret-entry form'
    Assert (-not $configPage.Content.Contains($env:SMTP_PASSWORD) -and -not $configPage.Content.Contains($encKey) -and -not $configPage.Content.Contains($hmacKey)) 'Configuration validation does not render secrets or encryption keys'
    $loginConfigPage = Get-Page $configSession 'login'
    $rejectedConfigPost = Post-Page $configSession 'config-setup' @{
        _csrf = Get-Csrf $loginConfigPage
        action = 'save_local_config'
        SMTP_PASSWORD = $env:SMTP_PASSWORD
        APP_CONFIG_FILE = 'C:\Windows\win.ini'
    }
    Assert (-not $rejectedConfigPost.Content.Contains($env:SMTP_PASSWORD)) 'Removed configuration-write POST action cannot echo submitted secrets'
    $externalHostStatus = Get-StatusWithHeaders 'config-setup' ("example.test:" + [string] $port)
    Assert ($externalHostStatus -eq 403) 'Configuration validator denies external Host headers'
    $forwardedStatus = Get-StatusWithHeaders 'config-setup' ("127.0.0.1:" + [string] $port) '203.0.113.8'
    Assert ($forwardedStatus -eq 403) 'Configuration validator denies forwarded proxy requests'

    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $registerResponse = Register-Applicant $session 'new-voter@example.test' 'NEW-VOTER-OTP-001'
    Assert ($registerResponse.Content.Contains('verification code') -or $registerResponse.Content.Contains('Verify your email')) 'Registration creates an OTP challenge'
    $code = Get-LatestCode 1

    $verifyPage = Get-Page $session 'verify-otp'
    $csrf = Get-Csrf $verifyPage
    $wrong = Post-Page $session 'verify-otp' @{ _csrf = $csrf; action = 'verify_otp'; otp = '000000' }
    Assert ($wrong.Content.Contains('incorrect') -or $wrong.Content.Contains('Check it and try again')) 'Incorrect OTP is rejected'

    $success = Post-Page $session 'verify-otp' @{ _csrf = $csrf; action = 'verify_otp'; otp = $code }
    Assert ($success.Content.Contains('Registration successful') -and $success.Content.Contains('active')) 'Correct OTP creates an active voter'

    $state = Invoke-MySql ("SELECT CONCAT(status, ':', IF(email_verified_at IS NULL,'unverified','verified')) FROM " + $testDb + ".voters WHERE email_normalized='new-voter@example.test'")
    Assert ($state -eq 'active:verified') 'Successful registration stores an active, verified voter'

    $dupEmailSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $dupEmail = Register-Applicant $dupEmailSession 'new-voter@example.test' 'NEW-VOTER-OTP-002'
    Assert ($dupEmail.Content.Contains('already associated') -or $dupEmail.Content.Contains('already in use')) 'Duplicate email is prevented'

    $dupNationalSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $dupNational = Register-Applicant $dupNationalSession 'another-new@example.test' 'NEW-VOTER-OTP-001'
    Assert ($dupNational.Content.Contains('already associated') -or $dupNational.Content.Contains('already in use')) 'Duplicate National ID is prevented'

    $reused = Post-Page $session 'verify-otp' @{ _csrf = $csrf; action = 'verify_otp'; otp = $code }
    Assert ($reused.Content.Contains('already been used') -or $reused.Content.Contains('This code is invalid')) 'OTP is single-use'

    $expiredSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $null = Register-Applicant $expiredSession 'expired-voter@example.test' 'EXPIRED-VOTER-OTP-003'
    $expiredCode = Get-LatestCode 2
    $expiredCsrf = Get-Csrf (Get-Page $expiredSession 'verify-otp')
    Invoke-MySql ("UPDATE " + $testDb + ".otp_challenges SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE email_normalized='expired-voter@example.test' AND purpose='registration' AND consumed_at IS NULL")
    $expiredResult = Post-Page $expiredSession 'verify-otp' @{ _csrf = $expiredCsrf; action = 'verify_otp'; otp = $expiredCode }
    Assert ($expiredResult.Content.Contains('Verification unavailable') -or $expiredResult.Content.Contains('invalid, expired')) 'Expired OTP is rejected'

    $attemptSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $null = Register-Applicant $attemptSession 'attempts-voter@example.test' 'ATTEMPTS-VOTER-OTP-004'
    $attemptCode = Get-LatestCode 3
    $wrongCode = if ($attemptCode -eq '999999') { '999998' } else { '999999' }
    $attemptCsrf = Get-Csrf (Get-Page $attemptSession 'verify-otp')
    for ($i = 1; $i -le 5; $i++) {
        $attemptResult = Post-Page $attemptSession 'verify-otp' @{ _csrf = $attemptCsrf; action = 'verify_otp'; otp = $wrongCode }
    }
    Assert ($attemptResult.Content.Contains('expired after too many attempts')) 'Five incorrect attempts are enforced'

    $resendSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $null = Register-Applicant $resendSession 'resend-voter@example.test' 'RESEND-VOTER-OTP-005'
    $resendPage = Get-Page $resendSession 'verify-otp'
    $cooldown = Post-Page $resendSession 'verify-otp' @{ _csrf = Get-Csrf $resendPage; action = 'resend_otp' }
    Assert ($cooldown.Content.Contains('Please wait') -or $cooldown.Content.Contains('wait 60 seconds')) 'Resend cooldown works'

    $loginPage = Get-Page (New-Object Microsoft.PowerShell.Commands.WebRequestSession) 'login'
    $unverifiedSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginPage = Get-Page $unverifiedSession 'login'
    $unverifiedLogin = Post-Page $unverifiedSession 'login' @{ _csrf = Get-Csrf $loginPage; action = 'login'; login = 'unverified@example.test'; password = 'TestVoterPassword-2026!' }
    Assert ($unverifiedLogin.Content.Contains('Verify your email address') -or $unverifiedLogin.Content.Contains('verify-otp')) 'Unverified accounts cannot log in'

    $activeSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginPage = Get-Page $activeSession 'login'
    $activeLogin = Post-Page $activeSession 'login' @{ _csrf = Get-Csrf $loginPage; action = 'login'; login = 'voter@example.test'; password = 'TestVoterPassword-2026!' }
    Assert ($activeLogin.Content.Contains('Welcome back') -or $activeLogin.Content.Contains('Dashboard')) 'Active voters can log in'

    $electionId = [string] $fixtures.election_id
    $candidateId = [string] $fixtures.candidate_id
    $electionPage = Get-Page $activeSession ('election&id=' + $electionId)
    $mismatchedCandidate = Post-Page $activeSession ('election&id=' + $electionId) @{
        _csrf = Get-Csrf $electionPage
        action = 'vote'
        election_id = $electionId
        candidate_id = [string] $fixtures.other_candidate_id
    }
    $mismatchVoteCount = Invoke-MySql ("SELECT COUNT(*) FROM " + $testDb + ".votes WHERE voter_id=" + [string] $fixtures.voter_id)
    Assert ($mismatchedCandidate.Content.Contains('does not belong to this election') -and $mismatchVoteCount -eq '0') 'Voting rejects candidates from another election'

    $electionPage = Get-Page $activeSession ('election&id=' + $electionId)
    $castVote = Post-Page $activeSession ('election&id=' + $electionId) @{
        _csrf = Get-Csrf $electionPage
        action = 'vote'
        election_id = $electionId
        candidate_id = $candidateId
    }
    $castVoteCount = Invoke-MySql ("SELECT COUNT(*) FROM " + $testDb + ".votes WHERE voter_id=" + [string] $fixtures.voter_id + " AND election_id=" + $electionId)
    Assert ($castVoteCount -eq '1' -and ($castVote.Content.Contains('vote was recorded') -or $castVote.Content.Contains('Your vote was recorded'))) 'Active voter can cast one vote in an open election'

    $electionPage = Get-Page $activeSession ('election&id=' + $electionId)
    $secondVote = Post-Page $activeSession ('election&id=' + $electionId) @{
        _csrf = Get-Csrf $electionPage
        action = 'vote'
        election_id = $electionId
        candidate_id = [string] $fixtures.second_candidate_id
    }
    $castVoteCount = Invoke-MySql ("SELECT COUNT(*) FROM " + $testDb + ".votes WHERE voter_id=" + [string] $fixtures.voter_id + " AND election_id=" + $electionId)
    Assert ($castVoteCount -eq '1' -and $secondVote.Content.Contains('already voted')) 'One-vote-per-election enforcement rejects a second vote'

    $unverifiedLegacySession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginPage = Get-Page $unverifiedLegacySession 'login'
    $legacyLogin = Post-Page $unverifiedLegacySession 'login' @{
        _csrf = Get-Csrf $loginPage
        action = 'login'
        login = 'active-missing-verification@example.test'
        password = 'TestVoterPassword-2026!'
    }
    Assert ($legacyLogin.Content.Contains('Verify your email address') -or $legacyLogin.Content.Contains('verify-otp')) 'Voters missing verification evidence must complete OTP before sign-in'
    $legacyCode = Get-LatestCode 6
    $legacyVerify = Post-Page $unverifiedLegacySession 'verify-otp' @{
        _csrf = Get-Csrf (Get-Page $unverifiedLegacySession 'verify-otp')
        action = 'verify_otp'
        otp = $legacyCode
    }
    $legacyStatus = Invoke-MySql ("SELECT CONCAT(status, ':', IF(email_verified_at IS NULL,'unverified','verified')) FROM " + $testDb + ".voters WHERE email_normalized='active-missing-verification@example.test'")
    Assert ($legacyStatus -eq 'active:verified' -and ($legacyVerify.Content.Contains('verified') -or $legacyVerify.Content.Contains('Dashboard'))) 'Legacy voter becomes active only after completing a fresh email OTP'

    $suspendedSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginPage = Get-Page $suspendedSession 'login'
    $suspendedResult = Post-Page $suspendedSession 'login' @{ _csrf = Get-Csrf $loginPage; action = 'login'; login = 'suspended@example.test'; password = 'TestVoterPassword-2026!' }
    Assert ($suspendedResult.Content.Contains('suspended') -or $suspendedResult.Content.Contains('Contact the administrator')) 'Suspended accounts cannot log in'

    $deactivatedSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginPage = Get-Page $deactivatedSession 'login'
    $deactivatedResult = Post-Page $deactivatedSession 'login' @{ _csrf = Get-Csrf $loginPage; action = 'login'; login = 'deactivated@example.test'; password = 'TestVoterPassword-2026!' }
    Assert ($deactivatedResult.Content.Contains('deactivated') -or $deactivatedResult.Content.Contains('cannot be used')) 'Deactivated accounts cannot log in'

    $recoverySession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $forgot = Get-Page $recoverySession 'forgot-password'
    $unverifiedRecovery = Post-Page $recoverySession 'forgot-password' @{ _csrf = Get-Csrf $forgot; action = 'request_reset'; email = 'unverified@example.test' }
    $statusAfterUnverifiedRecovery = Invoke-MySql ("SELECT status FROM " + $testDb + ".voters WHERE email_normalized='unverified@example.test'")
    Assert ($unverifiedRecovery.Content.Contains('If an eligible account uses that email') -and $statusAfterUnverifiedRecovery -eq 'unverified') 'Password recovery does not activate an unverified account'

    $recoverySession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $forgot = Get-Page $recoverySession 'forgot-password'
    $null = Post-Page $recoverySession 'forgot-password' @{ _csrf = Get-Csrf $forgot; action = 'request_reset'; email = 'voter@example.test' }
    $recoveryCode = Get-LatestCode 4
    $recoveryVerify = Post-Page $recoverySession 'verify-otp' @{ _csrf = Get-Csrf (Get-Page $recoverySession 'verify-otp'); action = 'verify_otp'; otp = $recoveryCode }
    Assert ($recoveryVerify.Content.Contains('new password') -or $recoveryVerify.Content.Contains('Choose a new password')) 'Password recovery sends a valid OTP for an active voter'

    $newPasswordPage = Get-Page $recoverySession 'new-password'
    $recoveryReset = Post-Page $recoverySession 'new-password' @{ _csrf = Get-Csrf $newPasswordPage; action = 'reset_password'; password = 'RecoveryPassword-2026!'; confirm_password = 'RecoveryPassword-2026!' }
    $statusAfterRecovery = Invoke-MySql ("SELECT status FROM " + $testDb + ".voters WHERE email_normalized='voter@example.test'")
    Assert ($statusAfterRecovery -eq 'active') 'Password recovery keeps an active voter active'

    $adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $adminLoginPage = Get-Page $adminSession 'login'
    $adminLogin = Post-Page $adminSession 'login' @{
        _csrf = Get-Csrf $adminLoginPage
        action = 'login'
        login = 'admin@example.test'
        password = 'TestAdminPassword-2026!'
    }
    Assert ($adminLogin.Content.Contains('Welcome back') -or $adminLogin.Content.Contains('Dashboard')) 'Main Admin account can sign in'

    $votersPage = Get-Page $adminSession 'voters'
    $legacyActivation = Post-Page $adminSession 'voters' @{
        _csrf = Get-Csrf $votersPage
        action = 'set_voter_status'
        voter_id = [string] $fixtures.legacy_approved_id
        status = 'active'
    }
    $legacyStatus = Invoke-MySql ("SELECT status FROM " + $testDb + ".voters WHERE id=" + [string] $fixtures.legacy_approved_id)
    Assert ($legacyStatus -eq 'unverified' -and $legacyActivation.Content.Contains('cannot be activated manually')) 'Admin cannot bypass OTP for a legacy approved voter with a historical timestamp'

    $votersPage = Get-Page $adminSession 'voters'
    $promoted = Post-Page $adminSession 'voters' @{
        _csrf = Get-Csrf $votersPage
        action = 'promote'
        voter_id = [string] $fixtures.voter_id
    }
    $juniorState = Invoke-MySql ("SELECT is_junior_admin FROM " + $testDb + ".voters WHERE id=" + [string] $fixtures.voter_id)
    Assert ($juniorState -eq '1') 'Main Admin can promote an active verified voter to Junior Admin'
    $votersPage = Get-Page $adminSession 'voters'
    $demoted = Post-Page $adminSession 'voters' @{
        _csrf = Get-Csrf $votersPage
        action = 'demote'
        voter_id = [string] $fixtures.voter_id
    }
    $juniorState = Invoke-MySql ("SELECT is_junior_admin FROM " + $testDb + ".voters WHERE id=" + [string] $fixtures.voter_id)
    Assert ($juniorState -eq '0') 'Main Admin can remove Junior Admin privileges'

    $voteCountBefore = Invoke-MySql ("SELECT COUNT(*) FROM " + $testDb + ".votes")
    $legacyEndpoint = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    try {
        $null = Get-Page $legacyEndpoint 'review_application'
        Assert $false 'Legacy approval endpoints are inaccessible'
    } catch {
        Assert $true 'Legacy review pages are inaccessible'
    }

    $voteCountAfter = Invoke-MySql ("SELECT COUNT(*) FROM " + $testDb + ".votes")
    Assert ($voteCountBefore -eq $voteCountAfter) 'Election and vote records remain intact'

    $legacyActionSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $legacyActionPage = Get-Page $legacyActionSession 'login'
    $legacyApprove = Post-Page $legacyActionSession 'login' @{
        _csrf = Get-Csrf $legacyActionPage
        action = 'approve_application'
        application_id = '1'
    }
    $legacyReject = Post-Page $legacyActionSession 'login' @{
        _csrf = Get-Csrf (Get-Page $legacyActionSession 'login')
        action = 'reject_application'
        application_id = '1'
    }
    Assert ($legacyApprove.Content.Contains('Unknown form action') -and $legacyReject.Content.Contains('Unknown form action')) 'Removed approval and rejection POST actions cannot be triggered'

    Write-Output "VALIDATION PASS: $passes checks passed"
    exit 0
} catch {
    Write-Error $_
    exit 1
} finally {
    Cleanup
}
