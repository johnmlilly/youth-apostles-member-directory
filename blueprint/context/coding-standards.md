# Coding Standards

> Rewritten by `/adopt` to match the conventions this codebase actually uses, not
> a default template. Edit freely; this file is yours.

## Stack

A WordPress plugin. React front end built by `@wordpress/scripts`, PHP REST
controller, CiviCRM APIv4 as the data source. npm is the package manager. There
is no framework beyond WordPress itself, no state library, and no CSS framework.

## Formatting

- **Tabs for indentation**, in PHP, JS, and CSS alike
- WordPress JS spacing: spaces inside parens and braces - `( a, b )`,
  `{ member }`, `useState( '' )`
- Single quotes in JS and PHP
- Trailing commas in multi-line arrays and arguments

Match the surrounding file. `wp-scripts` ships an ESLint config, but no lint
script is wired up, so formatting is by convention rather than enforced.

## PHP

Follow WordPress PHP coding standards, which is what the existing files do.

- Guard every file with `if ( ! defined( 'ABSPATH' ) ) { exit; }`
- `array()` long syntax, not `[]`
- Yoda conditions for comparisons against literals - `false !== $cached`
- snake_case functions and variables, `YAMD_`-prefixed constants, `YAMD_`-prefixed
  classes
- Class files are named `class-yamd-*.php` under `includes/`
- Escape on output: `esc_url_raw`, `esc_html`, and friends
- Return `WP_Error` with an explicit `array( 'status' => 4xx|5xx )` for failures,
  never a bare `false` or a silent empty array
- Wrap every CiviCRM API call in try/catch. A missing or misconfigured CiviCRM
  component should degrade the directory, not break it: the membership and
  relationship lookups both return an empty map on exception so members still
  render.

## CiviCRM access

- Call CiviCRM's PHP API in-process (`\Civi\Api4\Entity::get( TRUE )`). Never use
  the external REST API and never introduce an API key.
- Call `civicrm_initialize()` before touching any `\Civi\` class, and check
  `function_exists( 'civicrm_initialize' )` first.
- **Batch, never N+1.** Entities that do not live on `Contact` (memberships,
  relationships, related contact names) each get one query for the whole member
  list, keyed by contact id, then merged in PHP.
- **Do not rely on SQL ordering for correctness.** The membership tie-break picks
  the best row in PHP specifically because a NULL `end_date` (a lifetime
  membership) must win, and NULL sorting differs across database engines. A past
  bug came from exactly this.
- Filter `is_deleted` and `is_test` consistently. Note the one deliberate
  exception: `get_contact_names()` skips the `contact_type = 'Individual'` filter,
  because the other side of a relationship is often an Organization or Household.
- Find unknown custom field names with the CiviCRM API4 Explorer before writing
  the select, rather than guessing at `Group.Field`.

## React

- Function components with hooks only
- One component per file under `src/components/`, PascalCase filename matching the
  default export
- State is held in `App` and passed down as props. There is no context, reducer,
  or state library, and adding one is a decision, not an implementation detail.
- Derived data (filter options, the filtered and sorted list) goes in `useMemo`
  with an accurate dependency array
- Data is fetched once on mount in `App`, then all searching, filtering, and
  sorting happens client-side over the full list
- Send `X-WP-Nonce` on every request to the plugin's own REST routes
- Handle the three render states explicitly: loading, error, then content

## WordPress integration

- Enqueue assets only from the shortcode render, never site-wide
- Read the generated `build/index.asset.php` for dependencies and the version
  hash; bail early if it is missing, since that means the build has not been run
- Pass PHP values to JS through `wp_localize_script`, not inline script tags
- Release with `npm version patch|minor|major`. The `version` lifecycle script
  syncs the plugin header docblock and `YAMD_VERSION` from `package.json`. Never
  hand-edit the three; `scripts/sync-version.mjs` owns them.

## Styling

- One hand-written `src/style.css`. No preprocessor, no CSS-in-JS, no Tailwind.
- Every class is `yamd-` prefixed to avoid colliding with the host theme
- BEM-style double-dash for modifiers - `.yamd-avatar--placeholder`
- Mobile handled with `max-width` media queries at 899px and 599px
- Navy `#152b5c` on the derived tint `#eceef2` is the single accent, from the
  2025 brand guide in `blueprint/reference/`. Gold `#998631` is the brand's
  secondary color and is deliberately unused in the app so far. Grays are the
  Tailwind
  neutral ramp by value (`#111827`, `#6b7280`, `#d1d5db`, `#e5e7eb`), used
  directly as hex.
