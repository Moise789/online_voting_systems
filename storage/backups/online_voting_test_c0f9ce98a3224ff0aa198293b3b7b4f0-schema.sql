CREATE DATABASE IF NOT EXISTS online_voting_test_c0f9ce98a3224ff0aa198293b3b7b4f0
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE online_voting_test_c0f9ce98a3224ff0aa198293b3b7b4f0;

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    singleton_key TINYINT UNSIGNED NOT NULL DEFAULT 1,
    full_name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL,
    email_normalized VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    session_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_singleton (singleton_key),
    UNIQUE KEY uq_admin_email (email_normalized),
    CONSTRAINT chk_admin_singleton CHECK (singleton_key = 1)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS voters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(160) NOT NULL,
    national_id_ciphertext TEXT NOT NULL,
    national_id_hmac CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    email VARCHAR(254) NOT NULL,
    email_normalized VARCHAR(254) NOT NULL,
    email_verified_at DATETIME NULL,
    date_of_birth DATE NOT NULL,
    gender VARCHAR(40) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    session_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('unverified','active','suspended','deactivated') NOT NULL DEFAULT 'unverified',
    is_junior_admin BOOLEAN NOT NULL DEFAULT FALSE,
    promoted_by BIGINT UNSIGNED NULL,
    promoted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_voter_national_id_hmac (national_id_hmac),
    UNIQUE KEY uq_voter_email (email_normalized),
    KEY ix_voters_status (status, created_at),
    KEY ix_voters_junior (is_junior_admin),
    CONSTRAINT fk_voters_promoted_by FOREIGN KEY (promoted_by) REFERENCES admins(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS voter_identifiers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voter_id BIGINT UNSIGNED NOT NULL,
    identifier_type ENUM('email','national_id') NOT NULL,
    identifier_hmac CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_identifier_owner (identifier_type, identifier_hmac),
    KEY ix_identifier_voter (voter_id),
    CONSTRAINT fk_identifier_voter FOREIGN KEY (voter_id) REFERENCES voters(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS voter_profile_changes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voter_id BIGINT UNSIGNED NOT NULL,
    changed_by BIGINT UNSIGNED NOT NULL,
    previous_email VARCHAR(254) NOT NULL,
    new_email VARCHAR(254) NOT NULL,
    previous_national_id_ciphertext TEXT NOT NULL,
    new_national_id_ciphertext TEXT NOT NULL,
    previous_profile JSON NOT NULL,
    new_profile JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_profile_change_voter (voter_id, created_at),
    CONSTRAINT fk_profile_change_voter FOREIGN KEY (voter_id) REFERENCES voters(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_profile_change_admin FOREIGN KEY (changed_by) REFERENCES admins(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS elections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM('draft','scheduled','active','closed') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    KEY ix_election_schedule (status, starts_at, ends_at),
    CONSTRAINT fk_election_admin FOREIGN KEY (created_by) REFERENCES admins(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_election_dates CHECK (ends_at > starts_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS candidates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    party VARCHAR(160) NOT NULL DEFAULT '',
    biography TEXT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_candidate_election (id, election_id),
    KEY ix_candidate_election (election_id, full_name),
    CONSTRAINT fk_candidate_election FOREIGN KEY (election_id) REFERENCES elections(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS votes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voter_id BIGINT UNSIGNED NOT NULL,
    election_id BIGINT UNSIGNED NOT NULL,
    candidate_id BIGINT UNSIGNED NOT NULL,
    cast_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_one_vote_per_election (voter_id, election_id),
    KEY ix_votes_election_candidate (election_id, candidate_id),
    CONSTRAINT fk_vote_voter FOREIGN KEY (voter_id) REFERENCES voters(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_vote_election FOREIGN KEY (election_id) REFERENCES elections(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_vote_candidate_election FOREIGN KEY (candidate_id, election_id)
        REFERENCES candidates(id, election_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('admin','voter') NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_reset_hash (token_hash),
    KEY ix_password_reset_account (account_type, account_id, expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type ENUM('admin','voter','system') NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(80) NOT NULL,
    target_type VARCHAR(80) NULL,
    target_id BIGINT UNSIGNED NULL,
    details JSON NULL,
    ip_address VARBINARY(16) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_activity_created (created_at),
    KEY ix_activity_actor (actor_type, actor_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS email_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(254) NOT NULL,
    notification_type VARCHAR(80) NOT NULL,
    status ENUM('sent','failed') NOT NULL,
    error_message VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_notification_type_date (notification_type, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS otp_challenges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purpose ENUM('registration','password_recovery','email_verification') NOT NULL,
    flow_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    email VARCHAR(254) NOT NULL,
    email_normalized VARCHAR(254) NOT NULL,
    account_type ENUM('admin','voter') NULL,
    account_id BIGINT UNSIGNED NULL,
    payload MEDIUMTEXT NULL,
    code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    resend_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    last_sent_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_otp_flow_hash (flow_hash),
    KEY ix_otp_email_purpose (email_normalized, purpose, created_at),
    KEY ix_otp_expiry (expires_at, consumed_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auth_rate_limits (
    limiter_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY ix_rate_limit_blocked (blocked_until)
) ENGINE=InnoDB;
