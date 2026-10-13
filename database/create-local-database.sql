-- LOCAL MySQL setup only, using an account allowed to create databases.
-- cPanel users should create the database through cPanel instead; cPanel adds
-- an account prefix and manages database users and privileges separately.
CREATE DATABASE booking_saas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Select booking_saas in your SQL client before importing
-- database/migrations/001_customer_administration.sql.
