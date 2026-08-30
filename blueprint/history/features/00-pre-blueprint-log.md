# 00 - Pre-Blueprint build log

Features 1-4 shipped before the Blueprint was adopted, so they have no
individual spec to archive. This is their record, carried over verbatim from the
pre-Blueprint `context/current-feature.md` when `/feature` first needed to
overwrite that file.

Reverse-chronological. Later features are archived here one file each, by
`/complete`.

- **2026-08-22** — Print Directory added: a "Print Directory" button calls `window.print()`; a new `@media print` stylesheet hides the filter bar, print button, and per-card "View full details" buttons, reflows the grid to 2 columns, and prevents cards from splitting across a page break. No new logic needed for filter-respecting scope — the grid already only renders `filteredMembers`, so print just reflects whatever's on screen.
- **2026-08-21** — Full-details modal added: `MemberCard` gets a "View full details" button opening a `react-modal` popup with mailing address, member-since date (CiviCRM `join_date`), and relationships (spouse, parent, etc., resolved from each member's own perspective since CiviCRM relationship rows are directional). REST controller extended with an `Address` join, `join_date` on the membership query, and a new batched `Relationship`/`Contact` lookup. Two bugs caught by code review before shipping: the membership tie-break relied on SQL `ORDER BY end_date DESC` to put lifetime (NULL end_date) memberships first, but MySQL sorts NULLs last under DESC — fixed by comparing in PHP instead; and the relationship-name lookup was missing the `is_deleted` filter the rest of the file uses. Untested against real CiviCRM data — Address/Relationship field names should be verified via API4 Explorer before relying on this.
- **2026-08-21** — Card redesign + membership wired end-to-end: `MemberCard` now shows photo/initials placeholder → name (dominant) → membership pill → email/phone (clickable `mailto:`/`tel:`); REST controller queries `Membership::get` (active statuses only) and returns `membership_type`; filter dropdown, search, and sort switched from `chapter`/`job_title` to `membership_type`. Untested against real CiviCRM data.
- **2026-08-10** — Core Member Directory (MVP) shipped: shortcode embed, REST endpoint + CiviCRM query, search/filter/sort, member cards, logged-in-only access, 5-min cache.
