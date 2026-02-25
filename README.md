# Lean Redirects

The redirect plugin that does nothing else.

**One indexed DB query. Zero JavaScript. No bloat.**

## Why?

### Why a separate plugin?

Most SEO plugins (Yoast, Rank Math, AIOSEO) include redirects as a built-in feature. The problem? **You can't switch SEO plugins without losing all your redirects.** That's vendor lock-in.

Lean Redirects keeps your redirects independent. Switch SEO plugins, switch themes, redesign your entire site — your redirects stay exactly where they are, in their own table, managed by ~475 lines of code that do nothing else.

**This plugin is for people who care about what runs on every single page load.** Every plugin you install adds weight. Most redirect plugins load admin classes, analytics collectors, and upsell banners on every request — even on the frontend. Lean Redirects loads one function, runs one indexed query, and gets out of the way.

> **What's LOC?** Lines of Code — the total number of lines in the plugin's PHP files.
> Fewer lines = less complexity, fewer bugs, easier to audit. It's a rough but honest measure of how much a plugin is doing behind the scenes.

| Plugin | Storage | LOC (est.) | Extras |
|--------|---------|------------|--------|
| Redirection (2M+ installs) | Custom table | ~15,000+ | 404 logging, analytics, Apache/Nginx export, groups, regex, 10 import formats |
| Safe Redirect Manager | Custom Post Type | ~3,000 | Regex, wildcards, hook-heavy |
| Simple 301 Redirects | wp_options | ~500 | Converted to BetterLinks upsell |
| WP 301 Redirects | Custom table | ~5,000 | Freemium, licenses, 404 log |
| **Lean Redirects** | **Indexed custom table** | **~475** | **Nothing. Just redirects.** |

## Features

- ✅ 301, 302, 307 redirects
- ✅ One indexed DB query per request (~0.5ms)
- ✅ Hit counter
- ✅ Search & pagination
- ✅ Notes per redirect
- ✅ Toggle on/off without deleting
- ✅ CSV import/export
- ✅ REST API (`GET/POST/DELETE`)
- ✅ Clean uninstall
- ✅ Zero JavaScript on frontend
- ✅ No external dependencies
- ✅ No premium version

## What it doesn't do (on purpose)

- ❌ No 404 error logging
- ❌ No regex matching
- ❌ No wildcard matching
- ❌ No redirect chains detection
- ❌ No upsells
- ❌ No JavaScript frameworks
- ❌ No custom CSS files

## Installation

### From WordPress.org

1. Go to **Plugins → Add New**
2. Search for "Lean Redirects"
3. Install and activate

### Manual

1. Download the latest release
2. Upload to `/wp-content/plugins/lean-redirects/`
3. Activate in WordPress admin

### WP-CLI

```bash
wp plugin install lean-redirects --activate
```

## Usage

Go to **Settings → Redirects** in your WordPress admin.

### REST API

```bash
# List all redirects
curl -u user:app_password https://example.com/wp-json/lean-redirects/v1/redirects

# Add a redirect
curl -X POST -u user:app_password \
  -H "Content-Type: application/json" \
  -d '{"from":"/old/","to":"/new/","code":301,"note":"Migration"}' \
  https://example.com/wp-json/lean-redirects/v1/redirects

# Delete a redirect
curl -X DELETE -u user:app_password \
  -H "Content-Type: application/json" \
  -d '{"from":"/old/"}' \
  https://example.com/wp-json/lean-redirects/v1/redirects
```

### CSV Import

Format: `from,to,code` (code is optional, defaults to 301)

```
/old-page/,/new-page/,301
/blog/old-post/,/blog/new-post/
/temp/,https://example.com/,302
```

## Performance

With 1,000 redirects, Lean Redirects adds ~0.5ms per page load.

The query is a simple `SELECT` on an indexed `VARCHAR(191)` column. No deserialization, no CPT queries, no regex evaluation.

## Requirements

- WordPress 5.6+
- PHP 7.4+

## License

GPLv2 or later. See [LICENSE](LICENSE).

---

Made with ❤️ from Chile by [cristiantala.com](https://cristiantala.com)
