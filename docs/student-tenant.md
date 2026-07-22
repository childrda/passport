# Passport — Two-Tenant Google Setup

Passport spans **two separate Google Workspace tenants**. Each needs different setup, and
the two must not be confused. Domain-wide delegation is authorized **inside a specific
tenant** — a grant in one tenant confers no authority in the other.

| | Staff tenant | Student tenant |
|---|---|---|
| Domain | `lcps.k12.va.us` | `k12louisa.org` |
| Contains | Teachers, **Google Classroom** | Student accounts (only) |
| Passport uses it for | Teacher sign-in + Classroom roster reads | Directory lookups + password resets |
| Credential type | Teacher's own OAuth token | Service account with domain-wide delegation |
| Needs a DWD grant? | **No** | **Yes** |

Related app config: `config/reset.php`, `.env.example` (`GOOGLE_OAUTH_*` vs `GOOGLE_DIRECTORY_*`).

---

## Part 1 — Staff tenant (`lcps.k12.va.us`)

Teachers authenticate with their own Google account; Passport reads Classroom as that
teacher. No service account and no domain-wide delegation are required here.

1. In the Google Cloud project, configure the **OAuth consent screen**.
   - Restrict the app to the district's Workspace organization (internal) if possible.
   - The Classroom scopes below are **sensitive** and may require OAuth verification
     depending on project and consent-screen configuration.
2. Create an **OAuth client ID** (web application) and set the redirect URI to Passport's
   callback URL (`{APP_URL}/auth/google/callback`).
3. Ensure these scopes are requested (they are defined once in `config/reset.php`):
   - `https://www.googleapis.com/auth/classroom.courses.readonly`
   - `https://www.googleapis.com/auth/classroom.rosters.readonly`
   - `https://www.googleapis.com/auth/classroom.profile.emails`
4. Set in `.env`:
   - `GOOGLE_OAUTH_CLIENT_ID`
   - `GOOGLE_OAUTH_CLIENT_SECRET`
   - `GOOGLE_OAUTH_REDIRECT_URI`
   - `STAFF_DOMAIN=lcps.k12.va.us`

**Verify:** a teacher can sign in, sees only their own courses, and the roster shows
student `@k12louisa.org` email addresses. If emails are blank, stop — see the External
participants note below.

---

## Part 2 — Student tenant (`k12louisa.org`)

This is where password resets actually happen. The grant must be made in the **student
tenant's** Admin console.

1. **Create a service account in a district-controlled Google Cloud project** and generate
   a JSON key. The project itself does not grant access to the student tenant. Access is
   granted only when the service account's **numeric client ID** and required scope are
   authorized in the `k12louisa.org` Admin console (step 2).
   - Note its **Client ID** (the numeric OAuth2 client ID, not the email).
   - Store the JSON key outside the repository; reference it by path/secret only.
2. **Grant domain-wide delegation in the STUDENT tenant.**
   Sign in to the Admin console for **`k12louisa.org`** (not the staff one) →
   **Security → Access and data control → API controls → Domain-wide delegation** →
   **Add new**.
   - Client ID: the service account's client ID from step 1
   - OAuth scope: `https://www.googleapis.com/auth/admin.directory.user`
3. **Choose the impersonated admin — must exist in the student tenant.**
   - The account must be an administrator **in `k12louisa.org`**, e.g.
     `passport-admin@k12louisa.org`.
   - Prefer a **scoped custom admin role** over super admin, if it can perform the
     required user read + password update operations.
   - If using a scoped role, confirm its **org-unit scope covers the student OUs** you
     need. A role limited to the wrong OUs will fail on every reset.
   - The impersonated administrator must remain an active account with the required
     delegated privileges and comply with applicable tenant security policies.
4. Set in `.env`:
   - `GOOGLE_DIRECTORY_CREDENTIALS` — path/reference to the service-account JSON key
   - `GOOGLE_DIRECTORY_IMPERSONATED_ADMIN` — the **student-tenant** admin address
     (must be on `k12louisa.org`; Passport rejects a staff-domain value at startup)
   - `STUDENT_DOMAIN=k12louisa.org`

**Verify:** using the impersonated admin, a Directory `users.get` for one real student
returns that student's account, and a test password update succeeds on a **nonproduction**
student account.

---

## Part 3 — Preflight checklist

- [ ] Teacher can sign in (staff tenant) and sees only their own Classroom courses
- [ ] Server-side `courses.students.list` returns nonblank student `@k12louisa.org` emails
      for a real external student — **see go/no-go note below**
- [ ] **Admin SDK Directory API enabled** in the Google Cloud project used by the service
      account
- [ ] **Google Classroom API enabled** in the Google Cloud project used by the OAuth client
- [ ] Service account client ID authorized for `admin.directory.user` in the
      **`k12louisa.org`** Admin console
- [ ] Passport can obtain a **delegated Directory access token** before attempting a
      student lookup
- [ ] `GOOGLE_DIRECTORY_IMPERSONATED_ADMIN` is an admin **in the student tenant**
- [ ] Impersonated admin's scope covers the student OUs
- [ ] Directory lookup by student email succeeds from Passport
- [ ] Test reset succeeds against **one nonproduction student account**
- [ ] Confirmed students sign in to Google **directly**, not via a third-party IdP
      (Clever, ClassLink, SAML) — otherwise `changePasswordAtNextLogin` will not force a
      change at their actual login
- [ ] `CLASSROOM_DRIVER=google` and `DIRECTORY_DRIVER=google` in production
- [ ] Production cache is Redis (or another shared lock-capable driver) for the
      per-student reset lock

---

## Note: external participants and roster emails

Students are **external participants** in staff-tenant Classroom courses — they belong to a
different Workspace. Passport uses the roster email to find the student in the student
tenant, so **if Classroom does not return emails for external students, Passport cannot
identify them.**

**Go/no-go test.** Before enabling the live Directory integration, test one staff-tenant
Classroom course containing one real `@k12louisa.org` student. Confirm that the
**server-side `courses.students.list` response** includes a nonblank student email address.
**Do not rely only on what the Classroom web interface displays** — the UI showing an email
tells you nothing about what the API returns to Passport under its scopes.

The `classroom.profile.emails` scope permits access to Classroom profile email fields, but
returned data can still depend on what the authenticated teacher is authorized to view.

If roster emails come back blank, stop and reassess before further development — Passport
would have no way to identify students, and the approach needs rethinking.

Keep `DIRECTORY_DRIVER=mock` until this go/no-go test succeeds.

## Common misconfigurations

- **Grant made in the wrong tenant.** A DWD grant in `lcps.k12.va.us` gives no authority
  over `k12louisa.org` accounts. Resets will fail with authorization errors.
- **Impersonated admin in the wrong tenant.** The admin must exist in `k12louisa.org`.
  Passport validates this at startup and refuses to run otherwise.
- **Scoped admin role too narrow.** A delegated admin restricted to OUs that exclude
  students will fail every reset even though delegation looks correct.

## See also

- [deployment.md](deployment.md) — env vars, security, operations
- [architecture.md](architecture.md) — how the two tenants map to code
- Ubuntu install walkthrough: [README.md](../README.md)
