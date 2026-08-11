# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A WordPress plugin: an interactive, searchable/filterable member directory that pulls live data from CiviCRM. Frontend is React (mounted via a shortcode), backend is a WordPress REST endpoint that queries CiviCRM's PHP API directly (in-process, no HTTP/API key).

## Commands

```bash
npm install       # install deps
npm run build     # production build -> build/ (JS/CSS WordPress actually loads)
npm run start     # dev mode, watches src/ and rebuilds automatically
```

There is no test suite, linter, or type checker configured — `build`/`start` (via `@wordpress/scripts` / `wp-scripts`) are the only scripts. `build/` is committed/generated output; re-run `npm run build` after any `src/` change for it to take effect in WordPress (there's no CI step that does this).

The plugin only runs meaningfully inside a WordPress install with CiviCRM active — there's no standalone way to run/test it outside that environment.

## Architecture

```
Browser (React app, src/)
   |  fetch() with X-WP-Nonce header
   v
WordPress REST API  (/wp-json/yamd/v1/members)
   |  PHP function call, in-process (no HTTP, no API key)
   v
CiviCRM APIv4 (Contact::get), running as a plugin in the same WP install
```

Key files:
- `youth-apostles-member-directory.php` — plugin entry point. Registers the REST route on `rest_api_init`, registers the `[youth_apostles_directory]` shortcode, and enqueues `build/index.js`/`.css` only on pages using the shortcode. Passes `apiUrl` + REST nonce into JS via `wp_localize_script` as `window.yamdData`.
- `includes/class-yamd-rest-controller.php` — `YAMD_REST_Controller` class, defines `GET /wp-json/yamd/v1/members`. Requires a logged-in WP user (`is_user_logged_in()`). Queries CiviCRM's `Contact::get` APIv4, filtered to `MEMBERS_GROUP_ID` (currently `27`), caches result 5 min via `set_transient`.
- `src/index.js` — mounts `<App />` into `#yamd-directory-root` on `DOMContentLoaded`.
- `src/App.js` — fetches `/members`, holds search/filter/sort state, does all filtering/sorting client-side via `useMemo`.
- `src/components/SearchFilterBar.js`, `src/components/MemberCard.js` — presentational components.

## Adding a CiviCRM custom field (e.g. chapter)

Three places must stay in sync when exposing a new CiviCRM field:
1. `includes/class-yamd-rest-controller.php` — add the field to `addSelect(...)` (find exact name via CiviCRM's API4 Explorer, format `GroupName.FieldName`), then map it in the `foreach` loop (e.g. `'chapter' => $r['Membership_Info.Chapter'] ?? ''`).
2. `src/components/MemberCard.js` — render it.
3. `npm run build` — rebuild, since WordPress loads compiled `build/index.js`, not `src/` directly.

The filter bar's chapter dropdown (`src/App.js` `chapters` useMemo) auto-populates from whatever `chapter` values exist — no separate wiring needed there.

## Feature tracking

`context/current-feature.md` tracks the feature currently being built (status + goals), upcoming features, and a history log of past actions. Check it for current priorities and update it when starting/finishing a feature.

## Gotchas

- Changing `MEMBERS_GROUP_ID` in `class-yamd-rest-controller.php` requires the real CiviCRM group ID (WP Admin → CiviCRM → Contacts → Manage Groups, ID is in the URL as `gid=`).
- CiviCRM data changes don't show immediately — 5 min transient cache in `get_members()`. Lower `5 * MINUTE_IN_SECONDS` while testing.
- If the directory area is blank, it's almost always `build/index.js` missing (build never run) — check browser console first.
