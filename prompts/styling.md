# Passport — UI Styling Pass

Targeted styling work against the existing codebase. Do **not** regenerate the application
and do **not** change any reset logic. `mockup.png` is the visual target.

## Hard constraint: do not touch working functionality

The two-tenant reset flow is tested and working in production against real Google
Workspace tenants. This pass is **presentation only**.

Do not modify:
- `StudentPasswordResetService` or any service/gateway class
- The one-shot temporary-password display mechanism (`$this->js()` dispatch and its
  listener) — the password must never enter Livewire component state, session, or any
  persisted store
- Roster matching by Classroom member ID, or the server-side email derivation
- Any domain check, authorization, audit, or locking logic

If a styling change would require altering any of the above, stop and flag it instead.

---

## 1. Panel branding and sidebar (highest impact, lowest effort)

In `app/Providers/Filament/AdminPanelProvider.php`:

- `->brandName(config('app.name'))` — read from `APP_NAME` in `.env`, not a hardcoded
  string. Use `config()`, never `env()` directly (config caching breaks `env()` outside
  config files). Set `APP_NAME=Passport` in `.env` and `.env.example`.
- Add a brand logo/mark in the sidebar header above the app name, with a small subtitle
  line beneath it (mockup shows a mark + "LCPS" + "Password Reset" stacked).
- Style the sidebar as a **dark navy panel** with light text, matching the mockup — the
  main content area stays light. Use Filament's theme/color customization and a custom
  theme CSS file rather than inline overrides.
- Navigation groups: rename the "Administration" group label to **"ADMIN"**, styled as a
  small uppercase muted label as in the mockup.
- Ensure the user/profile block renders at the **bottom of the sidebar** showing the
  signed-in teacher's avatar, name, and email (mockup shows this). This is Filament's
  existing user menu — restyle it, don't rebuild it.

## 2. My Classes — card grid instead of a table

`app/Filament/Pages/MyClasses.php` currently renders an `EmbeddedTable`. Replace the
class list with a **card grid** matching the mockup:

- Two-column responsive grid of class cards (single column on narrow viewports).
- Each card shows: a colored circular subject icon, the class name, the section/period
  line, and a chevron affordance indicating it opens the roster.
- Cards are clickable in full (not just a "View roster" link).
- Vary the icon accent color per card so the grid reads like the mockup. Derive the color
  deterministically from the course ID or name so a given class keeps a stable color
  across page loads — do not randomize per render.
- Keep the page subtitle text ("These are the Google Classroom classes you currently
  teach.") and the "Data is provided by Google Classroom" attribution line.

Implement as a custom Blade view for the page. Keep the existing data source and error
handling exactly as they are — only the rendering changes.

**Student counts:** the mockup shows "23 students" per card. `courses.list` does not return
enrollment counts, and fetching them means one extra Classroom API call per course, which
is slow for a teacher with many classes. Do **not** make blocking per-course calls on page
load. Either omit the count, or load counts lazily after the cards render. Prefer omitting
it unless lazy loading is straightforward.

## 3. Roster page

- Keep the table structure but restyle toward the mockup: **circular avatar with initials**
  before each student name, name and email as separate columns, and the action button
  right-aligned.
- Keep the existing search input, styled to match the mockup's rounded search field.
- Header block above the table: subject icon, class name, section/period line, and a
  "Back to My Classes" control. Include a breadcrumb (My Classes › {class name}).
- The Reset Password button keeps its current behavior, including the disabled state for
  non-student-domain rows. Style the disabled state clearly (muted, with a tooltip
  explaining why it is unavailable).

## 4. Modals

Restyle the existing modals to match the mockup — **do not rewire them**:

- **Confirm:** warning icon, "You are about to reset the password for:" line, a student
  chip showing avatar/name/email, and an amber explanatory panel stating a temporary
  password will be generated and the student must change it at next sign-in. Buttons:
  "Cancel" and a red "Yes, Reset Password".
- **In progress:** spinner with "Resetting Password" and a muted note that it may take a
  few seconds.
- **Success:** green check, "Password Reset Successful!", the temporary password in a
  large monospace block on a tinted panel with a copy button, a green confirmation panel
  noting the forced change at next sign-in, and the line "This password will not be shown
  again."
- **Copy feedback:** the copy button must show a brief confirmation state (e.g. "Copied!")
  after clicking. This is currently missing and teachers will click it twice without it.
- **Failure states:** ensure the existing distinct failure outcomes each render clearly —
  student no longer on roster, confirmed failure (safe to retry), unknown outcome (do NOT
  retry, contact support), and student not found in the student tenant. Style them
  consistently with the modals above. Preserve the existing messaging semantics exactly,
  especially the do-not-retry wording for unknown outcomes.

## 5. Global

- Add the persistent security note styling seen in the mockup ("Temporary passwords are
  never stored. All resets are logged and audited.") as a footer note on the roster page.
- Ensure light/dark mode both render acceptably if dark mode is enabled.
- Custom styling should live in a Filament custom theme (Tailwind) — avoid scattering
  inline styles across Blade views.

## Verification before considering this done

- A live reset still completes end to end.
- The temporary password still appears to the teacher, and still does **not** appear in
  Livewire component state, session, or any subsequent request payload — re-run the
  existing test that asserts this.
- All existing tests pass unchanged. Add tests only for new UI state (e.g. disabled button
  for non-student-domain rows); do not weaken or delete existing tests.
