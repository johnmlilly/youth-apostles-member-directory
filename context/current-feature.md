# Current Feature Tracking

Living doc for what's being built now, what's next, and what already shipped.
Update this whenever a feature starts, changes status, or finishes.

---

## Current Feature

**Name:** Member Card Styling
**Status:** In progress
**Goals:**
- Style `MemberCard` to display: name, photo, vocation (married, consecrated, etc.), status in community, and which community they belong to
- Wire the underlying CiviCRM fields for vocation, community status, and community name (mapping + `addSelect`, per `CLAUDE.md` → "Adding a CiviCRM custom field")
- Visually distinguish/group these fields on the card
- Enhance overall UI of the cards (layout, spacing, visual hierarchy) 

---

## Upcoming Features

List in rough priority order. Move an item to "Current Feature" when work starts on it.

- [ ] **Chapter custom field wired end-to-end** — CiviCRM field mapping, `MemberCard` display, filter dropdown already scaffolded (see `CLAUDE.md` → "Adding a CiviCRM custom field"), just needs the actual CiviCRM field name and testing against real data.
- [ ] *Add Print directory option*

---

## History

Reverse-chronological log of completed actions/milestones. One line per entry: date, what happened.

- **2026-08-21** — Card redesign + membership wired end-to-end: `MemberCard` now shows photo/initials placeholder → name (dominant) → membership pill → email/phone (clickable `mailto:`/`tel:`); REST controller queries `Membership::get` (active statuses only) and returns `membership_type`; filter dropdown, search, and sort switched from `chapter`/`job_title` to `membership_type`. Untested against real CiviCRM data.
- **2026-08-10** — Core Member Directory (MVP) shipped: shortcode embed, REST endpoint + CiviCRM query, search/filter/sort, member cards, logged-in-only access, 5-min cache.