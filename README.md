# Cattle Project - Full Setup Guide

This repository contains a Laravel 12 + Inertia/Vue cattle management system.

Use this guide to set up a full local environment from scratch.

## Tech Stack

- PHP `^8.2`
- Laravel `^12.0`
- Node.js `^22.16.0` + Vite + Vue 3
- MariaDB `^11.5.2`

## System Requirements

Install the following first:

- PHP 8.2+ with extensions: `mbstring`, `openssl`, `pdo`, `pdo_sqlite` (or `pdo_mysql`), `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`
Composer version 2.8.10
- Node.js 20+ and npm

##  Install dependencies

```bash
composer install
npm install
```

## 3) Environment setup

Copy the environment file:

```bash
cp .env.example .env
```

If you are on Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Generate app key:

```bash
php artisan key:generate
```

## 4) Database setup

### Option A (recommended for fastest local setup): SQLite

This project defaults to SQLite in `.env.example`.

1. Ensure `database/database.sqlite` exists (it is already in this repo).
2. Keep these values in `.env`:

```dotenv
DB_CONNECTION=MySQL
```

3. Run migrations:

```bash
php artisan migrate
```

### Option B: MySQL / MariaDB (WAMP, XAMPP, etc.)

1. Create a database, for example `cattlesystem`.
2. Update `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306/3307
DB_DATABASE=cattlesystem
DB_USERNAME=root
DB_PASSWORD=
```

3. Run migrations:

```bash
php artisan migrate
```

## 5) Seed initial data

Run default seeder (includes initial users/roles and pasture data):

```bash
php artisan db:seed
```

Optional sample data:

```bash
php artisan db:seed --class=CalvingRecordsSeeder
```

## 6) Storage symlink

Required for uploaded files (profile photos, workflow docs, endorsements):

```bash
php artisan storage:link
```

## 7) Build frontend assets

Development mode:

```bash
npm run dev
```

Production build:

```bash
npm run build
```

## 8) Run the app

### Simple run (2 terminals)

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

### Full dev run (single command)

This starts Laravel server + queue listener + log tail + Vite:

```bash
composer run dev
```

Open:

- `http://127.0.0.1:8000`

The root route redirects to `/login`.

## 9) login accounts

- Admin
  - Email: `admin@sawitkinabalu.com.my`
  - Password: `admin123`
- Sr. Assistant Livestock
  - Email: `srassistant.livestock@sawitkinabalu.com.my`
  - Password: `admin123`
- Sr. Assistant Security
  - Email: `srassistant.security@sawitkinabalu.com.my`
  - Password: `admin123`
- Supervisor Livestock
  - Email: `supervisor.livestock@sawitkinabalu.com.my`
  - Password: `admin123`
- Penyelia Security
  - Email: `penyelia.security@sawitkinabalu.com.my`
  - Password: `admin123`
- Livestock Manager / OIC
  - Email: `livestock.manager@sawitkinabalu.com.my`
  - Password: `admin123`

## 10) Useful commands

- Run tests:

```bash
php artisan test
```

- Clear caches:

```bash
php artisan optimize:clear
```

- Rebuild database from scratch:

```bash
php artisan migrate:fresh --seed
```

## One-command bootstrap

You can run the Composer setup script defined in `composer.json`:

```bash
composer run setup
```

What it does:

- Installs PHP dependencies
- Creates `.env` if missing
- Generates app key
- Runs migrations
- Installs npm packages
- Builds frontend assets

## Troubleshooting

- `Class ... not found` or stale config:
  - Run `composer dump-autoload` and `php artisan optimize:clear`
- `Vite manifest not found`:
  - Run `npm run build` or keep `npm run dev` running
- Upload/download files not accessible:
  - Ensure `php artisan storage:link` was executed
- SQL or migration issues:
  - Verify `.env` DB values and re-run `php artisan migrate`
- Queue-related features not processing:
  - Run `php artisan queue:listen --tries=1` (or use `composer run dev`)

## Notes

- If you need module-specific usage details for calving workflows, see `CALVING_MODULE_README.md`.
- Do not commit your local `.env` file or secrets.
