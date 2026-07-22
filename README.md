# LCPS Teacher Student Password Reset

Laravel 12 + Filament app for Louisa County Public Schools. Teachers sign in with
LCPS Google, see their Classroom courses, and reset student Workspace passwords.

**Before you go live:** students must sign in to Google **directly** (not Clever,
ClassLink, or SAML SSO). If they use a third-party login, `changePasswordAtNextLogin`
will not force a password change at their real login.

## Documentation

| Doc | Purpose |
|-----|---------|
| This README | Full Ubuntu Server + Apache install |
| [docs/deployment.md](docs/deployment.md) | Env vars, security, operations |
| [docs/architecture.md](docs/architecture.md) | How the app is structured |
| [docs/testing.md](docs/testing.md) | Test checklist mapping |

---

## Full install: Ubuntu Server + Apache

These steps assume a **clean Ubuntu Server** (22.04 or 24.04 LTS) with a public
hostname and HTTPS. Replace placeholders like `password-reset.example.org` with
your real values.

You will need:

- Root or `sudo` access
- A DNS name pointing at the server
- Ability to create OAuth and service-account credentials in Google Cloud
- Ability to approve domain-wide delegation in Google Workspace Admin

### Step 1 — Update the server

```bash
sudo apt update
sudo apt upgrade -y
```

### Step 2 — Install Git, Apache, MySQL, and PHP

```bash
sudo apt install -y \
  git \
  apache2 \
  mysql-server \
  unzip \
  curl \
  ca-certificates \
  software-properties-common
```

#### PHP 8.2+ (required)

**Ubuntu 24.04** usually has PHP 8.3 already:

```bash
sudo apt install -y \
  php \
  php-cli \
  php-common \
  php-mysql \
  php-xml \
  php-mbstring \
  php-curl \
  php-zip \
  php-intl \
  php-bcmath \
  php-gd \
  libapache2-mod-php
```

**Ubuntu 22.04** (default PHP is too old) — use Ondřej’s PPA first:

```bash
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y \
  php8.3 \
  php8.3-cli \
  php8.3-common \
  php8.3-mysql \
  php8.3-xml \
  php8.3-mbstring \
  php8.3-curl \
  php8.3-zip \
  php8.3-intl \
  php8.3-bcmath \
  php8.3-gd \
  libapache2-mod-php8.3
```

Confirm:

```bash
php -v
# Must be 8.2 or newer
php -m | grep -E 'intl|zip|openssl|pdo_mysql'
```

Enable Apache modules:

```bash
sudo a2enmod rewrite headers ssl
sudo systemctl enable --now apache2
```

### Step 3 — Install Composer

```bash
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

### Step 4 — Secure MySQL and create the database

```bash
sudo mysql_secure_installation
```

Then create the app database and user:

```bash
sudo mysql
```

In the MySQL prompt:

```sql
CREATE DATABASE passport CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'passport'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON passport.* TO 'passport'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Save that password — you will put it in `.env`.

### Step 5 — Clone the application

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone https://github.com/childrda/passport.git passport
sudo chown -R www-data:www-data /var/www/passport
sudo -u www-data bash -c 'cd /var/www/passport && composer install --no-dev --optimize-autoloader'
```

If `composer install` fails on permissions as `www-data`, run:

```bash
cd /var/www/passport
sudo composer install --no-dev --optimize-autoloader
sudo chown -R www-data:www-data /var/www/passport
```

### Step 6 — Configure the environment

```bash
cd /var/www/passport
sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate
sudo nano /var/www/passport/.env
```

Set at least these values:

```dotenv
APP_NAME="LCPS Password Reset"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://password-reset.example.org

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=passport
DB_USERNAME=passport
DB_PASSWORD=choose-a-strong-password

STAFF_DOMAIN=lcps.k12.va.us
STUDENT_DOMAIN=k12louisa.org

# Leave drivers on mock until Google is configured (Step 10–11)
CLASSROOM_DRIVER=mock
DIRECTORY_DRIVER=mock

