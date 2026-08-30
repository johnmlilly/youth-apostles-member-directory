# Feature: Card visual refresh

**From build-plan:** feature 5
**Status:** not started

## Goal

Restyle `MemberCard` without changing a single piece of information it shows.
Two changes: the membership label loses its solid indigo fill and becomes an
outlined pill, and stroke icons appear beside membership, email, and phone.

The card is the one surface every member looks at, and the filled pill currently
pulls more attention than the name above it. Lightening it and adding icons makes
a column of cards scannable without adding anything to read.

## Design reference

**Brand guide:** `blueprint/reference/youth-apostles-brand-guide-2025.pdf`

The palette in the app was never the brand palette. The guide's colors:

| Role | Value | Notes |
|---|---|---|
| Primary | `#152b5c` | Brand navy, read from the guide's vector fill. Replaces the invented indigo `#3730a3`. |
| Secondary | `#998631` | Brand gold. Deliberately unused here. |
| Light tint | `#eceef2` | **Derived, not from the guide.** Navy at 8% on white, replacing `#eef2ff`. |

The guide also specifies typography (Playfair Display and EB Garamond for serif,
Montserrat and Open Sans for sans). The app declares no fonts at all today and
inherits the WordPress theme's. **Out of scope here**, and worth its own build
item if you want it.

No mockup for the card itself. The direction was chosen from options during
`/feature`:

- **Membership** - outlined pill. Same shape and size as today, 1px navy border,
  transparent interior, navy text. Keeps the existing card rhythm.
- **Icons** - outline/stroke style, roughly 1.5px, drawn inline as SVG and
  colored with `currentColor`. No icon library, so no new dependency.

```
        ( photo )

      Maria Delgado

     .-------------.
     | [B] FULL MEMBER |   <- 1px navy border, transparent inside
     '-------------'

   [envelope] maria@example.org
   [phone]    (703) 555-0142

     [ View full details ]
```

## In scope

- Every accent color in `src/style.css` moves from the invented indigo to the
  brand navy: 12 declarations across the print button, membership label, contact
  hover, details trigger, and modal detail headings.
- `.yamd-membership` becomes an outlined pill: transparent background, 1px navy
  border, navy text. Existing size, radius, casing, and letterspacing stay.
- A small inline-SVG icon set: envelope, phone handset, and a membership badge.
  Stroke style, `currentColor`, sized from CSS.
- Icons rendered beside membership, email, and phone, aligned with the card's
  existing centered layout.
- Icons marked `aria-hidden="true"`. The adjacent text already carries the
  meaning, so they must not add noise for screen readers.
- The `@media print` block keeps working, and the outlined pill prints without a
  block of indigo ink.

## Out of scope

- Any change to what the card displays. No new fields, no removed fields.
- The avatar, the name, the details button, and the modal. Untouched.
- The filter bar and the toolbar, beyond their accent color changing with the
  rest of the palette.
- Typography. The brand's fonts are not adopted here.
- The brand's secondary gold. Nothing in the app uses it yet.
- The card grid, its breakpoints, and card dimensions.
- Anything in the REST controller or the payload shape. This is CSS and JSX only.
- An icon library. Icons are hand-written inline SVG.

## Build loop

Build one step at a time, never the whole feature at once.

1. Plan mode lays out the step before any code.
2. The AI implements just that step.
3. It shows the diff (not full files); you read it and understand it.
4. You approve, then choose whether to commit a checkpoint or roll straight on.
   Checkpoints are optional; `/complete` makes the real feature-level commit at
   the end.

Never accept a step you haven't read. If a diff is too big to review, the step
was too big, so split it.

## Build steps

- [ ] **Step 1 - Adopt the brand palette** - `src/style.css` only, mechanical.
  Replace all 8 occurrences of `#3730a3` with `#152b5c` and all 4 of `#eef2ff`
  with `#eceef2`. Touches the print button, membership pill, contact link hover,
  details trigger, and modal detail headings. No layout or structural change.
  *Done when:* no `#3730a3` or `#eef2ff` remains in the file, every element that
  was indigo is now navy, hover states still invert correctly, and the card grid
  looks otherwise identical.

