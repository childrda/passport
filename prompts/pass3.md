# Passport — Pass Three: Two-Tenant (Cross-Workspace) Support

This is a targeted remediation pass against the existing codebase. Do not regenerate the
application. All existing security controls must remain intact.

## Why this change

The original build assumed staff and student accounts lived in **one** Google Workspace
tenant. They do not. LCPS runs **two separate Workspace tenants**:

- **Staff tenant — `lcps.k12.va.us`** — teachers sign in here. **Google Classroom lives
  here.**
- **Student tenant — `k12louisa.org`** — student accounts live here, and **only** here.
  Students do not have staff-tenant accounts.

Students appear in staff-tenant Classroom courses as **external participants**,
identified by their `@k12louisa.org` address.

Domain-wide delegation is scoped to a single tenant, so the Directory API work must be
authorized in the **student tenant**, separately from anything in the staff tenant.

## Directory discovery uses the live roster email

Students participate in staff-tenant Classroom courses as external users from the
`k12louisa.org` tenant. Classroom may expose both a Google user ID and an email address
for each roster member.

For Passport, use the `@k12louisa.org` **email address** returned by the server-side live
Classroom roster as the discovery key for the student-tenant Directory lookup. This is the
clearest cross-tenant identifier and can be supplied directly as the Directory API
`userKey`.

Do **not** assume or depend on the Classroom numeric user ID being resolvable through the
delegated student-tenant Directory client unless that behavior has been separately
verified.

The roster email is used **only to discover** the Directory account. After lookup:

- Treat the Directory response as authoritative.
- Confirm the resolved canonical **primary** email is on the configured student domain.
- Execute the reset against the **immutable Directory user ID**.

A roster email may be an alias, so resolving the account before reset remains mandatory.

The Directory API permits `users.get` to use a primary email, alias email, or unique user
ID as the `userKey`, so this email-discovery approach is valid.

---

## Changes

### 1. Split the Google clients by tenant

- **Staff tenant / Classroom (unchanged):** `GoogleClientFactory` continues to build a
  teacher-OAuth client for Classroom reads. No Directory grant is needed in the staff
  tenant at all.
- **Student tenant / Directory:** `GoogleServiceAccountClientFactory` must target the
  **student tenant** — its own service-account credentials and an impersonated admin who
  **exists in the student tenant**.
- Make the tenant separation explicit in naming and comments so a future reader cannot
  confuse the two paths (e.g. `StudentDirectoryClientFactory`, or clear docblocks stating
  which tenant each factory serves).

### 2. Config: separate student-tenant Directory settings

In `config/reset.php`, keep staff-tenant OAuth settings as-is and add distinct
student-tenant Directory keys, e.g.:

- `reset.google.directory.credentials` ← `GOOGLE_DIRECTORY_CREDENTIALS`
  (service-account key authorized in the **student** tenant)
- `reset.google.directory.impersonated_admin` ← `GOOGLE_DIRECTORY_IMPERSONATED_ADMIN`
  (an admin account **in the student tenant**, e.g. `admin@k12louisa.org`)

Deprecate/rename the current single `GOOGLE_IMPERSONATED_ADMIN` /
`GOOGLE_SERVICE_ACCOUNT_CREDENTIALS` keys so it is unambiguous which tenant they belong
to. Update `.env.example` and fail fast at startup with a clear message if the
student-tenant Directory settings are missing.

Add a startup validation: the configured `directory.impersonated_admin` must be on the
**student domain**. An admin address on the staff domain is a misconfiguration and must
be rejected — it means someone pointed the Directory client at the wrong tenant.

### 3. Directory lookup: discovery by email, constrained at the service layer

Google's `users.get` accepts a primary email, alias email, or immutable user ID as the
`userKey`, so a single gateway method serves both discovery and ID retrieval. Put the
constraint in the application service, not by amputating the gateway.

- In `App\Contracts\DirectoryApiGateway`, provide a single generic method:
  ```php
  public function getUser(string $userKey): ?array;
  ```
  (same return shape as today). Implement it in `GoogleDirectoryApiGateway` and the mock
  gateway. Retaining ID-capable retrieval here is intentional — reset and future
  verification workflows may legitimately need it.
