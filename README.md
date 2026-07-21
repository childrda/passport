# LCPS Teacher Student Password Reset

Laravel 12 + Filament application for Louisa County Public Schools. Teachers
reset Google Workspace passwords for students in their Google Classroom courses.

## Documentation

- [Architecture](docs/architecture.md)
- [Deployment](docs/deployment.md)
- [Testing](docs/testing.md)
- Build prompt: `prompts/main.md`

## Requirements

- PHP 8.2+ with `intl`, `zip`, `openssl`, `pdo_mysql`
- Composer
- MySQL 8+
- HTTPS in production (required for Google OAuth)

## Quick start (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
# Start MySQL; create database matching DB_DATABASE
php artisan migrate --seed
php artisan serve
```

Panel: `/admin`

Seeded local users (password: `password`):

| Email | Role |
|-------|------|
| `admin@{STAFF_DOMAIN}` | System Administrator |
| `teacher@{STAFF_DOMAIN}` | Teacher |
| `auditor@{STAFF_DOMAIN}` | Auditor |

Default drivers use fixtures (`CLASSROOM_DRIVER=mock`, `DIRECTORY_DRIVER=mock`).

## Production

See [docs/deployment.md](docs/deployment.md) for Google OAuth, service-account
domain-wide delegation, environment variables, and go-live checks.

**Prerequisite:** students must sign in to Google directly (not Clever/ClassLink/SAML),
or `changePasswordAtNextLogin` will not apply at their real login.

## Tests

```bash
php artisan test
```

See [docs/testing.md](docs/testing.md) for the `main.md` requirements checklist mapping.

## Status

All nine build phases are complete.
