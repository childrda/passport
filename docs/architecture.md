# Architecture — LCPS Teacher Student Password Reset

## Purpose

Teachers at Louisa County Public Schools sign in with their LCPS Google Workspace
account, view Google Classroom courses they teach, and reset passwords for
students enrolled in those courses. Temporary passwords are shown once and are
never persisted.

## Stack

- Laravel 12 / PHP 8.2+
- Filament 4 (teacher and administrator UI)
- MySQL
- Google OAuth, Classroom API, Admin SDK Directory API (later phases)

## Layering

| Layer | Responsibility |
|-------|----------------|
| Filament panels / actions | Thin UI: classes, rosters, confirmations, one-time password modal |
| Services | Business logic and Google API orchestration |
| Models | Users, roles, audit records |
| Config (`config/reset.php`) | Domains, OAuth, service account, password alphabet/length |

### Services

- `ClassroomService` — `MockGoogleClassroomService` / `GoogleClassroomService`
- `DirectoryService` — `MockGoogleDirectoryService` / `GoogleDirectoryService`
- `StudentPasswordResetService` — coordinates the full reset workflow
- `TemporaryPasswordGenerator` — secure passwords from config (validated at boot)
- `AuditService` — attempt logging without storing passwords (Phase 8)

## Roles

| Role | Access |
|------|--------|
| Teacher | Own classes and students only |
| System Administrator | Users, roles, integration status, audit logs (secrets stay in env) |
| Auditor | Audit logs only |

## Security rules (authoritative)

1. Only `STAFF_DOMAIN` accounts may sign in.
2. Only `STUDENT_DOMAIN` canonical primary emails may be reset.
3. Never reset staff-domain accounts.
4. No unrestricted student search — Classroom roster only.
5. Re-verify live Classroom teacher/student membership immediately before reset.
6. Use Classroom Google user ID → Directory canonical ID; emails are display-only.
7. Deny the reset if roster or Directory checks fail (API error/timeout).
8. Temporary password: immediate response only — not DB, session, cache, logs, Livewire properties, flash, queues, or telemetry.
9. Set `changePasswordAtNextLogin = true` on successful reset.

## Configuration

All deployment values come from `.env` via `config('reset.*')`. See `.env.example`.

## Phase boundaries

| Phase | Scope |
|-------|--------|
| 1 | Laravel, Filament, MySQL, users/roles, config, stubs, docs, test skeleton |
| 2 | Google teacher OAuth, staff-domain gate, user sync + Teacher role |
| 3 | Mock classes and rosters (`ClassroomService` + fixtures) |
| 4 | Teacher class/student Filament UI |
| 5 | Mock password reset + one-time display |
| 6 | Live Google Classroom API |
| 7 | Live Google Directory API + real reset |
| 8 | Audit logging and administration |
| 9 (complete) | Testing checklist + deployment documentation |

## Google authentication (Phase 2)

- Teachers sign in via Google OAuth (`/auth/google/redirect` → callback).
- Only emails whose domain exactly matches `config('reset.staff_domain')` are allowed.
- New staff users are created and assigned the Teacher role; existing roles are preserved.
- OAuth tokens (access + refresh) are stored encrypted for later Classroom API calls.
- Scopes include Classroom course/roster readonly access (used starting Phase 6).
- Filament login shows **Sign in with Google**; local password login remains for seeded accounts.

## Mock Classroom data (Phase 3)

- Inject `App\Contracts\ClassroomService` (bound by `CLASSROOM_DRIVER`).
- `mock` → `MockGoogleClassroomService` + `MockClassroomFixture`
- `google` → `GoogleClassroomService` via `GoogleClassroomApiGateway` (Phase 6)
- Fixture teachers (mock driver):
  - `teacher@{STAFF_DOMAIN}` — Algebra I, Biology
  - `other.teacher@{STAFF_DOMAIN}` — US History
- Students use Classroom Google user IDs; emails are informational and on `STUDENT_DOMAIN`.
- Roster methods return empty / false when the teacher does not teach the course (no cross-teacher access).

## Live Google Classroom (Phase 6)

