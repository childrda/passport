# Build Prompt: Teacher Student Password Reset
You are inside a new laravel 12 install
Create a Laravel 12 application using PHP 8.3+, Filament, MySQL and the
Google APIs.

The application is for Louisa County Public Schools and allows teachers to reset
passwords for students assigned to their Google Classroom classes.

There is a mockup image mockup.png that provides an example of the user interface. It is a visual guide for layout and the happy-path flow only — the written prompt is authoritative for all behavior, security rules, and any screen the image doesn't show (error and failure states in particular). Where the image and the prompt differ, follow the prompt

## Prerequisite to confirm first

Confirm LCPS students authenticate to Google **directly**, not through a third-party
identity provider (Clever, ClassLink, SAML/OIDC SSO). If authentication is delegated to
a third-party IdP, `changePasswordAtNextLogin` will not force a change at the student's
actual login, and this approach must be reconsidered before building the reset feature.

## User workflow

The teacher should be able to:

- Sign in using their LCPS Google Workspace account.
- View the Google Classroom courses they currently teach.
- Select a course.
- View the students currently enrolled in that course.
- Select a student.
- Click Reset Password.
- Confirm the reset.
- See the generated temporary password once.

The application should be simple and easy for teachers to use.

## Configuration (environment variables — nothing hardcoded)

All deployment-specific values must come from `.env` via Laravel `config()`, never
hardcoded in application logic. At minimum:

- `STAFF_DOMAIN` — staff sign-in domain (e.g. `lcps.k12.va.us`)
- `STUDENT_DOMAIN` — student reset domain (e.g. `k12louisa.org`)
- `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET`, `GOOGLE_OAUTH_REDIRECT_URI`
- `GOOGLE_SERVICE_ACCOUNT_CREDENTIALS` — path or reference to the service-account key
  (kept outside the repo)
- `GOOGLE_IMPERSONATED_ADMIN` — the Workspace admin the service account impersonates
- `TEMP_PASSWORD_LENGTH` — default `10`
- `TEMP_PASSWORD_ALPHABET` — allowed characters, defaulting to upper/lower/digits with
  confusing characters (`0 O 1 l I`) excluded

Provide a published `config/` file (e.g. `config/reset.php`) that reads these env
values, and reference config keys everywhere — domain checks, password generation, and
the Google clients must all read from config, not literals. Include a `.env.example`
documenting every key.

## Google domains

Read the two domains from `STAFF_DOMAIN` and `STUDENT_DOMAIN` (see Configuration).

Only allow accounts on the **staff domain** to sign in.

Only allow password resets for canonical Google Workspace accounts whose primary email
domain exactly equals the **student domain**.

Never allow the application to reset a staff-domain account.

## Google Classroom access

Use the signed-in teacher's own Google OAuth token to retrieve:

- Courses the teacher currently teaches
- Students currently enrolled in each course

Do not provide unrestricted student search.

A teacher may only reset a student who is currently enrolled in one of the teacher's
Google Classroom courses.

Immediately before resetting the password, fetch the course roster again from Google
Classroom and verify that:

- The signed-in user is currently a teacher or co-teacher for the selected course.
- The selected student is currently enrolled in that course.

Do not trust a course ID, student ID, or email address submitted by the browser without
server-side verification.

Use the Classroom-provided Google user ID as the student identifier. Resolve that ID
through the Directory API and use the resulting canonical Directory user ID for the
reset. Treat displayed email addresses as informational only.

## Password reset

Use a dedicated Google service account with domain-wide delegation to call the Google
Admin SDK Directory API. The service account must impersonate a designated Google
Workspace administrator.

When the teacher confirms the reset:

1. Verify the live Classroom roster.
2. Retrieve the student's canonical Directory account.
3. Confirm the canonical primary email domain exactly equals the configured
   `STUDENT_DOMAIN`.
4. Generate a cryptographically secure random password of length `TEMP_PASSWORD_LENGTH`
   (default 10) using the configured `TEMP_PASSWORD_ALPHABET`.
5. Reset the student's Google Workspace password.
6. Set `changePasswordAtNextLogin = true`.
7. Display the temporary password to the teacher once.

The password alphabet is configuration-driven (upper/lower/digits, excluding confusing
characters such as `0 O 1 l I` by default).

The generated password must contain at least one uppercase letter, one lowercase
letter, and one number, provided those character groups exist in the configured
alphabet. Randomize the final character order using a cryptographically secure method.

Validate password configuration at startup: reject invalid configuration when the
configured length is too short to satisfy the required character groups, the alphabet
contains duplicate characters, or the required character groups cannot be satisfied
from the configured alphabet.

If the live roster check or the Directory lookup cannot be completed for any reason
(API error or timeout), **deny the reset** — never proceed on unverified authorization.

Do not store the temporary password in the database, session, cache, logs, queues,
audit records, or application telemetry.

Return the temporary password only in the immediate successful response. Do not place it
in a persistent Livewire property, Laravel flash message, session value, notification
record, event payload, exception context, or queued job. Display it in a non-persistent
modal that cannot be reopened after dismissal or page refresh.

## Interface

Use Filament as the long-term teacher and administrator interface.

The teacher dashboard should include:

- My Classes
- Class roster
- Student name and email
- Reset Password action
- Confirmation dialog
- One-time temporary-password display

Keep the teacher interface focused on this workflow.

## Audit logging

Record every reset attempt with: teacher, student, course, date and time, result,
failure reason, IP address.

Never record the temporary password. Audit records should be read-only through the
application.

## Roles

Create these roles:

- **Teacher** — may only view their own classes and students.
- **System Administrator** — may view integration status, manage application users and
  roles, and review audit logs. Deployment secrets, service-account credentials, OAuth
  credentials, and domain settings remain environment-managed and are **not** editable
  through Filament.
- **Auditor** — may only view audit logs.

## Code structure

Keep Filament actions thin. Create dedicated services such as:

- `GoogleClassroomService`
- `GoogleDirectoryService`
- `StudentPasswordResetService`
- `TemporaryPasswordGenerator`
- `AuditService`

The `StudentPasswordResetService` should coordinate the complete reset workflow.

## Testing

Create automated tests confirming that:

- A teacher can see their own classes.
- A teacher can see students enrolled in their classes.
- A teacher cannot access another teacher's roster.
- A teacher cannot reset a student outside their roster.
- Staff-domain accounts cannot be reset.
- The generated password matches the configured length (default 10).
- The password contains only characters from the configured alphabet.
- `changePasswordAtNextLogin` is set to true.
- The temporary password is not stored or logged.
- Google API failures are shown as failures rather than successes.
- Every attempt creates an audit record.

Use mocked Google API clients for automated tests.

## Development phases

Build this application in phases:

1. Laravel and Filament foundation
2. Google teacher authentication
3. Mock classes and rosters
4. Teacher class and student interface
5. Mock password reset
6. Google Classroom API integration
7. Google Directory API integration
8. Audit logging and administration
9. Testing and deployment documentation

Begin with Phase 1 only. Before writing code, summarize the proposed architecture. Stop
after completing Phase 1 and wait for approval before continuing.

Phase 1 includes only Laravel installation, Filament installation, database setup, base
user and role models, configuration files, testing framework, application layout, and
architecture documentation. Do not implement Google OAuth, Classroom API access,
Directory API access, or password-reset functionality during Phase 1.