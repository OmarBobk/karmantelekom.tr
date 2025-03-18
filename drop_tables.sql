SET FOREIGN_KEY_CHECKS = 0;

-- Drop all tables
DROP TABLE IF EXISTS cache;
DROP TABLE IF EXISTS cache_locks;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS currencies;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS failed_jobs;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS job_batches;
DROP TABLE IF EXISTS migrations;
DROP TABLE IF EXISTS model_has_permissions;
DROP TABLE IF EXISTS model_has_roles;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS personal_access_tokens;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS product_prices;
DROP TABLE IF EXISTS product_tags;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS role_has_permissions;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS section_products;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS team_invitations;
DROP TABLE IF EXISTS team_user;
DROP TABLE IF EXISTS users;

-- Create a fresh migrations table
CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL,
    PRIMARY KEY (id)
);

-- Ensure the table is empty
TRUNCATE migrations;

SET FOREIGN_KEY_CHECKS = 1; 