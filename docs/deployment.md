# Deployment guide

LCPS Teacher Student Password Reset — production and staging deployment.

## Prerequisite (confirm before go-live)

Students must authenticate to Google **directly**, not through a third-party IdP
(Clever, ClassLink, SAML/OIDC SSO). If login is delegated, `changePasswordAtNextLogin`
will not force a password change at the student’s actual login, and this approach
must be reconsidered.

## Stack

| Component | Requirement |
|-----------|-------------|
| PHP | 8.2+ with `intl`, `zip`, `openssl`, `pdo_mysql` |
| Composer | 2.x |
| Database | MySQL 8+ (utf8mb4) |
| Web server | Apache or Nginx; HTTPS required for Google OAuth |
| App | Laravel 12 + Filament 4 |

## 1. Application install

```bash
git clone <repo-url> passport
cd passport
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Create the MySQL database, then set `.env` (see Environment variables below).

```bash
php artisan migrate --force
php artisan db:seed --force   # optional: local/dev roles + sample users only
php artisan filament:assets
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Point the web root at `public/`. Example Apache vhost document root:
`/var/www/passport/public`.

## 2. Environment variables

All deployment values come from `.env` via `config/reset.php`. Never hardcode
domains, OAuth secrets, or service-account paths in application code.

### Application / database

```
APP_NAME="LCPS Password Reset"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://password-reset.example.org

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=passport
DB_USERNAME=...
DB_PASSWORD=...
```

### Domains

```
STAFF_DOMAIN=lcps.k12.va.us
STUDENT_DOMAIN=k12louisa.org
```

- Only `STAFF_DOMAIN` accounts may sign in.
- Only canonical primary emails on `STUDENT_DOMAIN` may be reset.

### Google OAuth (teacher sign-in + Classroom)

```
GOOGLE_OAUTH_CLIENT_ID=...
GOOGLE_OAUTH_CLIENT_SECRET=...
GOOGLE_OAUTH_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

In Google Cloud Console (OAuth 2.0 Web client):

1. Authorized redirect URI must match `GOOGLE_OAUTH_REDIRECT_URI` exactly.
2. OAuth consent / Workspace policies must allow the staff domain.
3. Scopes requested by the app:
   - `openid`, `profile`, `email`
   - `https://www.googleapis.com/auth/classroom.courses.readonly`
   - `https://www.googleapis.com/auth/classroom.rosters.readonly`

Teachers must use **Sign in with Google** (not password login) so Classroom tokens exist.

### Google service account (Directory password reset)

```
GOOGLE_SERVICE_ACCOUNT_CREDENTIALS=/secure/path/sa-password-reset.json
GOOGLE_IMPERSONATED_ADMIN=workspace-admin@lcps.k12.va.us
```

1. Create a Google Cloud service account and download the JSON key.
2. Store the key **outside the web root and outside git** (paths under
   `storage/app/google/*.json` are gitignored as a convenience).
3. In Google Workspace Admin → Security → API controls → Domain-wide delegation,
   authorize the service account client ID with scope:
   - `https://www.googleapis.com/auth/admin.directory.user`
4. `GOOGLE_IMPERSONATED_ADMIN` must be a Workspace admin (or delegated admin)
   that can reset student passwords.

### Drivers

```
CLASSROOM_DRIVER=google
DIRECTORY_DRIVER=google
```

Use `mock` only for local development without live Google APIs.

### Temporary passwords

```
TEMP_PASSWORD_LENGTH=10
TEMP_PASSWORD_ALPHABET=ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789
```

Invalid alphabet/length configuration is rejected at application boot.

## 3. Google Cloud / Workspace checklist

- [ ] OAuth client created; redirect URI matches production `APP_URL`
- [ ] Classroom readonly scopes approved for the OAuth client / Workspace
- [ ] Service account created; JSON key stored securely
- [ ] Domain-wide delegation enabled with `admin.directory.user`
- [ ] Impersonated admin can manage student accounts on `STUDENT_DOMAIN`
- [ ] Confirmed students sign in to Google directly (not via third-party IdP)
- [ ] `CLASSROOM_DRIVER=google` and `DIRECTORY_DRIVER=google` in production

## 4. Roles and first users

Seeded roles (via `RoleSeeder`):

| Role | Access |
|------|--------|
| Teacher | My Classes / roster / reset |
| System Administrator | Users, roles, integration status, audit logs, classes |
| Auditor | Audit logs only |

Production tip: create the first System Administrator manually (tinker or a controlled
seed), then manage further users in Filament → **Users**. Do not leave default
`password` accounts enabled in production.

## 5. Security notes

- Temporary passwords are shown once in a non-persistent modal and must never appear
  in DB, session, cache, logs, flash, queues, or Livewire properties.
- Audit logs record attempts without passwords and are read-only in the UI.
- OAuth secrets, service-account JSON, and domain settings are **not** editable in Filament.
- Keep `APP_DEBUG=false` in production.
- Prefer HTTPS only; Google OAuth requires a secure redirect URI in production.

## 6. Verification after deploy

1. Open `/admin/login` → **Sign in with Google** with a staff account.
2. Confirm **My Classes** lists live Classroom courses (`CLASSROOM_DRIVER=google`).
3. As System Administrator, open **Integration status** and confirm all rows are OK.
4. Perform a test reset in a non-production student OU if available; confirm:
   - temporary password displays once
   - `changePasswordAtNextLogin` forces change at next Google login
   - an audit log success row appears
5. Run automated tests in CI or on a staging host:

```bash
php artisan test
```

## 7. Operations

| Task | Command / location |
|------|--------------------|
| Clear caches after env change | `php artisan config:cache` |
| View audit trail | Filament → Audit logs |
| Integration readiness | Filament → Integration status |
| Rotate SA key | Replace JSON file; update path if needed; no code change |
| Switch back to fixtures | `CLASSROOM_DRIVER=mock`, `DIRECTORY_DRIVER=mock` |

## 8. Related docs

- [Architecture](architecture.md)
- Build requirements: `prompts/main.md`
- Environment template: `.env.example`