- [ ] **Step 2 - Outline the membership pill** - `src/style.css` only. Swap
  `.yamd-membership`'s tint background for `background: transparent` plus a 1px
  `#152b5c` border, and adjust padding so the bordered pill keeps its current
  outer size. *Done when:* the pill renders as navy text inside a navy outline
  with the card's white showing through, the card's overall height is visually
  unchanged, and a print preview shows the outline rather than a filled block.

- [ ] **Step 3 - Icons on email and phone** - add `src/components/Icon.js`
  exporting stroke SVGs for envelope and phone, then render each inside its
  existing conditional in `MemberCard.js` so a member with no email or no phone
  still renders nothing at all. Add the CSS to sit the icon beside the text
  without breaking the card's centered alignment. *Done when:* both contact rows
  show an icon, the `mailto:` and `tel:` links still work, a member missing one
  or both fields shows no orphaned icon, and the rows stay centered.

- [ ] **Step 4 - Icon on the membership pill, and print check** - add the badge
  SVG to `Icon.js` and render it inside the outlined pill, before the label. Then
  verify the whole card in a print preview. *Done when:* the icon sits inside the
  border and is vertically centered against the text, a member with no
  `membership_type` renders no pill and no icon, and the print preview shows all
  three icons and the outlined pill with the interactive chrome still hidden.

## Files / areas

| File | Change |
|---|---|
| `src/style.css` | Brand palette swap, `.yamd-membership` outline treatment, icon sizing and alignment rules |
| `src/components/Icon.js` | New. Inline stroke SVGs: envelope, phone, badge. |
| `src/components/MemberCard.js` | Render icons inside the three existing conditionals |

## Data / contracts

None. No payload, type, or API shape changes. The card reads exactly the fields
it reads today: `membership_type`, `email`, `phone`.

## Testing

**No test runner is configured**, so there is no test gate for this feature, and
none is needed: this is entirely rendering and styling, which
`coding-standards.md` explicitly puts out of unit-test scope. No `Browser tests`
command is declared either.

Evidence is the build plus direct browser observation:

- `npm run build` must pass after every step. WordPress serves `build/`, not
  `src/`, so an unbuilt change looks like no change at all.
- Load a page carrying `[youth_apostles_directory]` while logged in and look at
  the cards.
- Open the browser print preview and confirm the card still reads correctly on
  paper.

Cases worth clicking through, since they are conditional branches rather than
styling:

| Case | Expected |
|---|---|
| Member with membership, email, and phone | Three icons, outlined pill |
| Member with no `membership_type` | No pill, no badge icon |
| Member with no email or no phone | That row absent entirely, no orphaned icon |
| Print preview | Outlined pill, icons present, filter bar and buttons hidden |

This cannot be verified from the repo alone. It needs the plugin installed on a
WordPress site with CiviCRM and a logged-in session.

## Notes for the AI

- **Client-side only.** No PHP, no REST controller, no CiviCRM.
- **Icons go inside the existing conditionals**, not beside them. `member.email`
  already guards its row; putting an icon outside that guard would render a
  floating icon for a member with no email.
- **The card is centered.** `.yamd-card` sets `align-items: center` and
  `text-align: center`. An icon plus text needs to stay centered as a unit, so
  use inline-flex on the row rather than floating the icon.
- **Icons are decorative.** `aria-hidden="true"` on every one. The text beside
  them already says what they mean.
- **`currentColor` for every stroke**, so an icon inherits the color of the text
  it sits beside and the hover states keep working without extra rules.
- **Navy `#152b5c` is the only accent**, with `#eceef2` as its light tint. Do
  not reintroduce indigo and do not add the brand gold. The error red `#b91c1c`
  and the gray ramp stay as they are.
- The palette swap is step 1 and is mechanical. Do it as a clean find-and-replace
  so the diff is trivially reviewable, before any structural change lands.
- Follow the file's conventions: tabs, WordPress JS spacing (`{ member }`,
  `( a, b )`), `yamd-` prefixed classes, BEM double-dash for modifiers.
- Comments follow the project's teaching style. Explain a non-obvious SVG
  attribute or an alignment trick; do not narrate the JSX.
- Keep the `@media print` block honest. Nothing added here is interactive, so no
  new hide rules should be needed, but confirm rather than assume.
