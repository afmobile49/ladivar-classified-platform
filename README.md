# Ladivar Classified Platform

A lightweight bilingual classified ads platform built with vanilla PHP and SQLite.

## Features

- Persian / English interface
- Public classified listings with category pages
- Admin moderation workflow
- Listing images and side ads
- Simple REST API under `/api/v1`
- Search, filtering, and homepage pagination
- Apache rewrite-based clean URLs

## Tech Stack

- PHP
- SQLite
- HTML / CSS
- Apache `.htaccess`

## Project Structure

- `index.php` — homepage listings and pagination
- `category.php` — listings by category
- `listing.php` — single listing page
- `post.php` — submit a listing
- `admin/` — admin dashboard and moderation tools
- `api/v1/` — REST API endpoints
- `inc/` — config, helpers, shared functions
- `uploads/` — runtime uploads (ignored in Git)

## API Examples

- `GET /api/v1/categories`
- `GET /api/v1/listings`
- `GET /api/v1/listings?q=car&category=services&city=los`
- `GET /api/v1/listings/12`
- `GET /api/v1/categories/services/listings`

## Local Setup

1. Clone the repository.
2. Copy `inc/config.example.php` to `inc/config.php` if needed.
3. Adjust `BASE_URL`, `DB_PATH`, and admin credentials.
4. Make sure Apache rewrite is enabled.
5. Create an empty SQLite database file named `data.sqlite` in the project root, or point `DB_PATH` to another location.
6. Open the site in your local PHP/Apache environment.

The app auto-creates required tables on first run.

## GitHub Safety Notes

This repository is prepared for public GitHub upload:

- Real database content is excluded.
- Uploaded images are excluded.
- Runtime logs are excluded.
- Admin password is replaced with a safe placeholder.

## Recommended Repository Names

- `ladivar-classified-platform`
- `php-classified-ads-platform`

## Before Your First Local Run

Change the default admin password in `inc/config.php` or set environment variables:

- `LADIVAR_SITE_NAME`
- `LADIVAR_BASE_URL`
- `LADIVAR_DB_PATH`
- `LADIVAR_ADMIN_USER`
- `LADIVAR_ADMIN_PASS`

## Author

Afshin