- In `App\Contracts\DirectoryService` / `GoogleDirectoryService`, replace
  `findByClassroomUserId()` with:
  ```php
  public function findByRosterEmail(string $email): ?DirectoryUser;
  ```
  Remove `findByClassroomUserId()` from the **application service** so discovery cannot
  accidentally be driven by the Classroom numeric ID.
- Keep `resetPassword(string $directoryUserId, ...)` unchanged — it must continue to take
  the **immutable Directory user ID**, not an email.

The division of responsibility: the service decides that *discovery* uses the live roster
email and the *reset* uses the resolved immutable ID; the gateway stays a thin pass-through
over the Google endpoint.

### 4. Reset service: match the roster entry safely, then discover by email

In `StudentPasswordResetService` (currently around lines 69-92):

- The live roster check stays first and unchanged — it is the authorization gate.

**Matching the teacher's selection (important):**
- The browser submits the **course ID and Classroom roster member ID only** — never an
  email.
- During the live roster re-fetch, locate the matching roster member by **exact Classroom
  member ID**.
- Obtain the email **only from that matched server-side roster object**. Never accept a
  browser-submitted email as the Directory lookup value.

**Normalization (both sides):**
- Before calling `findByRosterEmail()`: trim surrounding whitespace, lowercase, parse the
  domain, and require exact equality with `reset.student_domain`. This pre-check reduces
  unnecessary or suspicious Directory queries.
- After lookup: repeat the exact-domain check against the resolved Directory **primary**
  email. **This post-lookup check is the security boundary** — keep the existing
  lowercase/parse/exact-equality logic and the explicit staff-domain reject.

**Reset and audit:**
- Continue executing the reset against `$directoryUser->id`.
- When the roster email differs from the resolved canonical primary email (alias case),
  audit **both** the normalized roster email and the resolved canonical primary email. Do
  not treat the alias as the canonical student identity.
- If `findByRosterEmail()` returns null (student is on the Classroom roster but has no
  account in the student tenant), deny with a clear, distinct failure — do not fall through
  to a generic error. Audit it with its own failure code (e.g. `student_not_in_directory`).

### 5. Roster: ensure the student email is carried through

`ClassroomStudent` already carries `email`. The UI must pass only the **course ID and
Classroom roster member ID** into the reset action. The service re-derives the email from
the live roster fetch by matching on that member ID. The browser must never supply the
email used for the Directory lookup.

### 6. Update mocks, tests, and docs

Add or update tests to prove:
- A student discovered by roster email whose canonical primary email is on the student
  domain can be reset.
- A browser-submitted email is ignored: only the server-side roster match determines the
  lookup value.
- Roster email normalization (whitespace/case) is applied before lookup, and a roster email
  outside the student domain is rejected pre-lookup.
- A roster email that resolves to a Directory user whose **primary** email is on a
  different domain is rejected (alias-misdirection case).
- When roster email and canonical primary email differ, both are recorded in the audit.
- A staff-domain account is still rejected.
- A roster email with **no** matching student-tenant Directory user is denied with the
  distinct `student_not_in_directory` outcome.
- The reset executes against the immutable Directory user ID, not the email.
- Startup validation rejects a `directory.impersonated_admin` that is not on the student
  domain.
- Existing controls still pass: fail-closed on API errors, no password persistence, lock
  behavior, `changePasswordAtNextLogin`.

Update `docs/deployment.md` to describe the **two-tenant** architecture and the separate
student-tenant DWD grant (see the accompanying setup doc).

---

## Prerequisite to verify before live testing

Confirm that the Classroom API actually returns **email addresses for external
participants** (students from the other tenant) with the `classroom.profile.emails` scope
granted. The `emailAddress` field requires that scope, and `courses.students.list` returns
only students and data the requester is permitted to view — so external-participant
visibility must be confirmed empirically rather than assumed. If emails come back blank for
external students, Passport has no way to identify the student in the student tenant and
this approach must be reconsidered before further work.

Perform this test before enabling or completing the live Directory reset integration. Code
changes may be developed against mocks, but **production Google Directory calls must remain
disabled until the external roster email test succeeds.** This verification is a
tenant-level task for the operator, not something to be assumed or self-certified during
implementation.

## Constraints
- Do not regenerate or restructure the application.
- Do not weaken existing controls: live roster re-check first, canonical primary-email
  domain check, exact-equality domain match, reset by immutable ID, no password
  persistence, fail-closed error handling, per-student lock.
- Every change ships with a test.
