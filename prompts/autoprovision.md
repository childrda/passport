# Passport — Auto-Provision Teachers on First Sign-In

Targeted change against the existing codebase. Do not regenerate the application and do
not modify any reset, authorization, audit, or Google Directory logic.

## Goal

Today a new staff member signs in successfully but lands with **no role** and
`reset_access_enabled = false`, so they see "Your Google account is recognized, but it has
not been provisioned for this application yet." An administrator must then grant the
Teacher role and flip the enablement toggle by hand for every teacher.

Change this so that a user created on first Google sign-in is **automatically provisioned
as an enabled Teacher**.

Scoping is already handled by the Google Classroom API: a staff member with no Classroom
courses sees an empty class list and has nothing to act on. The staff-domain restriction on
sign-in is unchanged.

## Required behavior

In `App\Services\GoogleAuthService::syncUserFromGoogle()`, in the **user-creation branch
only** (currently `if ($user === null) { $user = User::query()->create($attributes); }`):

- Set `reset_access_enabled = true` on the newly created user.
- Assign the `RoleName::Teacher` role to the newly created user.

**Only on creation.** Do not assign roles or modify `reset_access_enabled` in the
update/`else` branch. An existing user whose access an administrator has revoked must stay
revoked across subsequent sign-ins — re-enabling on every login would silently undo
administrative action.

**Only the Teacher role.** Never auto-assign `RoleName::SystemAdministrator` or
`RoleName::Auditor`. Those remain manual.

Keep the `reset_access_enabled` column, the Filament Users toggle, and the
`User::canResetStudentPasswords()` gate exactly as they are. Auto-provisioning changes the
default value at creation; it does not remove the ability to disable someone later.

Make sure the role assignment and the user creation are consistent if one fails — wrap them
so a created user cannot end up enabled but role-less (or vice versa).

## Tests

Add or update tests proving:

- A new staff-domain user signing in for the first time is created with
  `reset_access_enabled = true` and holds the Teacher role.
- That newly provisioned user passes `canResetStudentPasswords()`.
- An **existing** user with `reset_access_enabled = false` still has it `false` after
  signing in again (revocation survives re-login).
- An existing user's roles are not modified on re-login (no role is added back).
- Auto-provisioning never grants System Administrator or Auditor.
- The staff-domain sign-in restriction is unchanged: a non-staff-domain account is still
  rejected and no user row is created.
- A provisioned teacher with no Classroom courses sees an empty class list and cannot
  initiate a reset.

Do not weaken or delete existing tests.

## Also fix: remove the local email/password login form

The Filament login page currently renders **email and password fields** alongside the
"Sign in with Google" button. The application is Google-only by design — there is no local
password authentication for teachers or administrators.

- Remove the email/password form from the login page so "Sign in with Google" is the only
  authentication path.
- Keep the "Use your lcps.k12.va.us account." helper text.
- Ensure the local login POST route/handler is unreachable, not merely hidden — a hidden
  form that still accepts credentials is a second auth path.
- Add a test asserting local credential login is rejected.

## Constraints

- Do not modify `StudentPasswordResetService`, the Google Directory/Classroom services, the
  roster-matching logic, audit logging, or the one-shot temporary-password display.
- Do not change the staff-domain restriction on sign-in.
- Every change ships with a test.
