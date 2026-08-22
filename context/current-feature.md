# Current Feature Tracking

Living doc for what's being built now, what's next, and what already shipped.
Update this whenever a feature starts, changes status, or finishes.

---

## Current Feature

**Name:** Print Member Directory
**Status:** Done
**Goals:**
- "Print Directory" button next to the member count, calls `window.print()` — native browser print, no new page or dependency
- Printing respects whatever's currently searched/filtered, since it prints the same DOM the screen shows (no extra state needed)
- Print output shows only the visible card info (name, membership, email, phone) — search bar, filter dropdowns, print button, and each card's "View full details" button are hidden via `@media print`
- Grid reflows to 2 columns for paper; cards don't split across a page break

---

## Upcoming Features

List in rough priority order. Move an item to "Current Feature" when work starts on it.

- [ ] **Card Visual Refresh** — restyle `MemberCard` (same info shown, different look): add icons next to the important info fields (membership, email, phone); remove the background color on `.yamd-membership` (currently a filled indigo pill), find a different treatment that doesn't use a solid background fill.
- [ ] **Chapter custom field wired end-to-end** — CiviCRM field mapping, `MemberCard` display, filter dropdown already scaffolded (see `CLAUDE.md` → "Adding a CiviCRM custom field"), just needs the actual CiviCRM field name and testing against real data.
- [ ] **Vocation / community fields** — display vocation (married, consecrated, etc.), community status, and community name on `MemberCard`. Never wired — needs the underlying CiviCRM fields identified and mapped (per `CLAUDE.md` → "Adding a CiviCRM custom field").

---

## History

Reverse-chronological log of completed actions/milestones. One line per entry: date, what happened.

- **2026-08-22** — Print Directory added: a "Print Directory" button calls `window.print()`; a new `@media print` stylesheet hides the filter bar, print button, and per-card "View full details" buttons, reflows the grid to 2 columns, and prevents cards from splitting across a page break. No new logic needed for filter-respecting scope — the grid already only renders `filteredMembers`, so print just reflects whatever's on screen.
- **2026-08-21** — Full-details modal added: `MemberCard` gets a "View full details" button opening a `react-modal` popup with mailing address, member-since date (CiviCRM `join_date`), and relationships (spouse, parent, etc., resolved from each member's own perspective since CiviCRM relationship rows are directional). REST controller extended with an `Address` join, `join_date` on the membership query, and a new batched `Relationship`/`Contact` lookup. Two bugs caught by code review before shipping: the membership tie-break relied on SQL `ORDER BY end_date DESC` to put lifetime (NULL end_date) memberships first, but MySQL sorts NULLs last under DESC — fixed by comparing in PHP instead; and the relationship-name lookup was missing the `is_deleted` filter the rest of the file uses. Untested against real CiviCRM data — Address/Relationship field names should be verified via API4 Explorer before relying on this.
- **2026-08-21** — Card redesign + membership wired end-to-end: `MemberCard` now shows photo/initials placeholder → name (dominant) → membership pill → email/phone (clickable `mailto:`/`tel:`); REST controller queries `Membership::get` (active statuses only) and returns `membership_type`; filter dropdown, search, and sort switched from `chapter`/`job_title` to `membership_type`. Untested against real CiviCRM data.
- **2026-08-10** — Core Member Directory (MVP) shipped: shortcode embed, REST endpoint + CiviCRM query, search/filter/sort, member cards, logged-in-only access, 5-min cache.