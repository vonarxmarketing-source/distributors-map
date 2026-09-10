=== VonArx Distributor Locator ===
Contributors: vonarx
Tags: store locator, map, distributors, leaflet
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.6.0
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

= 1.6.0 =
* Added a "Distributors by Country" directory above the map: continent tabs down the left (a horizontal scrollable row on tablet/mobile), each with its countries as pill sub-tabs, each country listing its distributors in a 4-column grid with a logo, a Website-or-Email button, and a "Go to Location" button that scrolls to the map and opens that marker's popup. The existing search/filter sidebar next to the map is unchanged.

= 1.5.0 =
* Fixed a mobile popup CSS bug where a location's logo could visually overflow past the popup card's rounded edge.
* Import: a blank Geolocation cell is now auto-filled by geocoding the Address/City/State/ZIP/Country columns instead of just clearing the pin; a failed lookup still saves the location and notes it in the import summary.
* Distributor Locations list: added a "Geolocation" status column flagging any location missing coordinates (which otherwise silently doesn't appear on the map).
* Import/Export: added a "Logo URL" column — set or replace a location's logo from a direct image URL during import, alongside the existing manual upload on each location's edit screen. Leaving it blank never removes an existing logo.

= 1.4.0 =
* Added Distributor Locations → Import / Export: download all locations as an .xlsx spreadsheet (Company, Product Groups, Address, City, State/Region, ZIP, Country, Geolocation, Phone, Email, Website), edit it, and re-upload to bulk-update existing locations or add new ones.
* Removed the bundled sample distributor data (data/seed-locations.json and data/logos/) and the one-time importer that seeded it on activation. New installs now start with zero locations instead of the demo set; existing sites are unaffected since that importer only ever ran once, on first activation.

= 1.3.2 =
* Mobile/tablet-portrait: tapping a map pin no longer auto-scrolls to the sidebar card. Its popup now has a "Go to Contacts" button next to "View Routes" for jumping there on demand instead.

= 1.3.1 =
* Fixed the map popup not appearing on mobile/tablet-portrait when tapping a marker — it now always shows, alongside highlighting the matching sidebar card.
* "View Routes" button hover is now a fixed dark navy (#1C2B4A) background with the text staying whatever color it already was.

= 1.3.0 =
* Widget height is now a fixed 700px (was up to 800px on mobile, full viewport height on desktop/tablet-landscape).
* Sidebar results list: each country is now a single-open accordion section instead of a flat grouped list — selecting one closes the rest automatically.

= 1.2.2 =
* Map: fixed the default (unfiltered) view actually ignoring zoom-cap changes — it was auto-fitting to every distributor worldwide on every load, which always zoomed out regardless of the cap. Now it only auto-fits once you search or filter by category; the default view stays fixed on a Europe center/zoom.

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
