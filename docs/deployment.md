# Deployment guide

LCPS Teacher Student Password Reset — production and staging deployment.

## Prerequisite (confirm before go-live)

Students must authenticate to Google **directly**, not through a third-party IdP
(Clever, ClassLink, SAML/OIDC SSO). If login is delegated, `changePasswordAtNextLogin`
will not force a password change at the student’s actual login, and this approach
must be reconsidered.

**Two-tenant Google setup** (staff OAuth/Classroom vs student Directory DWD) is documented
in [student-tenant.md](student-tenant.md). Complete that guide’s preflight checklist
before setting `DIRECTORY_DRIVER=google`.

## Stack

| Component | Requirement |
|-----------|-------------|
| PHP | 8.3+ with `intl`, `zip`, `openssl`, `pdo_mysql` |
| Composer | 2.x |
| Node.js | 20+ (Vite build for Filament custom theme) |
| Database | MySQL 8+ (utf8mb4) |
| Web server | Apache or Nginx; HTTPS required for Google OAuth |
| App | Laravel 12 + Filament 4 |

## 1. Application install

For a **clean Ubuntu Server + Apache** walkthrough (apt packages, MySQL user,
vhost, Certbot, and Google Workspace), use the root [README.md](../README.md).

Condensed app steps:

```bash
git clone https://github.com/childrda/passport.git passport
cd passport
composer install --no-dev --optimize-autoloader
npm ci
npm run build                 # writes public/build (required for custom theme)
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

After `git pull`, re-run `npm ci && npm run build` whenever theme/CSS sources change.

Point the web root at `public/`. Example Apache vhost document root:
`/var/www/passport/public`.
## 2. Environment variables

All deployment values come from `.env` via `config/reset.php`. Never hardcode
domains, OAuth secrets, or service-account paths in application code.

### Application / database

```
APP_NAME=Passport
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

### Domains (two Workspace tenants)

Full operator guide: [student-tenant.md](student-tenant.md).

```
STAFF_DOMAIN=lcps.k12.va.us
STUDENT_DOMAIN=k12louisa.org
```

LCPS runs **two separate Google Workspace tenants**:

| Tenant | Domain | Used for |
|--------|--------|----------|
| Staff | `STAFF_DOMAIN` | Teacher OAuth sign-in + Google Classroom |
| Student | `STUDENT_DOMAIN` | Student accounts + Admin SDK Directory password reset |

Students appear in staff-tenant Classroom courses as **external participants**.
Directory discovery uses the live Classroom roster **email** (`@STUDENT_DOMAIN`)
as the Admin SDK `userKey`, then resets by the immutable Directory user ID.

- Only `STAFF_DOMAIN` accounts may sign in.
- Only canonical **primary** emails on `STUDENT_DOMAIN` may be reset.

### Google OAuth (staff tenant — teacher sign-in + Classroom)

```
GOOGLE_OAUTH_CLIENT_ID=...
GOOGLE_OAUTH_CLIENT_SECRET=...
GOOGLE_OAUTH_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

In Google Cloud Console (OAuth 2.0 Web client) for the **staff** project:

1. Authorized redirect URI must match `GOOGLE_OAUTH_REDIRECT_URI` exactly.
2. OAuth consent / Workspace policies must allow the staff domain.
3. Scopes requested by the app (see `config/reset.php`):
   - `openid`, `profile`, `email`
   - `https://www.googleapis.com/auth/classroom.courses.readonly`
   - `https://www.googleapis.com/auth/classroom.rosters.readonly`
   - `https://www.googleapis.com/auth/classroom.profile.emails`

Teachers must use **Sign in with Google** (not password login) so Classroom tokens exist.

**Before enabling live Directory:** confirm Classroom returns `emailAddress` for
external (`@STUDENT_DOMAIN`) roster members with `classroom.profile.emails` granted.
If those emails are blank, do not enable `DIRECTORY_DRIVER=google`.