GOOGLE_OAUTH_CLIENT_ID=
GOOGLE_OAUTH_CLIENT_SECRET=
GOOGLE_OAUTH_REDIRECT_URI="${APP_URL}/auth/google/callback"

GOOGLE_SERVICE_ACCOUNT_CREDENTIALS=
GOOGLE_IMPERSONATED_ADMIN=
```

Run migrations and prepare Laravel:

```bash
cd /var/www/passport
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --force
sudo -u www-data php artisan filament:assets
sudo -u www-data php artisan storage:link
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

> **Production note:** `--seed` creates sample users with password `password`
> (`admin@`, `teacher@`, `auditor@` on `STAFF_DOMAIN`). After the first real
> System Administrator signs in with Google, remove or disable those accounts.

Fix ownership for writable dirs:

```bash
sudo chown -R www-data:www-data /var/www/passport/storage /var/www/passport/bootstrap/cache
sudo find /var/www/passport/storage /var/www/passport/bootstrap/cache -type d -exec chmod 775 {} \;
```

### Step 7 — Configure Apache

Create a site config:

```bash
sudo nano /etc/apache2/sites-available/passport.conf
```

Paste (adjust ServerName and paths):

```apache
<VirtualHost *:80>
    ServerName password-reset.example.org
    DocumentRoot /var/www/passport/public

    <Directory /var/www/passport/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/passport-error.log
    CustomLog ${APACHE_LOG_DIR}/passport-access.log combined
</VirtualHost>
```

Enable the site and disable the default if needed:

```bash
sudo a2ensite passport.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### Step 8 — Enable HTTPS (required for Google sign-in)

Install Certbot and get a certificate:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d password-reset.example.org
```

Confirm `APP_URL` in `.env` uses `https://`, then:

```bash
cd /var/www/passport
sudo -u www-data php artisan config:cache
```

### Step 9 — Smoke-test the site

Open:

```text
https://password-reset.example.org/admin/login
```

You should see the Filament login page. With `CLASSROOM_DRIVER=mock` you can
still log in with a seeded user (email/password) for a local-style check. For
production teachers, use **Sign in with Google** after Step 10.

---

## Connect Google Workspace

You need **two** Google integrations:

1. **OAuth (teacher sign-in + Classroom)** — each teacher authorizes the app
2. **Service account with domain-wide delegation (Directory)** — the app resets
   student passwords as an impersonated admin

### Step 10 — Google Cloud OAuth (teacher login + Classroom)

