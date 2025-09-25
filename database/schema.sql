-- fragfolio Database Schema
-- Created with sqldef for Laravel 12 project

-- Users table with roles and 2FA support
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    two_factor_secret TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    two_factor_confirmed_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
);

-- User profiles for additional information
CREATE TABLE user_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    avatar VARCHAR(255) NULL,
    bio TEXT NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'other', 'prefer_not_to_say') NULL,
    country VARCHAR(2) NULL,
    `language` VARCHAR(10) NOT NULL DEFAULT 'ja',
    timezone VARCHAR(50) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_user_profiles_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Password reset tokens
CREATE TABLE password_reset_tokens (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    PRIMARY KEY (email),
    INDEX idx_password_reset_tokens_token (token)
);

-- Sessions
CREATE TABLE sessions (
    id VARCHAR(255) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_sessions_user_id (user_id),
    INDEX idx_sessions_last_activity (last_activity)
);

-- Email change requests for secure email address changes
CREATE TABLE email_change_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    new_email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    verified BOOLEAN NOT NULL DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_email_change_requests_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email_change_requests_token (token),
    INDEX idx_email_change_requests_user_id (user_id),
    INDEX idx_email_change_requests_expires_at (expires_at)
);

-- Personal Access Tokens
CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_personal_access_tokens_tokenable (tokenable_type, tokenable_id)
);