### Google service account (student tenant — Directory password reset)

Domain-wide delegation is **per tenant**. The Directory client must be authorized in
the **student** Workspace (`STUDENT_DOMAIN`), not the staff tenant.

```
GOOGLE_DIRECTORY_CREDENTIALS=/secure/path/sa-student-directory.json
GOOGLE_DIRECTORY_IMPERSONATED_ADMIN=admin@k12louisa.org
```

(Legacy names `GOOGLE_SERVICE_ACCOUNT_CREDENTIALS` / `GOOGLE_IMPERSONATED_ADMIN` are
removed — use the `GOOGLE_DIRECTORY_*` keys only.)

1. Create a Google Cloud service account (often in a project tied to the student tenant)
   and download the JSON key.
2. Store the key **outside the web root and outside git**.
3. In **student-tenant** Google Workspace Admin → Security → API controls →
   Domain-wide delegation, authorize the service account client ID with scope:
   - `https://www.googleapis.com/auth/admin.directory.user`
4. `GOOGLE_DIRECTORY_IMPERSONATED_ADMIN` must be an admin that **exists in the student
   tenant** (on `STUDENT_DOMAIN`). An address on the staff domain is rejected at startup.

### Drivers

```
CLASSROOM_DRIVER=google
DIRECTORY_DRIVER=google
```

Use `mock` only for local development without live Google APIs.
Keep `DIRECTORY_DRIVER=mock` until the external roster-email prerequisite is verified.
### Temporary passwords

```
TEMP_PASSWORD_LENGTH=10
TEMP_PASSWORD_ALPHABET=ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789
```

Invalid alphabet/length configuration is rejected at application boot.

## 3. Google Cloud / Workspace checklist

- [ ] Staff-tenant OAuth client created; redirect URI matches production `APP_URL`
- [ ] Classroom scopes include `classroom.profile.emails`; external roster emails verified
- [ ] Student-tenant service account created; JSON key stored securely
- [ ] Student-tenant domain-wide delegation enabled with `admin.directory.user`
- [ ] `GOOGLE_DIRECTORY_IMPERSONATED_ADMIN` is on `STUDENT_DOMAIN` (not staff)
- [ ] Confirmed students sign in to Google directly (not via third-party IdP)
- [ ] `CLASSROOM_DRIVER=google` and `DIRECTORY_DRIVER=google` only after email prerequisite

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

- Temporary passwords are shown once via a one-shot browser event (not Livewire
  mounted-action arguments) and must never appear in DB, session, cache, logs,
  flash, queues, or Livewire properties.
- Audit logs are append-only at the Eloquent model layer (updates/deletes throw)
  and denied by policy. Production should also enforce append-only with database
  grants/triggers (app DB user: INSERT + SELECT only on `audit_logs`).
- Password reset uses `Cache::lock` keyed by Directory user ID. Production must
  use a shared lock-capable cache (Redis), not `array` or a single-node `file`
  driver behind multiple app servers.
- OAuth secrets, service-account JSON, and domain settings are **not** editable in Filament.
- Keep `APP_DEBUG=false` in production.
- Prefer HTTPS only; Google OAuth requires a secure redirect URI in production.
- Staff Google sign-in does **not** auto-grant Teacher or reset access — provision
  roles and `reset_access_enabled` in Filament → Users.
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
| Rebuild Filament theme | `npm ci && npm run build` |
| View audit trail | Filament → Audit logs |
| Integration readiness | Filament → Integration status |
| Rotate SA key | Replace JSON file; update path if needed; no code change |
| Switch back to fixtures | `CLASSROOM_DRIVER=mock`, `DIRECTORY_DRIVER=mock` |

## 8. Related docs

- [Two-tenant Google setup](student-tenant.md)
- [Architecture](architecture.md)
- Build requirements: `prompts/main.md`
- Environment template: `.env.example`