- `CLASSROOM_DRIVER=google` uses the teacher's stored OAuth access/refresh tokens.
- Lists ACTIVE courses where the user is a teacher (`teacherId=me`).
- Live `courses.teachers` / `courses.students` checks before roster display and reset.
- API failures deny resets (`PasswordResetException::classroomVerificationFailed`) and show UI errors.
- Token refresh persists updated access tokens on the user record.

## Teacher UI (Phase 4)

- **My Classes** (`/admin/classes`) — courses for the signed-in teacher
- **Class roster** (`/admin/classes/{courseId}`) — student name + email
- **Reset Password** action with confirmation dialog
- Confirmation re-checks teacher/course and student enrollment server-side

## Mock password reset (Phase 5)

- `StudentPasswordResetService` coordinates: roster re-check → Directory lookup →
  student-domain gate → generate password → reset with `changePasswordAtNextLogin`
- `TemporaryPasswordGenerator` uses `TEMP_PASSWORD_LENGTH` / `TEMP_PASSWORD_ALPHABET`
- One-time modal shows the password; dismiss/refresh clears it (not a Livewire property,
  session flash, or log field)
- `DIRECTORY_DRIVER=mock` for fixtures; `google` for live Admin SDK (Phase 7)

## Live Google Directory (Phase 7 / Pass 3)

- `DIRECTORY_DRIVER=google` uses a **student-tenant** service account JSON key + DWD
  (`GOOGLE_DIRECTORY_CREDENTIALS` / `GOOGLE_DIRECTORY_IMPERSONATED_ADMIN`).
- Impersonated admin must be on `STUDENT_DOMAIN` (startup-validated).
- Discovers the Directory user from the live Classroom roster email, then resets by
  immutable Directory user ID with `changePasswordAtNextLogin=true`.
- Password is never stored. Directory API failures deny the reset.
- Required scope: `https://www.googleapis.com/auth/admin.directory.user`

## Audit logging and administration (Phase 8)

- Every reset attempt writes an `audit_logs` row: teacher, student, course, timestamp,
  result, failure reason, IP — never the temporary password.
- Audit logs are read-only in Filament.
- **System Administrator:** Users (with roles), Integration status, Audit logs, My Classes
- **Auditor:** Audit logs only
- **Teacher:** My Classes / roster only
- Integration status shows configuration readiness without exposing secrets

## Testing and deployment (Phase 9)

- Requirements checklist tests: `Tests\Feature\RequirementsChecklistTest`
- Testing map: `docs/testing.md`
- Deployment / Google Workspace runbook: `docs/deployment.md`

## Pass two remediation (`prompts/pass2.md`)

- Canonical OAuth scopes in `config/reset.php` (includes `classroom.profile.emails`)
- Temporary password via one-shot JS/Alpine — not Livewire mounted-action state
- Directory confirmed failure vs outcome-unknown (do-not-retry)
- Per-student `Cache::lock` + UI double-submit guard
- Explicit `reset_access_enabled` gate (no auto-Teacher on Google sign-in)
- Append-only audit logs (model + policy) with UTC, user agent, correlation ID, failure codes

## Pass three — two-tenant support (`prompts/pass3.md`)

- Staff tenant: teacher OAuth + Classroom (`GoogleClientFactory`)
- Student tenant: Directory DWD (`StudentDirectoryClientFactory`, `GOOGLE_DIRECTORY_*`)
- Discovery by live roster email → reset by immutable Directory user ID
- Audit records both roster email and canonical primary when they differ
- Operator setup: [docs/student-tenant.md](student-tenant.md)

## Local development (Phase 1–9 + pass two/three)

- Panel: `/admin`
- Seeded users (password: `password`):
  - `admin@{STAFF_DOMAIN}` — System Administrator
  - `teacher@{STAFF_DOMAIN}` — Teacher (has mock classes)
  - `auditor@{STAFF_DOMAIN}` — Auditor
- Google OAuth: set `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET`, and
  `GOOGLE_OAUTH_REDIRECT_URI` (default `${APP_URL}/auth/google/callback`) in `.env`.
- `CLASSROOM_DRIVER=mock` and `DIRECTORY_DRIVER=mock` (defaults)