-- Cache
CREATE TABLE `cache` (
    `key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    expiration INT NOT NULL,
    PRIMARY KEY (`key`)
);

-- Cache locks
CREATE TABLE cache_locks (
    `key` VARCHAR(255) NOT NULL,
    owner VARCHAR(255) NOT NULL,
    expiration INT NOT NULL,
    PRIMARY KEY (`key`)
);

-- Job queue
CREATE TABLE jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_jobs_queue (queue)
);

-- Failed jobs
CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL UNIQUE,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- Brands master data
CREATE TABLE brands (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name_ja VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    description_ja TEXT NULL,
    description_en TEXT NULL,
    country VARCHAR(2) NULL,
    founded_year YEAR NULL,
    website VARCHAR(255) NULL,
    logo VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_brands_name_ja (name_ja),
    INDEX idx_brands_name_en (name_en),
    INDEX idx_brands_country (country)
);

-- Fragrance concentration types
CREATE TABLE concentration_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL UNIQUE,
    name_ja VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    description_ja TEXT NULL,
    description_en TEXT NULL,
    oil_concentration_min DECIMAL(4,2) NULL,
    oil_concentration_max DECIMAL(4,2) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_concentration_types_code (code)
);

-- Fragrance categories
CREATE TABLE fragrance_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name_ja VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    description_ja TEXT NULL,
    description_en TEXT NULL,
    parent_id BIGINT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_fragrance_categories_parent_id FOREIGN KEY (parent_id) REFERENCES fragrance_categories(id) ON DELETE SET NULL,
    INDEX idx_fragrance_categories_parent_id (parent_id)
);

-- Fragrance notes master data
CREATE TABLE fragrance_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name_ja VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    description_ja TEXT NULL,
    description_en TEXT NULL,
    category VARCHAR(50) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_fragrance_notes_name_ja (name_ja),
    INDEX idx_fragrance_notes_name_en (name_en),
    INDEX idx_fragrance_notes_category (category)
);

-- Fragrances master data
CREATE TABLE fragrances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id BIGINT UNSIGNED NOT NULL,
    name_ja VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    description_ja TEXT NULL,
    description_en TEXT NULL,
    concentration_type_id BIGINT UNSIGNED NULL,
    release_year YEAR NULL,
    image VARCHAR(255) NULL,
    is_discontinued BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_fragrances_brand_id FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    CONSTRAINT fk_fragrances_concentration_type_id FOREIGN KEY (concentration_type_id) REFERENCES concentration_types(id) ON DELETE SET NULL,
    INDEX idx_fragrances_brand_id (brand_id),
    INDEX idx_fragrances_name_ja (name_ja),
    INDEX idx_fragrances_name_en (name_en),
    INDEX idx_fragrances_release_year (release_year)
);

-- Fragrance category mappings
CREATE TABLE fragrance_category_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fragrance_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_fragrance_category_mappings_fragrance_id FOREIGN KEY (fragrance_id) REFERENCES fragrances(id) ON DELETE CASCADE,
    CONSTRAINT fk_fragrance_category_mappings_category_id FOREIGN KEY (category_id) REFERENCES fragrance_categories(id) ON DELETE CASCADE,
    UNIQUE INDEX unique_fragrance_category (fragrance_id, category_id)
);

-- Fragrance notes mappings with note positions
CREATE TABLE fragrance_note_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fragrance_id BIGINT UNSIGNED NOT NULL,
    note_id BIGINT UNSIGNED NOT NULL,
    note_position ENUM('top', 'middle', 'base', 'single') NOT NULL DEFAULT 'single',
    intensity TINYINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_fragrance_note_mappings_fragrance_id FOREIGN KEY (fragrance_id) REFERENCES fragrances(id) ON DELETE CASCADE,
    CONSTRAINT fk_fragrance_note_mappings_note_id FOREIGN KEY (note_id) REFERENCES fragrance_notes(id) ON DELETE CASCADE,
    UNIQUE INDEX unique_fragrance_note_position (fragrance_id, note_id, note_position),
    INDEX idx_fragrance_note_mappings_fragrance_id (fragrance_id),
    INDEX idx_fragrance_note_mappings_note_id (note_id),
    INDEX idx_fragrance_note_mappings_note_position (note_position)
);

-- Scene appropriateness
CREATE TABLE scenes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name_ja VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    description_ja TEXT NULL,
    description_en TEXT NULL,
    icon VARCHAR(50) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
);

-- Season appropriateness
CREATE TABLE seasons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name_ja VARCHAR(50) NOT NULL,
    name_en VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
);

-- Fragrance scene mappings
CREATE TABLE fragrance_scene_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fragrance_id BIGINT UNSIGNED NOT NULL,
    scene_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_fragrance_scene_mappings_fragrance_id FOREIGN KEY (fragrance_id) REFERENCES fragrances(id) ON DELETE CASCADE,
    CONSTRAINT fk_fragrance_scene_mappings_scene_id FOREIGN KEY (scene_id) REFERENCES scenes(id) ON DELETE CASCADE,
    UNIQUE INDEX unique_fragrance_scene (fragrance_id, scene_id)
);

-- Fragrance season mappings
CREATE TABLE fragrance_season_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fragrance_id BIGINT UNSIGNED NOT NULL,
    season_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_fragrance_season_mappings_fragrance_id FOREIGN KEY (fragrance_id) REFERENCES fragrances(id) ON DELETE CASCADE,
    CONSTRAINT fk_fragrance_season_mappings_season_id FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    UNIQUE INDEX unique_fragrance_season (fragrance_id, season_id)
);

-- User's fragrance collection
CREATE TABLE user_fragrances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    fragrance_id BIGINT UNSIGNED NOT NULL,
    purchase_date DATE NULL,
    volume_ml DECIMAL(6,2) NULL,
    purchase_price DECIMAL(10,2) NULL,
    purchase_place VARCHAR(255) NULL,
    current_volume_ml DECIMAL(6,2) NULL,
    possession_type ENUM('full_bottle', 'decant', 'sample') NOT NULL DEFAULT 'full_bottle',
    duration_hours TINYINT UNSIGNED NULL,
    projection ENUM('weak', 'moderate', 'strong') NULL,
    user_rating TINYINT UNSIGNED NULL,
    comments TEXT NULL,
    bottle_image VARCHAR(255) NULL,
    box_image VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_user_fragrances_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_fragrances_fragrance_id FOREIGN KEY (fragrance_id) REFERENCES fragrances(id) ON DELETE CASCADE,
    INDEX idx_user_fragrances_user_id (user_id),
    INDEX idx_user_fragrances_fragrance_id (fragrance_id),
    INDEX idx_user_fragrances_purchase_date (purchase_date)
);

-- User's fragrance tags
CREATE TABLE user_fragrance_tags (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_fragrance_id BIGINT UNSIGNED NOT NULL,
    tag_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_user_fragrance_tags_user_fragrance_id FOREIGN KEY (user_fragrance_id) REFERENCES user_fragrances(id) ON DELETE CASCADE,
    INDEX idx_user_fragrance_tags_user_fragrance_id (user_fragrance_id),
    INDEX idx_user_fragrance_tags_tag_name (tag_name)
);

-- User's wearing logs
CREATE TABLE wearing_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    user_fragrance_id BIGINT UNSIGNED NOT NULL,
    worn_at DATETIME NOT NULL,
    temperature SMALLINT NULL,
    weather VARCHAR(50) NULL,
    location VARCHAR(255) NULL,
    occasion VARCHAR(255) NULL,
    sprays_count TINYINT UNSIGNED NULL,
    performance_rating TINYINT UNSIGNED NULL,
    comments TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_wearing_logs_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wearing_logs_user_fragrance_id FOREIGN KEY (user_fragrance_id) REFERENCES user_fragrances(id) ON DELETE CASCADE,
    INDEX idx_wearing_logs_user_id (user_id),
    INDEX idx_wearing_logs_user_fragrance_id (user_fragrance_id),
    INDEX idx_wearing_logs_worn_at (worn_at)
);

-- User's rating history
CREATE TABLE user_rating_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_fragrance_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comments TEXT NULL,
    rated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_user_rating_history_user_fragrance_id FOREIGN KEY (user_fragrance_id) REFERENCES user_fragrances(id) ON DELETE CASCADE,
    INDEX idx_user_rating_history_user_fragrance_id (user_fragrance_id),
    INDEX idx_user_rating_history_rated_at (rated_at)
);

-- Others' reactions to user's fragrances
CREATE TABLE reaction_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    wearing_log_id BIGINT UNSIGNED NOT NULL,
    reactor_type ENUM('friend', 'colleague', 'stranger', 'family', 'other') NOT NULL,
    reaction_type ENUM('positive', 'negative', 'neutral', 'asked_about') NOT NULL,
    comments TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_reaction_logs_wearing_log_id FOREIGN KEY (wearing_log_id) REFERENCES wearing_logs(id) ON DELETE CASCADE,
    INDEX idx_reaction_logs_wearing_log_id (wearing_log_id),
    INDEX idx_reaction_logs_reaction_type (reaction_type)
);

-- WebAuthn credentials for FIDO2 authentication (Laragear/WebAuthn)
CREATE TABLE webauthn_credentials (
    id VARCHAR(510) NOT NULL,
    authenticatable_type VARCHAR(255) NOT NULL,
    authenticatable_id BIGINT UNSIGNED NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    alias VARCHAR(255) NULL,
    counter BIGINT UNSIGNED NULL,
    rp_id VARCHAR(255) NOT NULL,
    origin VARCHAR(255) NOT NULL,
    transports JSON NULL,
    aaguid CHAR(36) NULL,
    public_key TEXT NOT NULL,
    attestation_format VARCHAR(255) NOT NULL DEFAULT 'none',
    certificates JSON NULL,
    disabled_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX webauthn_user_index (authenticatable_type, authenticatable_id),
    INDEX idx_webauthn_credentials_user_id (user_id)
);

-- AI normalization logs
CREATE TABLE ai_normalization_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    input_text VARCHAR(500) NOT NULL,
    normalized_text VARCHAR(500) NULL,
    ai_provider ENUM('openai', 'anthropic') NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    entity_type ENUM('brand', 'fragrance', 'note') NOT NULL,
    confidence_score DECIMAL(4,3) NULL,
    `status` ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    processing_time_ms INT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_ai_normalization_logs_input_text (input_text),
    INDEX idx_ai_normalization_logs_entity_type (entity_type),
    INDEX idx_ai_normalization_logs_ai_provider (ai_provider),
    INDEX idx_ai_normalization_logs_status (`status`),
    INDEX idx_ai_normalization_logs_created_at (created_at)
);

-- Permission system tables (spatie/laravel-permission)
CREATE TABLE permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX permissions_name_guard_name_unique (`name`, guard_name)
);

CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX roles_name_guard_name_unique (`name`, guard_name)
);

CREATE TABLE model_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    INDEX idx_model_has_permissions_model_id_model_type (model_id, model_type),
    CONSTRAINT fk_model_has_permissions_permission_id FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (permission_id, model_id, model_type)
);

CREATE TABLE model_has_roles (
    role_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    INDEX idx_model_has_roles_model_id_model_type (model_id, model_type),
    CONSTRAINT fk_model_has_roles_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, model_id, model_type)
);

CREATE TABLE role_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    CONSTRAINT fk_role_has_permissions_permission_id FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_has_permissions_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (permission_id, role_id)
);

-- AI cost tracking for usage monitoring and billing
CREATE TABLE ai_cost_tracking (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    provider ENUM('openai', 'anthropic') NOT NULL,
    operation_type VARCHAR(50) NOT NULL,
    tokens_used INT UNSIGNED NOT NULL DEFAULT 0,
    estimated_cost DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    api_response_time_ms INT UNSIGNED NOT NULL DEFAULT 0,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_ai_cost_tracking_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ai_cost_tracking_user_id_created_at (user_id, created_at),
    INDEX idx_ai_cost_tracking_provider_operation (provider, operation_type),
    INDEX idx_ai_cost_tracking_created_at (created_at)
);