1. Open [Google Cloud Console](https://console.cloud.google.com/) and create or
   select a project (for example `lcps-password-reset`).
2. Enable APIs:
   - **Google Classroom API**
   - **Admin SDK API** (needed for Directory in Step 11)
3. **APIs & Services → OAuth consent screen**
   - User type: **Internal** (Workspace) if available, otherwise External with
     staff-domain users only
   - App name: `LCPS Password Reset`
   - Support email: your IT contact
4. **APIs & Services → Credentials → Create credentials → OAuth client ID**
   - Application type: **Web application**
   - Name: `Passport web`
   - Authorized redirect URI (must match exactly):

     ```text
     https://password-reset.example.org/auth/google/callback
     ```

5. Copy the **Client ID** and **Client secret** into `.env`:

```dotenv
GOOGLE_OAUTH_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_OAUTH_CLIENT_SECRET=your-client-secret
GOOGLE_OAUTH_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

Scopes the app requests (teachers approve these on first sign-in):

- `openid`, `profile`, `email`
- `https://www.googleapis.com/auth/classroom.courses.readonly`
- `https://www.googleapis.com/auth/classroom.rosters.readonly`
- `https://www.googleapis.com/auth/classroom.profile.emails`

Only accounts on `STAFF_DOMAIN` are allowed to sign in. Signing in does **not**
grant Teacher or password-reset access — a System Administrator must assign a
role and enable **Can reset student passwords** in Users.

### Step 11 — Service account + domain-wide delegation (password reset)

1. In the same Google Cloud project:
   **IAM & Admin → Service Accounts → Create service account**
   - Name: `passport-directory`
   - No special GCP project roles are required for Workspace DWD
2. Open the service account → **Keys → Add key → Create new key → JSON**
3. Copy the JSON file to the server **outside the web root**, for example:

```bash
sudo mkdir -p /etc/passport
sudo nano /etc/passport/sa-password-reset.json
# paste the JSON, save
sudo chown root:www-data /etc/passport/sa-password-reset.json
sudo chmod 640 /etc/passport/sa-password-reset.json
```

4. Note the service account **Client ID** (numeric) from the service account
   details page (not the email alone).
5. In [Google Workspace Admin](https://admin.google.com/):
   **Security → Access and data control → API controls → Domain-wide delegation
   → Manage Domain Wide Delegation → Add new**
   - Client ID: the numeric client ID from step 4
   - OAuth scopes (one scope only):

     ```text
     https://www.googleapis.com/auth/admin.directory.user
     ```

6. Choose a Workspace admin (or delegated admin) who can reset passwords for
   students on `STUDENT_DOMAIN`. Put that email and the JSON path in `.env`:

```dotenv
GOOGLE_SERVICE_ACCOUNT_CREDENTIALS=/etc/passport/sa-password-reset.json
GOOGLE_IMPERSONATED_ADMIN=workspace-admin@lcps.k12.va.us
```

7. Switch drivers to live Google APIs:

```dotenv
CLASSROOM_DRIVER=google
DIRECTORY_DRIVER=google
```

8. Reload Laravel config:

```bash
cd /var/www/passport
sudo -u www-data php artisan config:cache
```

### Step 12 — Verify Google end-to-end

1. Open `/admin/login` → **Sign in with Google** with a real staff account.
2. Confirm **My Classes** shows live Classroom courses.
3. As System Administrator, open **Integration status** and confirm checks are OK.
4. Reset a **test** student (non-production OU if possible) and confirm:
   - temporary password appears once in the modal
   - next Google login forces a password change
   - an audit log success row is created

Checklist:

- [ ] OAuth redirect URI matches production HTTPS URL
- [ ] Classroom readonly scopes work for a teacher
- [ ] Service-account JSON is readable by `www-data` and not in git
- [ ] Domain-wide delegation includes `admin.directory.user`
- [ ] Impersonated admin can manage `STUDENT_DOMAIN` users
- [ ] Students authenticate to Google directly (not Clever/ClassLink/SAML)
- [ ] `CLASSROOM_DRIVER=google` and `DIRECTORY_DRIVER=google`
- [ ] `APP_DEBUG=false`

---

## Roles

| Role | Access |
|------|--------|
| Teacher | My Classes, roster, password reset |
| System Administrator | Users, roles, Integration status, Audit logs, classes |
| Auditor | Audit logs only |

Panel URL: `/admin`

---

## Local development (optional)

On a developer machine (XAMPP, Homestead, etc.):

```bash
git clone https://github.com/childrda/passport.git
cd passport
composer install
cp .env.example .env
php artisan key:generate
# Create MySQL database matching DB_* in .env
php artisan migrate --seed
php artisan serve
```

Default drivers are mock fixtures. Seeded users (password: `password`):

| Email | Role |
|-------|------|
| `admin@{STAFF_DOMAIN}` | System Administrator |
| `teacher@{STAFF_DOMAIN}` | Teacher |
| `auditor@{STAFF_DOMAIN}` | Auditor |

```bash
php artisan test
```

---

## After install: common operations

| Task | Command |
|------|---------|
| Clear config after `.env` edits | `sudo -u www-data php artisan config:cache` |
| Rotate service-account key | Replace JSON file; keep path in `.env` |
| View audit trail | Filament → Audit logs |
| Integration readiness | Filament → Integration status |

More detail: [docs/deployment.md](docs/deployment.md).

## Status

All nine build phases from `prompts/main.md` are complete.
