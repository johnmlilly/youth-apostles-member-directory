# Current Feature Tracking

Living doc for what's being built now, what's next, and what already shipped.
Update this whenever a feature starts, changes status, or finishes.

---

## Current Feature

**Name:** None in progress
**Status:** —
**Goals:** Pick the next item from "Upcoming Features" below.

---

## Upcoming Features

List in rough priority order. Move an item to "Current Feature" when work starts on it.

- [ ] **Chapter custom field wired end-to-end** — CiviCRM field mapping, `MemberCard` display, filter dropdown already scaffolded (see `CLAUDE.md` → "Adding a CiviCRM custom field"), just needs the actual CiviCRM field name and testing against real data.
- [ ] **Vocation / community fields** — display vocation (married, consecrated, etc.), community status, and community name on `MemberCard`. Never wired — needs the underlying CiviCRM fields identified and mapped (per `CLAUDE.md` → "Adding a CiviCRM custom field").
- [ ] *Add Print directory option*

---

## History

Reverse-chronological log of completed actions/milestones. One line per entry: date, what happened.

- **2026-08-21** — Full-details modal added: `MemberCard` gets a "View full details" button opening a `react-modal` popup with mailing address, member-since date (CiviCRM `join_date`), and relationships (spouse, parent, etc., resolved from each member's own perspective since CiviCRM relationship rows are directional). REST controller extended with an `Address` join, `join_date` on the membership query, and a new batched `Relationship`/`Contact` lookup. Two bugs caught by code review before shipping: the membership tie-break relied on SQL `ORDER BY end_date DESC` to put lifetime (NULL end_date) memberships first, but MySQL sorts NULLs last under DESC — fixed by comparing in PHP instead; and the relationship-name lookup was missing the `is_deleted` filter the rest of the file uses. Untested against real CiviCRM data — Address/Relationship field names should be verified via API4 Explorer before relying on this.
- **2026-08-21** — Card redesign + membership wired end-to-end: `MemberCard` now shows photo/initials placeholder → name (dominant) → membership pill → email/phone (clickable `mailto:`/`tel:`); REST controller queries `Membership::get` (active statuses only) and returns `membership_type`; filter dropdown, search, and sort switched from `chapter`/`job_title` to `membership_type`. Untested against real CiviCRM data.
- **2026-08-10** — Core Member Directory (MVP) shipped: shortcode embed, REST endpoint + CiviCRM query, search/filter/sort, member cards, logged-in-only access, 5-min cache.