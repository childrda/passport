# Passport — Pass Two Remediation Prompt

This is a remediation pass against the existing codebase, not a rebuild. Do not
regenerate the application. Make the targeted changes below, add tests that prove each
one, and keep everything else working. The written requirements from the original build
prompt remain authoritative.

Work in the order given. Items 1 and 2 are blockers — the app should not be connected to
the live Google Directory API until both are done.

---

## 1. BLOCKER — Add the missing Classroom email scope

The code calls `getEmailAddress()` on the Classroom user profile, but the
`classroom.profile.emails` scope is not requested anywhere. Google only populates
`UserProfile.emailAddress` when that scope is granted, so student emails will be blank in
rosters.

- Add `https://www.googleapis.com/auth/classroom.profile.emails` to the OAuth scope list
  in `app/Services/GoogleAuthService.php` (`scopes()`) AND to the client scopes in
  `app/Services/Google/GoogleClientFactory.php` (`setScopes([...])`).
- These two lists currently duplicate the scope set and can drift. Define the scope list
  **once** — in `config/reset.php` (e.g. `reset.google.scopes`) or a shared provider —
  and have both the OAuth request and the API client read from that single source.
- Add a test asserting the canonical scope list includes all three Classroom scopes and
  that both consumers read from it.

## 2. BLOCKER — Stop routing the temporary password through Livewire mounted-action state

Today the success flow calls
`replaceMountedAction('showTemporaryPassword', ['temporaryPassword' => ...])`. Filament
mounted-action arguments are stored in the component's `mountedActions` state, which
Livewire serializes to the browser as component state — so the password is not confined
to the immediate response.

The existing test `test_temporary_password_is_not_a_livewire_property_and_clears_on_dismiss`
does NOT prove the requirement: it only checks that no dedicated public property exists.
The password is still present in `mountedActions[0]['arguments']['temporaryPassword']`.

- Redesign the success display so the temporary password is rendered **once** in the
  immediate response to the reset action, without remounting a Filament action that
  carries the password in its arguments. (A one-shot render in the action's own response,
  or a transient non-persisted view variable, is fine — the password must not survive
  into serialized Livewire component state or any subsequent request payload.)
- Replace the misleading test with one that actually asserts the requirement: after the
  reset action completes, assert the temporary password does NOT appear anywhere in the
  component's serialized state (including `mountedActions` arguments) and does not appear
  in the next request payload.
- Manually inspect the browser's Livewire response and the following request payloads to
  confirm the password does not travel after the initial display. Note in
  `docs/testing.md` how this was verified.

## 3. Handle uncertain Directory API outcomes (timeouts) distinctly from confirmed failures

All Directory failures currently collapse into `PasswordResetException::resetFailed()`
("please try again"). That is unsafe for a timeout or interrupted response: Google may
have completed the reset even though the app never received confirmation. A retry would
mint a second password and invalidate the first — including one the teacher may already
be reading to a student.

- Split the outcome into at least two cases:
  - `confirmed_failure` — the request demonstrably did not apply (e.g. clear API error
    response). Safe to retry. Keep the current "try again" messaging.
  - `outcome_unknown` — timeout, connection interruption, or any response the app could
    not confirm. Show messaging like: "We could not confirm whether Google completed the
    reset. Do not retry yet — contact Technology Support." Do NOT offer a retry button
    for this case.
- Audit both outcomes with distinct failure codes (see item 8).
- Add tests for both paths: a confirmed failure allows retry; an unknown outcome does not
  and surfaces the do-not-retry message.

## 4. Add per-student reset lock and double-click protection

There is no lock around the reset. Two clicks or two teachers could reset the same
student within seconds, and the second password would silently replace the first.

- After resolving the canonical Directory user and before the final reset, wrap the
  revalidate-and-reset block in a short lock keyed to the canonical Directory user ID,
  e.g.:
  ```php
  Cache::lock("student-password-reset:{$directoryUser->id}", 15)->block(2, function () {
      // re-validate roster + execute reset
  });
  ```
