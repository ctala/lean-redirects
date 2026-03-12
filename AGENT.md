# Lean Redirects — Agent Reference

*Machine-readable documentation for AI agents managing redirects via this plugin.*

## Identity

- **Slug:** `lean-redirects`
- **Version:** 1.2.0
- **Text Domain:** `lean-redirects`
- **Requires:** WordPress 6.2+, PHP 7.4+
- **Entry File:** `lean-redirects.php`
- **Settings Page:** `options-general.php?page=lean-redirects`
- **DB Table:** `{prefix}lean_redirects` (custom, indexed)
- **REST Namespace:** `lean-redirects/v1`
- **Uninstall:** Drops DB table on plugin deletion

## Database Schema

Table: `{wp_prefix}lean_redirects`

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT | Primary key |
| `url_from` | VARCHAR(500) | — | Source path (e.g. `/old-page/`) |
| `url_to` | VARCHAR(500) | — | Destination URL or path |
| `code` | SMALLINT | `301` | HTTP status: `301`, `302`, or `307` |
| `active` | TINYINT(1) | `1` | `1` = active, `0` = disabled |
| `hits` | BIGINT UNSIGNED | `0` | Number of times this redirect fired |
| `note` | VARCHAR(255) | `""` | Optional note/label |
| `created_at` | DATETIME | `CURRENT_TIMESTAMP` | Creation timestamp |

**Indexes:**
- `PRIMARY KEY (id)`
- `UNIQUE KEY idx_from (url_from(191))` — ensures one redirect per source path
- `KEY idx_active (active)` — fast filtered queries

## REST API

**Base:** `/wp-json/lean-redirects/v1`
**Auth:** Requires `manage_options` capability (use Application Passwords or cookie auth)

### GET /redirects

List all redirects.

```bash
curl -u user:app_password \
  https://example.com/wp-json/lean-redirects/v1/redirects
```

**Response:** Array of redirect objects

```json
[
  {
    "id": 1,
    "url_from": "/old-page/",
    "url_to": "/new-page/",
    "code": 301,
    "active": 1,
    "hits": 42,
    "note": "Migration Q1",
    "created_at": "2026-01-15 10:30:00"
  }
]
```

### POST /redirects

Add or update a redirect (upsert by `url_from`).

```bash
curl -X POST -u user:app_password \
  -H "Content-Type: application/json" \
  -d '{"from":"/old/","to":"/new/","code":301,"note":"Added by agent"}' \
  https://example.com/wp-json/lean-redirects/v1/redirects
```

**Parameters:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `from` | string | ✅ | — | Source path |
| `to` | string | ✅ | — | Destination URL or path |
| `code` | integer | ❌ | `301` | `301`, `302`, or `307` |
| `note` | string | ❌ | `""` | Optional note |

**Response:** `{"ok": true, "total": 98}`

**Note:** Uses `REPLACE INTO` — if `url_from` already exists, the row is replaced (upsert behavior).

### DELETE /redirects

Delete a redirect by source path.

```bash
curl -X DELETE -u user:app_password \
  -H "Content-Type: application/json" \
  -d '{"from":"/old/"}' \
  https://example.com/wp-json/lean-redirects/v1/redirects
```

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `from` | string | ✅ | Source path to delete |

**Response:** `{"ok": true, "deleted": 1}`

## WP-CLI

```bash
# Install and activate
wp plugin install lean-redirects --activate

# Check if active
wp plugin is-active lean-redirects && echo "active" || echo "inactive"

# Get version
wp plugin get lean-redirects --field=version

# Direct DB: list all redirects
wp db query "SELECT * FROM wp_lean_redirects ORDER BY hits DESC"

# Direct DB: add a redirect
wp db query "REPLACE INTO wp_lean_redirects (url_from, url_to, code, active, note) VALUES ('/old/', '/new/', 301, 1, 'Added via CLI')"

# Direct DB: count active
wp db query "SELECT COUNT(*) AS total FROM wp_lean_redirects WHERE active = 1"

# Direct DB: disable a redirect
wp db query "UPDATE wp_lean_redirects SET active = 0 WHERE url_from = '/old/'"

# Direct DB: delete a redirect
wp db query "DELETE FROM wp_lean_redirects WHERE url_from = '/old/'"
```

