# Youth Apostles Member Directory - Project Overview

<!-- blueprint:source-hash 08746215bb6645d7c451bb57e628602b0f9c01840a393f544a1d9acb34e5a3bf -->

> A WordPress plugin that renders a searchable, printable member directory for
> Youth Apostles, reading live from CiviCRM through a login-gated REST endpoint.

## Problem

Member records live in CiviCRM, but CiviCRM's contact screens are an admin tool:
hard to navigate, far more detail than a member needs, and not something you hand
to a whole community. Members have no shared way to look each other up.

This plugin puts a clean directory on the organization's WordPress site, reading
live from CiviCRM so there is no second copy of the data to keep in sync.

## Users

One audience, one permission level.

| User | Access | Needs |
|---|---|---|
| Signed-in member | Full directory: name, photo, membership, email, phone, mailing address, join date, relationships | Find a person fast, get their contact details, print the roster |
| Anonymous visitor | None. The REST endpoint returns 401. | n/a |

Deliberate design decisions:

- `check_permission()` gates on `is_user_logged_in()` and nothing narrower.
  Everyone with an account on this site is a member and is trusted with member
  contact details.
- There is no admin UI and no per-member privacy control. Directory membership is
  decided entirely in CiviCRM, by membership in group ID `27`.

## Features

Build-plan order. Items 1-4 shipped before the Blueprint was adopted.

**Shipped**

1. **Core member directory** - shortcode embed, login-gated REST endpoint,
   CiviCRM APIv4 query, client-side search/filter/sort, member cards, 5 minute
   transient cache.
2. **Membership wired end-to-end** - card built around photo, name, membership,
   and clickable contact lines; search, filter, and sort keyed on membership type.
3. **Full member details modal** - popup with mailing address, member-since date,
   and relationships resolved from each member's own perspective.
4. **Print member directory** - print button plus a print stylesheet that hides
   interactive chrome, reflows to two columns, and keeps cards whole across page
   breaks. **The headline feature**: the roster is meant to leave the screen.

**Planned**

5. **Card visual refresh** - same information, new treatment: icons beside
   membership, email, and phone, and no solid fill behind the membership label.
6. **TypeScript migration** - convert `src/` to `.ts`/`.tsx`, strict
   `tsconfig.json`, real types for the REST payload, `typecheck` command. PHP
   stays unchecked by decision.
7. **Unit tests** - add a runner, cover `formatDate` and the filter/sort
   derivation, add the `test` command. This turns the test gate on.
8. **Chapter custom field** - map, display, and filter on the CiviCRM chapter
   field. Scaffolding already sits commented out in the REST controller. Blocked
   until the real field name is found in the API4 Explorer.
9. **Vocation and community fields** - display vocation, community status, and
   community name. Same blocker as item 8, and worth doing in the same sitting.
10. **Member photo loading** - placeholder until each image resolves, eager-load
    the first 9-12, lazy-load the rest.

## Data model

**The plugin persists nothing of its own.** No custom tables, no post types, no
options. CiviCRM is the single source of truth and the only writer. The "data
model" here is the read model: the JSON shape the REST endpoint assembles.

Lock this shape. Build item 6 turns it into `Member`, `Address`, and
`Relationship` types, and every component reads from it.

### Member

Returned as a JSON array from `GET /wp-json/yamd/v1/members`, sorted by
`last_name` ascending.

- `id` (int) - CiviCRM contact id, and the React list key
- `display_name` (string) - the dominant element on the card
- `first_name` (string) - sort option
- `last_name` (string) - default sort
- `image_url` (string) - from CiviCRM `image_URL`; empty means render the
  placeholder avatar
- `email` (string) - primary email, `''` if none, rendered as `mailto:`
- `phone` (string) - primary phone, `''` if none, rendered as `tel:` after
  stripping non-digits
- `membership_type` (string) - membership type label, `''` if none. Drives the
  filter dropdown and one sort option.
- `member_since` (string) - CiviCRM `join_date` as `YYYY-MM-DD`, `''` if none
- `address` (Address) - always present as an object, fields empty when unset
- `relationships` (Relationship[]) - empty array when none

Empty string rather than null is the convention throughout. Every consumer tests
truthiness, never `null`.

### Address

Primary address only. Flattened onto the member, not a separate entity.