- If the lock cannot be acquired, treat it as a transient "another reset is in progress"
  condition and deny gracefully (not a success).
- Document that production must use a shared lock-capable cache (Redis), not the array/file
  driver.
- In the UI, disable the confirm button as soon as it is submitted so a double-click
  cannot fire two requests.
- Add a test proving concurrent/repeat resets on the same student do not both execute.

## 5. Decide role provisioning — do not auto-assign Teacher to every staff account

`GoogleAuthService::syncUserFromGoogle()` assigns the Teacher role to any user with no
roles, so every `lcps.k12.va.us` account becomes a Teacher on first sign-in. Classroom
scoping still limits which students they can touch, but role assignment should be
intentional, not incidental — and accounts meant to be auditors/admins become teachers
first.

- Add an explicit enablement gate: a `reset_access_enabled` (or equivalent) flag on the
  user, or explicit role provisioning, so signing in does not by itself grant reset
  capability.
- Do not auto-add Teacher to users intended for administrative or auditor roles.
- The reset action must check the enablement gate server-side (in addition to Classroom
  scoping), enforced by policy — not just a hidden button.
- Add tests: a signed-in staff account without the gate cannot reach the reset action; a
  provisioned teacher can.

## 6. Harden audit logs toward truly append-only

Audit records are read-only in the Filament resource but are a normal Eloquent model with
fillable fields, so update/delete remain reachable from code.

- Block updates and deletes at the model level (e.g. guard `updating`/`deleting` events or
  override to throw) AND add a policy denying update/delete everywhere.
- Corrections are written as new audit records, never edits.
- Document that production should additionally enforce append-only via database
  permissions/triggers.

## 7. Enrich audit records

Add to each audit entry, if not already present:
- Explicit UTC timestamp
- User agent
- Application correlation ID generated at the start of each attempt
- A stable, normalized failure **code** (e.g. `not_on_roster`, `staff_account`,
  `directory_timeout_unknown`, `directory_confirmed_failure`) stored separately from the
  human-readable, teacher-facing message. Report/filter on the code, not the prose.

## 8. Smaller corrections

- **PHP version:** change `composer.json` `"php": "^8.2"` to `"php": "^8.3"` (the build
  target is 8.3+). Only keep 8.2 if that support is intentional.
- **OAuth prompt churn:** change `'prompt' => 'select_account consent'` to
  `'prompt' => 'select_account'` for normal sign-in. Perform a deliberate consent-
  requesting reconnect flow only when a refresh token is absent.
- **OAuth identity matching:** the sign-in query matches on `google_id` OR `email`. Once a
  user has a `google_id`, reject a mismatched Google ID rather than overwriting it via the
  email match — prevents accidentally linking an existing account to a different Google
  identity.
- **Directory error detail:** keep detailed Google exception text out of teacher-facing
  messages (the reset path already replaces it with a generic message — preserve that).
  Log Google error detail only after sanitization.
- **Course-name lookup ordering:** `resolveCourseName()` makes a Classroom call before the
  two authorization calls, purely for audit convenience. Move the name resolution so it
  cannot delay or interfere with authorization — resolve from existing UI/validated data
  after the authorization checks, or leave the audit course name null when unavailable.

---

## Before any production reset

- Run `composer install` and the full suite; confirm every test passes (not just that
  tests exist).
- Confirm items 1 and 2 are complete and verified in the browser payload.
- Perform a live end-to-end test with ONE nonproduction teacher, class, and student
  account before enabling resets for real students.

## Constraints for this pass
- Do not regenerate or restructure the application; make targeted changes only.
- Every change above must ship with a test that proves it.
- Keep all existing passing behavior intact.
- Do not weaken any existing security control (fail-closed ordering, canonical-identity
  domain check, no-password-persistence) while making these changes.