## CSV Import/Export

### Import Format

```csv
/old-page/,/new-page/,301
/blog/old-post/,/blog/new-post/
/temp/,https://external.com/,302
```

- Column 1: `url_from` (required)
- Column 2: `url_to` (required)
- Column 3: `code` (optional, defaults to 301)
- Uses `REPLACE INTO` — existing `url_from` entries are updated

### Programmatic Import via REST

```bash
# Batch import via loop
while IFS=, read -r from to code; do
  curl -s -X POST -u user:app_password \
    -H "Content-Type: application/json" \
    -d "{\"from\":\"$from\",\"to\":\"$to\",\"code\":${code:-301}}" \
    https://example.com/wp-json/lean-redirects/v1/redirects
done < redirects.csv
```

## Redirect Matching Logic

```
1. Request comes in → template_redirect hook (priority 1)
2. Parse REQUEST_URI → extract path → strip trailing slash
3. Query: SELECT WHERE active=1 AND (url_from = path OR url_from = path/)
4. If match → increment hits → redirect with wp_safe_redirect (internal) or wp_redirect (external)
5. If no match → do nothing (zero overhead)
```

**Key behaviors:**
- Matches with and without trailing slash
- Relative `url_to` values are prepended with `home_url()`
- External redirects use `wp_redirect` (filtered by `allowed_redirect_hosts`)
- Hook priority `1` = runs before most other plugins

## Common Agent Tasks

### Task: Migrate redirects from another plugin

```bash
# Export from SmartCrawl (stored in wp_options as serialized array)
wp option get wds-redirections --format=json | jq -r 'to_entries[] | "\(.key),\(.value)"' > redirects.csv

# Import into Lean Redirects
while IFS=, read -r from to; do
  curl -s -X POST -u user:app_password \
    -H "Content-Type: application/json" \
    -d "{\"from\":\"$from\",\"to\":\"$to\",\"code\":301,\"note\":\"Migrated from SmartCrawl\"}" \
    https://site.com/wp-json/lean-redirects/v1/redirects
done < redirects.csv
```

### Task: Add redirect after slug change

```bash
curl -X POST -u user:app_password \
  -H "Content-Type: application/json" \
  -d '{"from":"/old-slug/","to":"/new-slug/","code":301,"note":"Slug change 2026-03-12"}' \
  https://site.com/wp-json/lean-redirects/v1/redirects
```

### Task: Audit — find unused redirects (0 hits)

```bash
wp db query "SELECT url_from, url_to, created_at FROM wp_lean_redirects WHERE hits = 0 AND active = 1 ORDER BY created_at"
```

### Task: Bulk disable all redirects

```bash
wp db query "UPDATE wp_lean_redirects SET active = 0"
```

### Task: Deploy to a new WordPress site

```bash
# 1. Download and install
wget https://assets.cristiantala.com/tools/lean-redirects.zip -O /tmp/lean-redirects.zip
wp plugin install /tmp/lean-redirects.zip --activate

# 2. Table is auto-created on activation

# 3. Import redirects if needed
curl -X POST -u user:app_password \
  -H "Content-Type: application/json" \
  -d '{"from":"/example/","to":"/new-example/","code":301}' \
  https://site.com/wp-json/lean-redirects/v1/redirects
```

## File Structure

```
lean-redirects/
├── lean-redirects.php     # Everything: activation, frontend redirect, admin, REST API
├── views/
│   └── admin-page.php     # Admin page HTML template
├── assets/                # WP.org assets (banners, screenshots)
├── languages/             # i18n .pot/.po/.mo files
├── index.php              # Silence is golden
├── uninstall.php          # Drops DB table on deletion
├── AGENT.md               # This file (agent reference)
├── README.md              # Human documentation
├── readme.txt             # WordPress.org format
├── CHANGELOG.md           # Version history
└── LICENSE                # GPLv2 or later
```

## Performance

- **1 indexed query** per frontend request (~0.5ms with 1,000 redirects)
- **Zero JavaScript** on frontend
- **No wp_options bloat** — uses dedicated table
- **No autoload** — nothing loaded on non-redirect requests except the hook registration

---

*Last updated: 2026-03-12 — v1.2.0*
