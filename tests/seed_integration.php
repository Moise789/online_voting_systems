<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = pdo();
$adminPassword = 'TestAdminPassword-2026!';
$voterPassword = 'TestVoterPassword-2026!';
$adminEmail = 'admin@example.test';
$voterEmail = 'voter@example.test';
$unverifiedEmail = 'unverified@example.test';
$activeMissingVerificationEmail = 'active-missing-verification@example.test';
$suspendedEmail = 'suspended@example.test';
$deactivatedEmail = 'deactivated@example.test';

$admin = $pdo->prepare(
    'INSERT INTO admins (singleton_key, full_name, email, email_normalized, password_hash)
     VALUES (1, ?, ?, ?, ?)'
);
$admin->execute(['Integration Admin', $adminEmail, normalize_email($adminEmail), password_hash($adminPassword, PASSWORD_DEFAULT)]);
$adminId = (int) $pdo->lastInsertId();

$identifier = $pdo->prepare('INSERT INTO voter_identifiers (voter_id, identifier_type, identifier_hmac) VALUES (?, ?, ?)');

$activeNationalId = 'INTEGRATION-VOTER-100';
$active = $pdo->prepare(
    "INSERT INTO voters
        (full_name, national_id_ciphertext, national_id_hmac, email, email_normalized, email_verified_at,
         date_of_birth, gender, phone, password_hash, status)
     VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), '1990-01-01', 'Prefer not to say', '+12025550123', ?, 'active')"
);
$active->execute([
    'Integration Voter',
    encrypt_value($activeNationalId),
    identifier_hash('national_id', normalize_national_id($activeNationalId)),
    $voterEmail,
    normalize_email($voterEmail),
    password_hash($voterPassword, PASSWORD_DEFAULT),
]);
$voterId = (int) $pdo->lastInsertId();
$identifier->execute([$voterId, 'email', identifier_hash('email', normalize_email($voterEmail))]);
$identifier->execute([$voterId, 'national_id', identifier_hash('national_id', normalize_national_id($activeNationalId))]);

$unverifiedNationalId = 'INTEGRATION-UNVERIFIED-100';
$unverified = $pdo->prepare(
    "INSERT INTO voters
        (full_name, national_id_ciphertext, national_id_hmac, email, email_normalized,
         date_of_birth, gender, phone, password_hash, status)
     VALUES (?, ?, ?, ?, ?, '1991-01-01', 'Prefer not to say', '+12025550124', ?, 'unverified')"
);
$unverified->execute([
    'Unverified Voter',
    encrypt_value($unverifiedNationalId),
    identifier_hash('national_id', normalize_national_id($unverifiedNationalId)),
    $unverifiedEmail,
    normalize_email($unverifiedEmail),
    password_hash($voterPassword, PASSWORD_DEFAULT),
]);
$unverifiedId = (int) $pdo->lastInsertId();
$identifier->execute([$unverifiedId, 'email', identifier_hash('email', normalize_email($unverifiedEmail))]);
$identifier->execute([$unverifiedId, 'national_id', identifier_hash('national_id', normalize_national_id($unverifiedNationalId))]);

$activeMissingVerificationNationalId = 'INTEGRATION-ACTIVE-MISSING-VERIFICATION-100';
$activeMissingVerification = $pdo->prepare(
    "INSERT INTO voters
        (full_name, national_id_ciphertext, national_id_hmac, email, email_normalized,
         date_of_birth, gender, phone, password_hash, status)
     VALUES (?, ?, ?, ?, ?, '1991-06-01', 'Prefer not to say', '+12025550127', ?, 'active')"
);
$activeMissingVerification->execute([
    'Voter Missing Verification Timestamp',
    encrypt_value($activeMissingVerificationNationalId),
    identifier_hash('national_id', normalize_national_id($activeMissingVerificationNationalId)),
    $activeMissingVerificationEmail,
    normalize_email($activeMissingVerificationEmail),
    password_hash($voterPassword, PASSWORD_DEFAULT),
]);
$activeMissingVerificationId = (int) $pdo->lastInsertId();
$identifier->execute([$activeMissingVerificationId, 'email', identifier_hash('email', normalize_email($activeMissingVerificationEmail))]);
$identifier->execute([$activeMissingVerificationId, 'national_id', identifier_hash('national_id', normalize_national_id($activeMissingVerificationNationalId))]);

