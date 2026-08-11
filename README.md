# Member Directory — Setup Guide

A WordPress plugin that shows an interactive, searchable/filterable member
directory, pulling live data from CiviCRM. Built with React.

## How the pieces fit together

```
Browser (React app)
   |
   |  fetch() with a security nonce
   v
WordPress REST API  (/wp-json/yamd/v1/members)
   |
   |  PHP function call, in-process (no HTTP, no API key needed)
   v
CiviCRM (running as a plugin in the same WordPress install)
```

Because CiviCRM runs inside the same WordPress site, our PHP code can call
CiviCRM's API directly as a function call. That's simpler and more secure
than using CiviCRM's external REST API with an API key.

## File-by-file overview

| File | Purpose |
|---|---|
| `youth-apostles-member-directory.php` | Plugin entry point. Registers the REST route and the `[youth_apostles_directory]` shortcode. |
| `includes/class-yamd-rest-controller.php` | Defines the `/members` endpoint — checks login, queries CiviCRM, returns JSON. |
| `src/index.js` | React entry point — mounts the app into the page. |
| `src/App.js` | Main component — fetches data, holds search/filter/sort state. |
| `src/components/SearchFilterBar.js` | The search box + dropdowns. |
| `src/components/MemberCard.js` | Renders one member's info. |
| `src/style.css` | Styling for the grid/cards. |
| `package.json` | Defines the `npm run build` command via `@wordpress/scripts`, WordPress's official build tool (handles React/JSX compiling for you — no manual webpack config needed). |

## One-time setup

### Prerequisites
- Node.js installed on your computer (v18+). Check with `node -v`.
- Access to your WordPress site's `wp-content/plugins/` folder (via SFTP, hosting file manager, or local dev environment).
- CiviCRM already installed and active on the WordPress site, with some contacts in a "Members" group.

### Steps

1. **Copy the plugin folder** into `wp-content/plugins/youth-apostles-member-directory/` on your WordPress site (or your local dev copy of it).

2. **Install dependencies and build the React app.** In a terminal, inside the `youth-apostles-member-directory` folder:
   ```bash
   npm install
   npm run build
   ```
   This creates a `build/` folder containing the compiled JavaScript/CSS that WordPress actually loads. You need to re-run `npm run build` any time you change a file in `src/`.

   While actively developing, you can instead run `npm run start`, which watches for changes and rebuilds automatically.

3. **Activate the plugin** in WordPress Admin → Plugins → find "Member Directory" → Activate.

4. **CiviCRM Members group ID.** Already set to `27` in `includes/class-yamd-rest-controller.php`:
   ```php
   const MEMBERS_GROUP_ID = 27;
   ```
   If this ever needs to change: WordPress Admin → CiviCRM → Contacts → Manage Groups, hover the group's name — the ID is in the link (e.g. `...gid=42`).

5. **Add the directory to a page.** Edit any WordPress page and add a block/paragraph containing:
   ```
   [youth_apostles_directory]
   ```
   Visit that page while logged in — you should see the directory.

## Adding custom fields (chapter, membership type, etc.)

Right now the directory shows name, title, photo, email, and phone. To add
a CiviCRM custom field (like "Chapter"):

1. In CiviCRM, go to **Support → API4 Explorer**. This lets you visually
   build and test a `Contact.get` query and see the exact field name to
   use (it'll look like `GroupName.FieldName`).
2. In `class-yamd-rest-controller.php`, uncomment/add that field name to the
   `addSelect(...)` list.
3. In the `foreach` loop just below, add a matching line, e.g.:
   ```php
   'chapter' => $r['Membership_Info.Chapter'] ?? '',
   ```
4. In `src/components/MemberCard.js`, add a line to display it, e.g.:
   ```js
   { member.chapter && <p className="yamd-chapter">{ member.chapter }</p> }
   ```
5. Run `npm run build` again.

The filter bar's chapter dropdown will automatically populate once members
have a `chapter` value.

## Troubleshooting

- **Directory area is blank / nothing shows up:** Open your browser's dev tools (F12) → Console tab, look for errors. Most common cause: forgot to run `npm run build`, so `build/index.js` doesn't exist yet.
- **"CiviCRM does not appear to be active" error:** Confirm CiviCRM is installed and activated as a WordPress plugin.
- **Empty member list but no error:** Double check `MEMBERS_GROUP_ID` matches a real group ID that contains contacts.
- **Changes to CiviCRM data don't show immediately:** Results are cached for 5 minutes (see `set_transient` in the REST controller). Lower that value temporarily while testing, or wait it out.
