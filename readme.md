# Velora - Wedding Guest Management System

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)
![Frontend](https://img.shields.io/badge/Frontend-HTML%20%7C%20CSS%20%7C%20JavaScript-E34F26)
![Security](https://img.shields.io/badge/Security-CSRF%20%7C%20Prepared%20Statements-0A7F5A)
![Status](https://img.shields.io/badge/Status-In%20Development-F59E0B)
![License](https://img.shields.io/badge/License-Apache%202.0-green)

## Overview

Velora is a PHP + MySQL web application with a custom authentication flow for a wedding guest management platform.

The current implementation focuses on:

- user registration and login
- protected dashboard access
- session and CSRF protection
- basic role handling (user/admin)

## Current Feature Set

- Sign up flow with:
  - name, email, country code, phone, role
  - optional admin key when role is admin
  - terms acceptance
  - math captcha validation
- Sign in flow with secure session login
- Password hashing with `password_hash()` and verification with `password_verify()`
- Legacy plain-text password auto-upgrade to hashed password at login
- CSRF token generation and validation for POST authentication requests
- Session-protected dashboard routing
- Logout with session + cookie cleanup
- JSON auth utility endpoints for frontend checks

## Tech Stack

- Frontend: HTML, CSS, vanilla JavaScript
- Backend: PHP (procedural)
- Database: MySQL (MySQLi)

## Project Structure

- `index.html`: public landing page
- `signin.html`: login page and frontend auth helpers
- `signup.html`: registration page with validations and captcha
- `dashboard.php`: protected route that serves `dashboard.html`
- `dashboard.html`: dashboard UI
- `home.php`: route helper (redirects by auth state)
- `login-validation.php`: login/registration backend logic
- `csrf-token.php`: JSON endpoint that returns session CSRF token
- `auth-status.php`: JSON endpoint that returns current auth status
- `logout.php`: logout handler
- `config.php`: MySQL connection config
- `privacy-policy.html`: static legal/privacy page

## Prerequisites

- PHP 7.4+ recommended
- MySQL 5.7+ (or compatible)
- Web server:
  - Apache (XAMPP/WAMP), or
  - PHP built-in server for local development

## Gmail SMTP Setup

This project now includes PHPMailer-based SMTP support:

- `smtp-config.php`: SMTP configuration loader
- `mailer.php`: reusable email sender function
- `smtp-test.php`: CLI test script

### 1. Gmail account preparation

1. Enable 2-Step Verification on your Gmail account.
2. Create an App Password (Google Account > Security > App passwords).
3. Use the generated 16-character app password for SMTP.

### 2. Configure SMTP credentials

Edit `smtp-config.php` defaults or set these environment variables:

- `SMTP_HOST` (default: `smtp.gmail.com`)
- `SMTP_PORT` (default: `587`)
- `SMTP_ENCRYPTION` (default: `tls`)
- `SMTP_USERNAME` (your Gmail address)
- `SMTP_PASSWORD` (your Gmail app password)
- `SMTP_FROM_EMAIL` (usually same as username)
- `SMTP_FROM_NAME` (default: `Velora`)

### 3. Send a test email

From the project folder:

```bash
php smtp-test.php your-email@example.com
```

If correctly configured, you should see success output and receive the test email.

## Quick Start (Windows)

1. Place this project in your web root (for example XAMPP `htdocs`) or keep it in any folder for PHP built-in server.
2. Create the database table shown below.
3. Update database credentials in `config.php`.
4. Start the app:

If using PHP built-in server from the project folder:

```bash
php -S localhost:8000
```

Then open:

```text
http://localhost:8000/index.html
```

## Database Schema

The backend expects a table named `registration` with these columns:

```sql
CREATE TABLE registration (
	id INT NOT NULL AUTO_INCREMENT,
	name VARCHAR(120) NOT NULL,
	email VARCHAR(190) NOT NULL,
	countryCode VARCHAR(10) NOT NULL,
	phone VARCHAR(20) NOT NULL,
	role VARCHAR(20) NOT NULL,
	adminKey VARCHAR(190) NULL,
	password VARCHAR(255) NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_registration_email (email),
	UNIQUE KEY uq_registration_phone (phone),
	UNIQUE KEY uq_registration_adminKey (adminKey)
);
```

Notes:

- `adminKey` can be `NULL` for non-admin users.
- Unique constraints are important because backend duplicate handling relies on MySQL duplicate-key errors.

## Authentication Flow

1. Frontend fetches CSRF token from `csrf-token.php`.
2. User submits sign in or sign up form to `login-validation.php`.
3. Backend validates CSRF token and request payload.
4. On login success:
   - session ID is regenerated
   - session fields are set
   - user is redirected to `dashboard.php`
5. `dashboard.php` denies unauthenticated users and redirects to `index.html`.

## Security Implemented

- CSRF token generation with `random_bytes(32)`
- CSRF comparison with `hash_equals()`
- Prepared statements for DB queries
- Password hashing and verification using native PHP APIs
- Session regeneration after successful login
- Sanitization and validation for input fields
- Logout clears session storage and session cookie

## Endpoints

- `GET /auth-status.php`
  - Response: `{ "loggedIn": boolean, "name": string }`
- `GET /csrf-token.php`
  - Response: `{ "csrfToken": string }`
- `POST /login-validation.php`
  - Handles both login and registration based on form payload

## Configuration

Database settings are currently hardcoded in `config.php`.

For production, prefer environment-based secrets management instead of committing credentials.

## Known Gaps

- No password reset backend flow yet
- No email verification flow
- No migration tooling included (SQL is manual)

## License

See `LICENSE`.
