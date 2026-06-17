-- Migration: Add provisioning columns to tenants table
-- Run this after the base control-database.sql

-- First, modify the status enum to include provisioning states
-- Note: MySQL doesn't allow direct enum modification, so we use a workaround

-- Add new columns for provisioning tracking
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS installed_at TIMESTAMP NULL DEFAULT NULL AFTER metadata,
    ADD COLUMN IF NOT EXISTS wp_admin_username VARCHAR(128) NULL DEFAULT NULL AFTER installed_at,
    ADD COLUMN IF NOT EXISTS wp_admin_email VARCHAR(255) NULL DEFAULT NULL AFTER wp_admin_username,
    ADD COLUMN IF NOT EXISTS installation_error TEXT NULL DEFAULT NULL AFTER wp_admin_email,
    ADD COLUMN IF NOT EXISTS installation_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER installation_error;

-- Modify status enum to include provisioning states
-- This requires recreating the enum with all values
-- For MySQL 8.0+:
ALTER TABLE tenants
    MODIFY COLUMN status ENUM(
        'pending',
        'installing',
        'installed',
        'failed',
        'active',
        'suspended',
        'disabled'
    ) NOT NULL DEFAULT 'pending';

-- For older MySQL versions, you may need to:
-- 1. Create a new column with the new enum
-- 2. Copy data with conversion
-- 3. Drop old column
-- 4. Rename new column

-- Add index on status for faster lookups
CREATE INDEX IF NOT EXISTS tenants_status_index ON tenants(status);

-- Add index on installed_at for reporting
CREATE INDEX IF NOT EXISTS tenants_installed_at_index ON tenants(installed_at);
