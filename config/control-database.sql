CREATE TABLE tenants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    domain VARCHAR(253) NOT NULL,
    database_host VARCHAR(255) NOT NULL,
    database_port SMALLINT UNSIGNED NOT NULL DEFAULT 3306,
    database_name VARCHAR(64) NOT NULL,
    database_user VARCHAR(128) NOT NULL,
    encrypted_database_password VARCHAR(2048) NOT NULL,
    status ENUM('active', 'suspended', 'disabled') NOT NULL DEFAULT 'active',
    plan VARCHAR(64) NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY tenants_domain_unique (domain),
    KEY tenants_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