$legacyApprovedEmail = 'legacy-approved@example.test';
$legacyApprovedNationalId = 'INTEGRATION-LEGACY-APPROVED-100';
$legacyApproved = $pdo->prepare(
    "INSERT INTO voters
        (full_name, national_id_ciphertext, national_id_hmac, email, email_normalized, email_verified_at,
         date_of_birth, gender, phone, password_hash, status)
     VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), '1991-06-02', 'Prefer not to say', '+12025550128', ?, 'unverified')"
);
$legacyApproved->execute([
    'Legacy Approved Voter',
    encrypt_value($legacyApprovedNationalId),
    identifier_hash('national_id', normalize_national_id($legacyApprovedNationalId)),
    $legacyApprovedEmail,
    normalize_email($legacyApprovedEmail),
    password_hash($voterPassword, PASSWORD_DEFAULT),
]);
$legacyApprovedId = (int) $pdo->lastInsertId();
$identifier->execute([$legacyApprovedId, 'email', identifier_hash('email', normalize_email($legacyApprovedEmail))]);
$identifier->execute([$legacyApprovedId, 'national_id', identifier_hash('national_id', normalize_national_id($legacyApprovedNationalId))]);

$suspendedNationalId = 'INTEGRATION-SUSPENDED-100';
$suspended = $pdo->prepare(
    "INSERT INTO voters
        (full_name, national_id_ciphertext, national_id_hmac, email, email_normalized, email_verified_at,
         date_of_birth, gender, phone, password_hash, status)
     VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), '1992-01-01', 'Prefer not to say', '+12025550125', ?, 'suspended')"
);
$suspended->execute([
    'Suspended Voter',
    encrypt_value($suspendedNationalId),
    identifier_hash('national_id', normalize_national_id($suspendedNationalId)),
    $suspendedEmail,
    normalize_email($suspendedEmail),
    password_hash($voterPassword, PASSWORD_DEFAULT),
]);
$suspendedId = (int) $pdo->lastInsertId();
$identifier->execute([$suspendedId, 'email', identifier_hash('email', normalize_email($suspendedEmail))]);
$identifier->execute([$suspendedId, 'national_id', identifier_hash('national_id', normalize_national_id($suspendedNationalId))]);

$deactivatedNationalId = 'INTEGRATION-DEACTIVATED-100';
$deactivated = $pdo->prepare(
    "INSERT INTO voters
        (full_name, national_id_ciphertext, national_id_hmac, email, email_normalized, email_verified_at,
         date_of_birth, gender, phone, password_hash, status)
     VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), '1993-01-01', 'Prefer not to say', '+12025550126', ?, 'deactivated')"
);
$deactivated->execute([
    'Deactivated Voter',
    encrypt_value($deactivatedNationalId),
    identifier_hash('national_id', normalize_national_id($deactivatedNationalId)),
    $deactivatedEmail,
    normalize_email($deactivatedEmail),
    password_hash($voterPassword, PASSWORD_DEFAULT),
]);
$deactivatedId = (int) $pdo->lastInsertId();
$identifier->execute([$deactivatedId, 'email', identifier_hash('email', normalize_email($deactivatedEmail))]);
$identifier->execute([$deactivatedId, 'national_id', identifier_hash('national_id', normalize_national_id($deactivatedNationalId))]);

$election = $pdo->prepare(
    "INSERT INTO elections (name, description, starts_at, ends_at, status, created_by)
     VALUES (?, '', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), 'active', ?)"
);
$election->execute(['Integration open election', $adminId]);
$electionId = (int) $pdo->lastInsertId();
$candidate = $pdo->prepare('INSERT INTO candidates (election_id, full_name, party, biography, photo_path) VALUES (?, ?, ?, ?, ?)');
$photo = 'uploads/candidates/209d110cfe4fb17dce4ad18a6b61b74600303473.png';
$candidate->execute([$electionId, 'Candidate One', 'Party One', 'Test candidate', $photo]);
$candidateId = (int) $pdo->lastInsertId();
$candidate->execute([$electionId, 'Candidate Two', 'Party Two', 'Test candidate', $photo]);
$secondCandidateId = (int) $pdo->lastInsertId();

$otherElection = $pdo->prepare(
    "INSERT INTO elections (name, description, starts_at, ends_at, status, created_by)
     VALUES (?, '', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 HOUR), 'draft', ?)"
);
$otherElection->execute(['Integration second election', $adminId]);
$otherElectionId = (int) $pdo->lastInsertId();
$candidate->execute([$otherElectionId, 'Other Candidate', 'Other Party', 'Test candidate', $photo]);
$otherCandidateId = (int) $pdo->lastInsertId();

$reset = $pdo->prepare(
    'INSERT INTO password_resets (account_type, account_id, token_hash, expires_at)
     VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR))'
);
$reset->execute(['voter', $voterId, hash('sha256', random_bytes(32))]);

echo json_encode([
    'admin_id' => $adminId,
    'voter_id' => $voterId,
    'unverified_id' => $unverifiedId,
    'active_missing_verification_id' => $activeMissingVerificationId,
    'legacy_approved_id' => $legacyApprovedId,
    'suspended_id' => $suspendedId,
    'deactivated_id' => $deactivatedId,
    'election_id' => $electionId,
    'candidate_id' => $candidateId,
    'second_candidate_id' => $secondCandidateId,
    'other_election_id' => $otherElectionId,
    'other_candidate_id' => $otherCandidateId,
], JSON_THROW_ON_ERROR);