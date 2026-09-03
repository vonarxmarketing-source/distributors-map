=== VonArx Distributor Locator ===
Contributors: vonarx
Tags: store locator, map, distributors, leaflet
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage VonArx distributor locations and display them on an interactive map with the [vonarx_locator] shortcode.

== Description ==

* Adds a "Distributor Locations" admin screen for managing name, address, phone, Product Groups, and map coordinates.
* Coordinates can be dropped/dragged on an admin map or auto-filled from the address via geocoding.
* The `[vonarx_locator]` shortcode renders a searchable list plus an interactive Leaflet/OpenStreetMap map on any page or post.
* No API keys required (OpenStreetMap tiles + Nominatim geocoding).

== Installation ==

1. Activate the plugin.
2. Go to Distributor Locations → Add New to create locations.
3. Add the `[vonarx_locator]` shortcode to any page to display the map.

== Changelog ==

= 1.2.1 =
* Sidebar cards: reduced the space above the email/phone/website buttons from 48px to 36px.
* Map: default/auto-fit zoom can now go all the way to the tile layer's own max (18) instead of being capped at 9.

= 1.2.0 =
* Map popups: replaced the "Visit us" website link with a "View Routes" button that opens Google Maps directions to that location's coordinates.

= 1.1.0 =
* Sidebar cards: dropped the physical address, added Product Group category tags, and replaced the raw email/phone/website text with circular icon buttons.
* Map popups: dropped phone numbers, added admin controls for the "Visit us" button's colors/font size.
* Mobile (below 1024px): replaced the category filter chips with a checkbox dropdown, and moved the map above the results list.
* Widget now always renders in the active theme's own font instead of loading Google Fonts/Inter; only sizes remain admin-controllable.
* Self-updates from GitHub Releases via Plugin Update Checker.

= 1.0.0 =
* Initial release.
