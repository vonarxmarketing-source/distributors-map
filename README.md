# VonArx Distributor Locator

A WordPress plugin that manages VonArx distributor locations and displays them on an interactive map via the `[vonarx_locator]` shortcode — search, category filters, "use my location" geolocation, and a per-location logo, all styled to VonArx's brand.

## Features

- **Interactive map** (Leaflet + OpenStreetMap, no API keys) with markers for every published location.
- **Top bar**: location search field, and multi-select category filter chips (Scarifiers, Shavers, Dust extractors, Shotblasters, Dust collectors, Grinders, Pneumatic tools).
- **"Use my location"**: geolocates the visitor and selects the nearest distributor, centering the map on it.
- **Responsive layout**: side-by-side map/sidebar on desktop and tablet-landscape (≥1024px), stacked with a collapsible sidebar below that.
- **Map popups vs. sidebar selection**: desktop/tablet-landscape shows a real Leaflet popup with a "View details" link into the sidebar; tablet-portrait/mobile skips the popup and highlights/scrolls to the sidebar entry directly.
- **Sidebar results list**: grouped and sorted by country, each entry showing address/email/phone (with icons) and the location's logo.
- **Admin**: a "Distributor Locations" custom post type with an address/contact meta box, a map picker (click/drag to set coordinates, or geocode the address via Nominatim), and a logo uploader (drag-and-drop-style, auto-resized to 250px wide on save).

## Local development

A local WordPress site (Docker + `wp-env`) with the plugin live-mounted, so edits show up on refresh with no build step:

```bash
npm install   # first time only
npm start     # runs `wp-env start`
```

- **Site:** http://localhost:8888
- **Sample page:** http://localhost:8888/find-a-distributor/ (has the `[vonarx_locator]` shortcode)
- **Admin:** http://localhost:8888/wp-admin/ — login `admin` / `password`
- **Manage locations:** wp-admin → Distributor Locations

Stop it with `npm run stop`. Requires Docker Desktop running.

## Project structure

```
vonarx-distributor-locator/         the actual WordPress plugin (this is what gets zipped/distributed)
  vonarx-distributor-locator.php    plugin bootstrap
  includes/
    class-post-type.php             "Distributor Location" CPT, admin meta box, logo upload handling
    class-rest-api.php              GET /wp-json/vonarx/v1/locations (search + sorted by country)
    class-shortcode.php             [vonarx_locator] shortcode markup + asset loading
    class-settings.php              shared logo upload/resize helper (Vonarx_Locator_Settings)
  admin/
    js/admin-map.js                 admin map picker (click/drag pin, address geocoding)
    js/logo-uploader.js             generic logo dropzone (used by the location meta box)
    css/settings.css
  public/
    js/locator.js                   frontend map, sidebar list, search, geolocation, popups
    css/locator.css
.wp-env.json / package.json         local dev WordPress environment (this repo's root)
test-install/                       a SEPARATE, vanilla WordPress environment for install-testing
  .wp-env.json / package.json       the plugin ZIP is installed here via `wp plugin install`,
                                     not dev-mounted — this is what catches packaging bugs
```

## Testing a real install (not the dev mount)

The dev site above mounts the plugin folder directly, which won't catch packaging bugs. To test it the way a real user would install it:

```bash
# from D:\Petko\StoreLocator — build the ZIP
python -c "
import zipfile, os
with zipfile.ZipFile('dist/vonarx-distributor-locator.zip', 'w', zipfile.ZIP_DEFLATED) as zf:
    for root, dirs, files in os.walk('vonarx-distributor-locator'):
        for f in files:
            path = os.path.join(root, f)
            zf.write(path, path.replace(os.sep, '/'))
"
```

Use Python (not PowerShell's `Compress-Archive`) — it writes backslash path separators inside the ZIP, which is invalid per the ZIP spec. Windows tools hide this, but PHP's unzip on the Linux WordPress container takes the backslashes as literal filename characters and produces a broken, double-nested plugin folder.

Then spin up the separate `test-install/` environment (`cd test-install && npm install && npm start`, port 8890) and install the ZIP via `wp plugin install <path> --activate` — the same code path as wp-admin's "Upload Plugin" button.

## How it works

- **Distributor Locations** is a custom post type: title, address, city, state, zip, country, phone, email, lat/lng, a logo, and one or more Product Groups (a `vonarx_product_group` taxonomy — category-style, with an "+ Add New Product Group" affordance right in the editor).
- The REST endpoint (`/wp-json/vonarx/v1/locations`) powers the frontend map/sidebar and is sorted by country, then name; `?search=` filters by name/city/state/country/zip.
- No API keys required anywhere — map tiles and geocoding both use OpenStreetMap (Nominatim).

## Troubleshooting (this network specifically)

`wp-env` needed two workarounds on this machine, patched directly in `node_modules/@wordpress/env` after every `npm install` (in both this repo's root and `test-install/`):

1. Its "am I online" check uses raw DNS (`dns.resolve`), which is sometimes blocked here even though normal HTTPS works — patch `lib/wordpress.js` to use `dns.lookup` instead.
2. It unconditionally clones the PHPUnit test suite from `github.com` — patch `lib/runtime/docker/download-wp-phpunit.js` to skip that clone if GitHub isn't reachable (only affects running PHPUnit tests, not browsing the site).

If `npm install` is ever re-run, these patches are lost and `wp-env start` may fail again with the same two errors depending on network conditions at the time.
