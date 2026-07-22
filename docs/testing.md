# Testing

Automated tests live under `tests/` and use mocked Google API gateways
(`ClassroomApiGateway`, `DirectoryApiGateway`) plus the mock drivers.

## Run

```bash
php artisan test
```

## Requirements checklist (`prompts/main.md`)

Covered explicitly by `Tests\Feature\RequirementsChecklistTest` and the broader suite:

| Requirement | Primary coverage |
|-------------|------------------|
| Teacher sees own classes | `RequirementsChecklistTest`, `MockClassroomServiceTest`, `GoogleClassroomServiceTest`, `TeacherClassroomUiTest` |
| Teacher sees enrolled students | same |
| Teacher cannot access another teacher’s roster | same |
| Teacher cannot reset outside roster | `RequirementsChecklistTest`, `PasswordResetTest` |
| Staff-domain accounts cannot be reset | `RequirementsChecklistTest`, `PasswordResetTest`, `GoogleDirectoryServiceTest` |
| Password length (default 10) | `TemporaryPasswordGeneratorTest`, `RequirementsChecklistTest` |
| Password uses configured alphabet only | same |
| `changePasswordAtNextLogin` is true | `PasswordResetTest`, `GoogleDirectoryServiceTest`, `RequirementsChecklistTest` |
| Temporary password not stored or logged | `PasswordResetTest`, `AuditAndAdministrationTest`, `RequirementsChecklistTest` |
| Google API failures are failures (not successes) | `GoogleClassroomServiceTest`, `GoogleDirectoryServiceTest`, `RequirementsChecklistTest` |
| Every attempt creates an audit record | `AuditAndAdministrationTest`, `RequirementsChecklistTest` |

## Notes

- Feature tests use SQLite in-memory (`phpunit.xml`) unless overridden.
- Live Google calls are never made in CI; set `CLASSROOM_DRIVER=mock` and
  `DIRECTORY_DRIVER=mock` for local fixture runs.
- For staging smoke tests against real Google APIs, use the steps in
  [deployment.md](deployment.md) instead of PHPUnit.

## Pass two — temporary password Livewire verification

The temporary password must appear **only** in the immediate reset response (as a
one-shot JS effect that Alpine renders), never in Livewire component state
(`mountedActions` arguments, public properties, session, or subsequent request
payloads).

Automated coverage: `PassTwoRemediationTest::test_temporary_password_is_not_in_livewire_serialized_state`
and `PasswordResetTest` UI cases.

**Manual browser check (required before production Directory):**

1. Open DevTools → Network.
2. Reset a mock/test student password.
3. Inspect the Livewire update response: the password may appear once inside a
   `js` / effects payload for `passport-temp-password`.
4. Confirm the Livewire component snapshot / next request body does **not**
   include the password (no `mountedActions[…].arguments.temporaryPassword`).
5. Confirm closing the Alpine dialog and refreshing the page cannot re-show it.