- Keep the `@media print` block working. Anything new and interactive that is
  added to the page needs a matching hide rule there.
- No inline styles. The one `MODAL_STYLE_OVERRIDE` in `MemberCard` exists solely
  to blank out `react-modal`'s injected inline defaults so the stylesheet wins.

## Types

> TODO (confirm): aspirational until build item 6 lands. Today every file is
> plain `.js` with JSX and there is no type checking at all.

Once the TypeScript migration is done:

- `strict: true`, no `any`; use `unknown` and narrow
- The REST payload shape (`Member`, `Address`, `Relationship`) is defined once and
  imported, not re-declared per component
- Component props get an explicit interface
- PHP stays untyped and unanalyzed by decision. Do not add PHPStan, Psalm, or
  PHPUnit without asking.

## Testing

**No test runner is configured, so there is no test gate right now.** The switch
is a `test` command in the Commands section of `AGENTS.md`. It is absent, which
means the loop verifies logic with the evidence it already has: `npm run build`
succeeding, and the directory rendering in a real browser on a page carrying the
shortcode.

Build item 7 turns this on through `/tests`. Do not install a runner in the
middle of an unrelated feature.

When the gate is on, the scope rule is the usual one:

- **Test** pure logic where a wrong answer is possible. In this codebase that
  means `formatDate` (the local-versus-UTC parsing that exists to dodge an
  off-by-one day) and the search, filter, and sort derivation in `App`.
- **Do not test** rendering, the modal, or anything that would need a live
  WordPress or CiviCRM instance. Verify those with the browser and the build.
- An empty suite must fail, not pass.
- Test files sit next to their source - `formatDate.test.ts` beside the module.

Browser testing is separately opt-in through `/browser-tests`. Nothing is
installed today. Until then, UI evidence means loading the shortcode page while
logged in and looking at it, including a print preview for anything that changes
layout.

## Verification

There is no `Verify` command and no CI. The real gate is `npm run build`. Run it
after any change under `src/`, because WordPress serves `build/`, not `src/`, so
an unbuilt change looks like no change at all.

## Code Quality

- No commented-out code, with one deliberate exception: the chapter custom-field
  lines in the REST controller are intentionally left commented as the documented
  extension point, and the README points at them. Remove them when build item 8
  wires the field for real.
- No unused imports or variables
- Keep functions under 50 lines where practical

## Comments

**This codebase teaches.** It was written while learning WordPress plugin
development and CiviCRM, and the comments are part of the product: a future
reader (often the author, months later) should be able to understand not just
what the code does but how the platform underneath it works. New code matches
that, so it reads consistently with its neighbors.

This is a deliberate departure from the usual "comment only the why" rule.

### What to explain

- **Platform mechanics.** How WordPress or CiviCRM actually behaves, especially
  where it is implicit or magic. Why `rest_api_init` is the right hook, what the
  plugin header block does, how a shortcode gets replaced, why `build/index.asset.php`
  exists, what a nonce proves, what a transient is.
- **Non-obvious decisions and gotchas.** Keep every one of these. They are the
  highest-value comments in the codebase: the NULL `end_date` tie-break done in
  PHP rather than SQL, the local-versus-UTC date parsing in `formatDate`, the
  deliberately missing `contact_type` filter in `get_contact_names()`, the
  `MODAL_STYLE_OVERRIDE` that exists to beat `react-modal`'s inline styles.
- **Orientation.** A file-level block saying what the file is for and where it
  sits in the request flow, as `class-yamd-rest-controller.php` does.
- **Where to go next.** Pointers a reader can act on, like using the CiviCRM API4
  Explorer to find a custom field name.

### What still does not earn a comment

Teaching style is not a license for noise.

- Do not explain the JavaScript or PHP language itself. A reader knows what
  `map`, `filter`, and `foreach` do. Explain CiviCRM's relationship direction
  model instead.
- Do not restate the line below it. `// Set loading to false` adds nothing.
- Do not leave a comment that has drifted from the code. A wrong explanation is
  worse than none, so update comments in the same edit that changes the code.
- Prefer a clear name over a comment that compensates for an unclear one.

The test: would this comment save a reader a trip to the WordPress or CiviCRM
docs? If yes, write it. If it only restates the syntax in front of them, cut it.

## Writing

- No em dashes in generated content: docs, comments, commit messages, READMEs,
  specs. They read as AI-generated.
- Use a hyphen for `term - description` separators; rephrase prose with commas,
  parentheses, or a colon. Avoid en dashes and the ellipsis character too.
- Note that the existing README and the pre-Blueprint history notes do use em
  dashes. The rule applies going forward rather than as a cleanup task.