- `street` (string) - CiviCRM `street_address`
- `street2` (string) - CiviCRM `supplemental_address_1`
- `city` (string)
- `state` (string) - resolved label, not the id
- `postal_code` (string)
- `country` (string) - resolved label, not the id

### Relationship

One entry per active relationship, already resolved from the member's own
perspective.

- `type` (string) - the directional label, `label_a_b` or `label_b_a` depending
  on which side the member sits on
- `name` (string) - the other contact's display name

The other side may be an Organization or Household, not just an Individual.

### Cache

- `yamd_members_cache` (WordPress transient) - the fully assembled member array,
  5 minute TTL. The only persisted state the plugin creates.

There is no purge hook, so a CiviCRM edit can take up to 5 minutes to appear.
That is an accepted tradeoff, not a bug: a directory is a reference, not a live
feed, and the transient is what stops repeat page loads from re-querying CiviCRM.
Lower the TTL temporarily when testing against real data.

### Not yet mapped

Chapter, vocation, community status, and community name are CiviCRM custom
fields. Their exact API names are unknown and must be found in the API4 Explorer
before build items 8 and 9 can start.

## Tech stack

- **WordPress plugin** - the host. Runs in the same install as CiviCRM.
- **CiviCRM APIv4** - the data source, called in-process as PHP. No API key and
  no HTTP hop, because CiviCRM is a plugin in the same site.
- **PHP + WordPress REST API** - one controller class exposing one route.
- **React 18 with hooks** - mounted into the shortcode's div. State lives in
  `App` and is passed down as props; no context, reducer, or state library.
- **@wordpress/scripts** - the build tool. Compiles JSX with zero config and
  reuses WordPress's bundled React instead of shipping a second copy.
- **react-modal** - the only runtime dependency, used for the details popup.
- **Hand-written CSS** - one `src/style.css`, `yamd-` prefixed classes, no
  framework or preprocessor.
- **npm** - package manager.

No types and no test runner today. Both are planned build items.

## Monetization

Not in v1, and not planned. This is an internal tool for the organization's own
members. No billing, no tiers, no advertising.

## UI/UX

Calm and utilitarian. A member should find a person in a few seconds without
being taught anything.

There are no application routes. The plugin renders into whatever WordPress page
carries the shortcode.

- `[youth_apostles_directory]` - the shortcode. Renders `<div
  id="yamd-directory-root">`, and enqueues the built assets only on pages that
  use it.
- `GET /wp-json/yamd/v1/members` - the only endpoint. Nonce-checked via
  `X-WP-Nonce`, logged-in users only.

Screen layout, top to bottom:

1. Filter bar - search input, membership dropdown, sort dropdown
2. Toolbar - member count on the left, Print Directory button on the right
3. Card grid - 4 columns, 2 below 900px, 1 below 600px

Card layout: round avatar or placeholder, name (dominant), membership, contact
lines, then the details button pinned to the bottom.

Conventions:

- **Palette** - neutral grays on white, with brand navy (`#152b5c` on the
  derived tint `#eceef2`) as the single accent for interactive and emphasized
  elements, from the 2025 brand guide in `blueprint/reference/`. The brand's
  secondary gold (`#998631`) is not used in the app yet.
- **Feedback** - plain text for loading and error states, no spinners
- **Print** - a real output, not an afterthought. Anything new and interactive
  needs a matching hide rule in the `@media print` block.

The card is the one part explicitly up for redesign, in build item 5.

## Deployment

**Zip upload through WordPress Admin.** No CI/CD, no server build step, no
hosting provider config.

Release path:

1. `npm install && npm run build` locally
2. `npm version patch|minor|major` - syncs the plugin header and `YAMD_VERSION`
   from `package.json` via the npm `version` lifecycle hook, and commits all
   three together
3. Zip the plugin folder **including `build/`**
4. WordPress Admin > Plugins > Add New > Upload Plugin, then activate
5. Confirm the directory page still renders while logged in

Constraints:

- `build/` is gitignored, so a plain clone is not installable. The zip must come
  from a local build.
- The target site must already have CiviCRM active, with contacts in group `27`.
  A missing CiviCRM returns a `civicrm_missing` error, not a blank page.
- No environment variables and no secrets. The Members group id is a class
  constant in `includes/class-yamd-rest-controller.php`.
- No database migrations. Deactivating leaves nothing behind but an expiring
  transient.
