=== Lean Redirects ===
Contributors: ctala
Tags: redirect, 301, 302, seo, lightweight
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The redirect plugin that does nothing else. One indexed DB query. Zero JavaScript. No bloat.

== Description ==

Lean Redirects is the simplest way to manage 301, 302, and 307 redirects in WordPress.

Most redirect plugins come loaded with features you don't need: 404 logging, analytics dashboards, regex engines, import wizards for 10 different formats, and upsells to premium versions. All of that adds overhead to every single page load on your site.

**Lean Redirects takes a different approach: do one thing, do it well, and get out of the way.**

= How it works =

* Stores redirects in a dedicated database table with an indexed column
* Processes redirects with a single indexed query on `template_redirect`
* No deserialization of large option blobs
* No custom post type queries
* No JavaScript loaded on the frontend
* No external API calls
* No tracking, no analytics, no phone-home

= Features =

* **301, 302, 307 redirects** — permanent, temporary, and strict temporary
* **Hit counter** — see which redirects are actually being used
* **Search** — find redirects quickly as your list grows
* **Pagination** — handles hundreds of redirects without slowing down the admin
* **Notes** — add context to each redirect (migration batch, ticket number, etc.)
* **Toggle on/off** — disable a redirect without deleting it
* **CSV Import/Export** — bulk manage redirects with simple `from,to,code` format
* **REST API** — programmatically manage redirects (`GET/POST/DELETE /wp-json/lean-redirects/v1/redirects`)
* **Settings link** — quick access from the Plugins page
* **Clean uninstall** — removes the table when you delete the plugin

= What it doesn't do (on purpose) =

* ❌ No 404 error logging (use a dedicated tool if you need this)
* ❌ No regex matching (keeps the query simple and fast)
* ❌ No wildcard matching
* ❌ No redirect chains detection
* ❌ No premium version or upsells
* ❌ No JavaScript frameworks
* ❌ No custom CSS files
* ❌ No external dependencies

= Performance =

On a site with 1,000 redirects, Lean Redirects adds approximately **0.5ms** to each page load. Compare this to plugins that deserialize large option arrays (~5-15ms) or query custom post types (~2-8ms).

The secret is simple: one `SELECT` on an indexed `VARCHAR(191)` column. That's it.

== Installation ==

1. Upload the `lean-redirects` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to **Settings → Redirects** to manage your redirects

== Frequently Asked Questions ==

= Does this work with custom post types and pages? =

Yes. It intercepts any request at the `template_redirect` hook, before WordPress renders anything. It works with any URL path.

= Can I redirect to external URLs? =

Yes. Put the full URL (including `https://`) in the "To" field.

= What happens if I deactivate the plugin? =

Your redirects stay in the database. Reactivate and they're back.

= What happens if I delete the plugin? =

The database table is removed. Export your redirects first if you want to keep them.

= Is it compatible with caching plugins? =

Yes. Since redirects happen at the PHP level before any output, they work with all major caching plugins. For best performance with page caching, ensure your cache plugin doesn't cache redirected URLs.

= Can I use the REST API? =

Yes. Requires authentication with a user that has `manage_options` capability.

* `GET /wp-json/lean-redirects/v1/redirects` — list all redirects
* `POST /wp-json/lean-redirects/v1/redirects` — add a redirect (`from`, `to`, `code`, `note`)
* `DELETE /wp-json/lean-redirects/v1/redirects` — delete a redirect (`from`)

== Screenshots ==

1. Main admin screen showing redirects with hit counter and search
2. Adding a new redirect
3. CSV import/export

== Changelog ==

= 1.0.0 =
* Initial release
* 301/302/307 redirects with indexed database table
* Admin UI under Settings → Redirects
* Search, pagination, toggle, delete
* CSV import/export
* REST API (GET/POST/DELETE)
* Hit counter
* Notes field
* Clean uninstall

== Upgrade Notice ==

= 1.0.0 =
Initial release.
