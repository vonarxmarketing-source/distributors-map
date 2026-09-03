(function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		var mapEl = document.getElementById( 'vonarx-map' );
		if ( ! mapEl || typeof L === 'undefined' ) {
			return;
		}

		var map = L.map( mapEl ).setView( [ 39.5, -98.35 ], 4 );
		L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap contributors',
			maxZoom: 18,
		} ).addTo( map );

		var storeListEl = document.getElementById( 'vonarx-store-list' );
		var markers = {};

		/**
		 * Same breakpoint the sidebar layout switches on (min-width: 1024px in
		 * locator.css). Read live via matchMedia rather than cached once, so
		 * marker/popup behavior stays correct across resize/rotation without
		 * a reload.
		 */
		var desktopMql = window.matchMedia( '(min-width: 1024px)' );

		var sidebar = document.getElementById( 'vonarx-locator-sidebar' );
		var sidebarToggle = document.getElementById( 'vonarx-sidebar-toggle' );

		function escapeHtml( str ) {
			var div = document.createElement( 'div' );
			div.textContent = str || '';
			return div.innerHTML;
		}

		function logoImgHtml( store, className ) {
			if ( ! store.logo ) {
				return '';
			}
			return '<img class="' + className + '" src="' + escapeHtml( store.logo ) + '" alt="" />';
		}

		function categoryLabel( slug ) {
			var categories = ( window.vonarxLocatorSettings && vonarxLocatorSettings.categories ) || {};
			return categories[ slug ] || slug;
		}

		function storeDetailsHtml( store ) {
			// Logo floated (not flexed alongside just the name) so the rest of
			// the text — name, categories, address — wraps around it.
			var html = logoImgHtml( store, 'vonarx-popup-logo' );
			html += '<h3>' + escapeHtml( store.name ) + '</h3>';
			if ( store.product_groups && store.product_groups.length ) {
				html += '<p class="vonarx-popup-categories">' +
					escapeHtml( store.product_groups.map( categoryLabel ).join( ', ' ) ) +
					'</p>';
			}
			html += '<p>' + escapeHtml( store.address ) + '</p>';
			html += '<p>' + escapeHtml( store.city ) + ', ' + escapeHtml( store.state ) + ' ' + escapeHtml( store.zip ) + '</p>';
			if ( store.website ) {
				html += '<a href="' + escapeHtml( store.website ) + '" target="_blank" rel="noopener noreferrer" class="vonarx-popup-visit-us">' +
					'Visit us</a>';
			}
			return html;
		}

		function getSidebarItem( id ) {
			return storeListEl.querySelector( '.vonarx-locator__item[data-id="' + id + '"]' );
		}

		/**
		 * Highlights (and optionally scrolls/expands to) a store's sidebar
		 * entry. Returns false if the store has no sidebar counterpart, so
		 * callers can fall back to a popup instead.
		 */
		function highlightSidebarEntry( id, opts ) {
			opts = opts || {};
			var item = getSidebarItem( id );
			if ( ! item ) {
				return false;
			}

			document.querySelectorAll( '.vonarx-locator__item' ).forEach( function ( el ) {
				el.classList.toggle( 'active', el === item );
			} );

			if ( opts.expand && sidebar && sidebarToggle && sidebar.classList.contains( 'is-collapsed' ) ) {
				sidebar.classList.remove( 'is-collapsed' );
				sidebarToggle.setAttribute( 'aria-expanded', 'true' );
			}

			if ( opts.scroll ) {
				item.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			}

			return true;
		}

		/**
		 * Desktop/tablet-landscape (>=1024px): a real, compact Leaflet popup
		 * (280-320px wide, capped height with internal scroll via Leaflet's
		 * own maxHeight option) with a "View details" link into the sidebar
		 * — only shown if the store actually has a sidebar entry to jump to.
		 */
		function openDesktopPopup( store, marker ) {
			var html = '<div class="vonarx-popup-body">' + storeDetailsHtml( store ) + '</div>';

			marker.unbindPopup();
			marker.bindPopup( html, {
				className: 'vonarx-popup vonarx-popup--desktop',
				minWidth: 280,
				maxWidth: 320,
				maxHeight: 240,
			} );
			marker.openPopup();
		}

		/**
		 * Tablet-portrait/mobile (<1024px): no floating popup on marker tap —
		 * the sidebar entry is highlighted/scrolled to instead. This fallback
		 * popup only appears for a marker with no sidebar counterpart to jump
		 * to (e.g. filtered out of the list but still shown on the map).
		 */
		function openMobileFallbackPopup( store, marker ) {
			var html = '<div class="vonarx-popup-body">' + storeDetailsHtml( store ) + '</div>';

			marker.unbindPopup();
			marker.bindPopup( html, {
				className: 'vonarx-popup vonarx-popup--mobile-fallback',
			} );
			marker.openPopup();
		}

		function handleStoreSelected( store, marker, triggeredByMarker ) {
			if ( desktopMql.matches ) {
				openDesktopPopup( store, marker );
				// Mirror the selection in the sidebar list alongside the
				// popup — same highlight/scroll the non-desktop path below
				// uses. No-ops harmlessly if this store has no sidebar entry
				// to jump to (e.g. filtered out of the list).
				highlightSidebarEntry( store.id, { scroll: true, expand: true } );
				// setView AFTER opening the popup: Leaflet's own openPopup()
				// auto-pans the map to fit the popup on screen, which would
				// otherwise shift the marker away from true center. Doing our
				// own centering last guarantees it wins.
				map.setView( marker.getLatLng(), 12, { animate: true } );
				return;
			}

			map.setView( marker.getLatLng(), 12, { animate: true } );

			if ( ! triggeredByMarker ) {
				highlightSidebarEntry( store.id, { scroll: false, expand: false } );
				return;
			}

			var hasSidebarEntry = highlightSidebarEntry( store.id, { scroll: true, expand: true } );
			if ( ! hasSidebarEntry ) {
				openMobileFallbackPopup( store, marker );
				map.setView( marker.getLatLng(), 12, { animate: true } );
			}
		}

		// Close any open popup when crossing the breakpoint so a desktop-style
		// popup doesn't linger after resizing into the mobile layout, or vice versa.
		if ( typeof desktopMql.addEventListener === 'function' ) {
			desktopMql.addEventListener( 'change', function () {
				map.closePopup();
			} );
		} else if ( typeof desktopMql.addListener === 'function' ) {
			desktopMql.addListener( function () {
				map.closePopup();
			} );
		}

		// Same icon markup as PHP's icon() method (class-shortcode.php), kept
		// in sync by hand since these list items are rendered client-side.
		var ICONS = {
			mail: '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="2.5" y="4.5" width="15" height="11" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M3.5 5.5L10 11l6.5-5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			phone: '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 3h2.2l1 3.6-1.7 1.2a9 9 0 004.7 4.7l1.2-1.7 3.6 1V15a2 2 0 01-2.2 2 14 14 0 01-10.8-10.8A2 2 0 015 3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
			website: '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M3 10h14M10 3c2.5 2 2.5 12 0 14M10 3c-2.5 2-2.5 12 0 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
		};

		function storeCardHtml( store ) {
			var content = '<h3>' + escapeHtml( store.name ) + '</h3>';

			if ( store.product_groups && store.product_groups.length ) {
				content += '<p class="vonarx-locator__item-categories">' +
					escapeHtml( store.product_groups.map( categoryLabel ).join( ', ' ) ) +
					'</p>';
			}
			// Icon-only circular buttons rather than the raw address/value as
			// text — aria-label/title carry the accessible name ("Email" etc.)
			// since there's no room to also show it visually in the circle.
			var actions = '';
			if ( store.email ) {
				actions += '<a class="vonarx-locator__item-action" href="mailto:' + escapeHtml( store.email ) + '" aria-label="Email" title="Email">' + ICONS.mail + '</a>';
			}
			if ( store.phone ) {
				actions += '<a class="vonarx-locator__item-action" href="tel:' + escapeHtml( store.phone.replace( /[^0-9+]/g, '' ) ) + '" aria-label="Phone" title="Phone">' + ICONS.phone + '</a>';
			}
			if ( store.website ) {
				actions += '<a class="vonarx-locator__item-action" href="' + escapeHtml( store.website ) + '" target="_blank" rel="noopener noreferrer" aria-label="Website" title="Website">' + ICONS.website + '</a>';
			}
			if ( actions ) {
				content += '<div class="vonarx-locator__item-actions">' + actions + '</div>';
			}

			// Logo sits to the right of the card's text content.
			return '<div class="vonarx-locator__item-inner">' +
				'<div class="vonarx-locator__item-content">' + content + '</div>' +
				logoImgHtml( store, 'vonarx-locator__item-logo' ) +
				'</div>';
		}

		function renderList( stores ) {
			storeListEl.innerHTML = '';

			if ( ! stores.length ) {
				storeListEl.innerHTML = '<li class="vonarx-locator__empty">No locations match your search.</li>';
				return;
			}

			// Stores already arrive sorted by country from the REST API;
			// group consecutive same-country entries under a heading.
			var lastCountry = null;

			stores.forEach( function ( store ) {
				var country = store.country || 'Other';
				if ( country !== lastCountry ) {
					var heading = document.createElement( 'li' );
					heading.className = 'vonarx-locator__group-heading';
					heading.textContent = country;
					storeListEl.appendChild( heading );
					lastCountry = country;
				}

				var li = document.createElement( 'li' );
				li.className = 'vonarx-locator__item';
				li.dataset.id = store.id;
				li.innerHTML = storeCardHtml( store );
				li.addEventListener( 'click', function () {
					var marker = markers[ store.id ];
					if ( marker ) {
						handleStoreSelected( store, marker, false );
					}
				} );
				storeListEl.appendChild( li );
			} );
		}

		function renderMarkers( stores ) {
			Object.keys( markers ).forEach( function ( id ) {
				map.removeLayer( markers[ id ] );
			} );
			markers = {};

			var bounds = [];
			stores.forEach( function ( store ) {
				var marker = L.marker( [ store.lat, store.lng ] ).addTo( map );
				marker.on( 'click', function () {
					handleStoreSelected( store, marker, true );
				} );
				markers[ store.id ] = marker;
				bounds.push( [ store.lat, store.lng ] );
			} );

			if ( bounds.length ) {
				// 9 is 50% of the tile layer's maxZoom (18) above — the closest
			// the results view is allowed to zoom in, even for a tightly
			// clustered set of results.
			map.fitBounds( bounds, { padding: [ 40, 40 ], maxZoom: 9 } );
			}
		}

		var currentStores = [];
		var currentSearchQuery = '';
		var selectedCategories = [];

		function loadStores() {
			var params = [];
			if ( currentSearchQuery ) {
				params.push( 'search=' + encodeURIComponent( currentSearchQuery ) );
			}
			if ( selectedCategories.length ) {
				params.push( 'category=' + encodeURIComponent( selectedCategories.join( ',' ) ) );
			}
			var url = vonarxLocatorSettings.restUrl + ( params.length ? '?' + params.join( '&' ) : '' );

			fetch( url )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( stores ) {
					currentStores = stores;
					renderList( stores );
					renderMarkers( stores );
				} )
				.catch( function () {
					storeListEl.innerHTML = '<li class="vonarx-locator__empty">Unable to load locations right now.</li>';
				} );
		}

		/**
		 * "Use my location": geolocates the visitor, drops a marker for them,
		 * and selects the nearest distributor (which also centers the map on
		 * it, via the existing handleStoreSelected() -> map.setView() path).
		 */
		var userLocationMarker = null;

		function haversineMiles( lat1, lng1, lat2, lng2 ) {
			var toRad = function ( deg ) {
				return ( deg * Math.PI ) / 180;
			};
			var earthRadiusMiles = 3958.8;
			var dLat = toRad( lat2 - lat1 );
			var dLng = toRad( lng2 - lng1 );
			var a = Math.sin( dLat / 2 ) * Math.sin( dLat / 2 ) +
				Math.cos( toRad( lat1 ) ) * Math.cos( toRad( lat2 ) ) *
				Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );
			var c = 2 * Math.atan2( Math.sqrt( a ), Math.sqrt( 1 - a ) );
			return earthRadiusMiles * c;
		}

		function findNearestStore( lat, lng ) {
			if ( ! currentStores.length ) {
				return null;
			}
			var nearestStore = null;
			var nearestDistance = Infinity;
			currentStores.forEach( function ( store ) {
				var distance = haversineMiles( lat, lng, store.lat, store.lng );
				if ( distance < nearestDistance ) {
					nearestDistance = distance;
					nearestStore = store;
				}
			} );
			return nearestStore ? { store: nearestStore, distanceMiles: nearestDistance } : null;
		}

		function placeUserLocationMarker( lat, lng ) {
			if ( userLocationMarker ) {
				map.removeLayer( userLocationMarker );
			}
			var accentColor = getComputedStyle( document.getElementById( 'vonarx-locator' ) )
				.getPropertyValue( '--color-accent' )
				.trim() || '#94acff';
			userLocationMarker = L.circleMarker( [ lat, lng ], {
				radius: 8,
				color: '#ffffff',
				weight: 3,
				fillColor: accentColor,
				fillOpacity: 1,
			} ).addTo( map );
			userLocationMarker.bindTooltip( 'Your location' );
		}

		// Two triggers share this: the icon button in the search field, and
		// the floating "Use my location" button over the map. Both reflect
		// the same loading/disabled state so they never fall out of sync.
		var locateButtons = Array.prototype.slice.call(
			document.querySelectorAll( '#vonarx-locate-btn, #vonarx-map-locate-btn' )
		);
		var locateStatusEl = document.getElementById( 'vonarx-locate-status' );
		var locateStatusTimer;

		function showLocateStatus( message, autoHideMs ) {
			if ( ! locateStatusEl ) {
				return;
			}
			locateStatusEl.textContent = message;
			locateStatusEl.hidden = false;
			clearTimeout( locateStatusTimer );
			if ( autoHideMs ) {
				locateStatusTimer = setTimeout( function () {
					locateStatusEl.hidden = true;
				}, autoHideMs );
			}
		}

		function setLocateButtonsLoading( isLoading ) {
			locateButtons.forEach( function ( btn ) {
				btn.disabled = isLoading;
				btn.classList.toggle( 'is-loading', isLoading );
			} );
		}

		function locateUser() {
			if ( ! navigator.geolocation ) {
				showLocateStatus( 'Geolocation isn’t supported by this browser.' );
				return;
			}

			setLocateButtonsLoading( true );
			showLocateStatus( 'Locating you…' );

			navigator.geolocation.getCurrentPosition(
				function ( position ) {
					setLocateButtonsLoading( false );

					var lat = position.coords.latitude;
					var lng = position.coords.longitude;
					placeUserLocationMarker( lat, lng );

					var nearest = findNearestStore( lat, lng );
					if ( ! nearest ) {
						showLocateStatus( 'No distributors are loaded to compare against.' );
						return;
					}

					var marker = markers[ nearest.store.id ];
					if ( marker ) {
						handleStoreSelected( nearest.store, marker, true );
					}

					showLocateStatus(
						'Nearest distributor: ' + nearest.store.name + ' (' + nearest.distanceMiles.toFixed( 1 ) + ' mi away)',
						6000
					);
				},
				function ( error ) {
					setLocateButtonsLoading( false );

					var message = 'Unable to determine your location.';
					if ( error.code === error.PERMISSION_DENIED ) {
						message = 'Location access was denied.';
					} else if ( error.code === error.POSITION_UNAVAILABLE ) {
						message = 'Your location is currently unavailable.';
					} else if ( error.code === error.TIMEOUT ) {
						message = 'Locating you timed out — try again.';
					}
					showLocateStatus( message );
				},
				// enableHighAccuracy holds out for a GPS fix that desktop
				// hardware doesn't have, which is a common cause of timeouts
				// there; network/Wi-Fi based positioning is faster and plenty
				// precise for "nearest distributor". Generous timeout since
				// first-time OS location resolution can still take a while.
				{ enableHighAccuracy: false, timeout: 20000, maximumAge: 60000 }
			);
		}

		locateButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', locateUser );
		} );

		/**
		 * Top bar: location search.
		 *
		 * This is a stand-in for real geocoding (e.g. re-centering the map on
		 * a searched address). Until that's wired up, it filters the existing
		 * results the same way the old sidebar search did, so the field is
		 * still useful in the meantime.
		 */
		function onSearch( query ) {
			currentSearchQuery = query;
			loadStores();
		}

		var searchInput = document.getElementById( 'vonarx-location-search' );
		if ( searchInput ) {
			var searchTimer;
			searchInput.addEventListener( 'input', function ( e ) {
				clearTimeout( searchTimer );
				var value = e.target.value.trim();
				searchInput.setAttribute( 'aria-expanded', value.length > 0 ? 'true' : 'false' );
				searchTimer = setTimeout( function () {
					onSearch( value );
				}, 250 );
			} );
		}

		/**
		 * Top bar: category filter. Selecting one or more re-fetches
		 * locations from the REST API filtered by Product Group (see
		 * class-rest-api.php's `category` param), same as the search field.
		 *
		 * Two UIs share the same selectedCategories state: a row of toggle
		 * chips (>=1024px) and a checkbox-list-in-a-popover (<1024px, CSS
		 * swaps which is visible). syncCategoryFilterUi() keeps both — plus
		 * the dropdown's label and the shared "Clear all" button — in sync
		 * regardless of which one a change came from, so switching
		 * breakpoints mid-session (e.g. rotating a tablet) never shows a
		 * stale selection.
		 */
		function onCategoryFilterChange( categories ) {
			selectedCategories = categories;
			loadStores();
		}

		var chipsContainer = document.getElementById( 'vonarx-category-chips' );
		var clearChipsBtn = document.getElementById( 'vonarx-clear-chips' );
		var categoryDropdown = document.getElementById( 'vonarx-category-dropdown' );
		var categoryDropdownToggle = document.getElementById( 'vonarx-category-dropdown-toggle' );
		var categoryDropdownPanel = document.getElementById( 'vonarx-category-dropdown-panel' );
		var categoryDropdownLabelEl = categoryDropdownToggle ?
			categoryDropdownToggle.querySelector( '.vonarx-locator__category-dropdown-label' ) : null;
		var categoryDropdownDefaultLabel = categoryDropdownLabelEl ? categoryDropdownLabelEl.textContent : '';

		function syncCategoryFilterUi() {
			if ( chipsContainer ) {
				chipsContainer.querySelectorAll( '.vonarx-chip' ).forEach( function ( chip ) {
					chip.setAttribute( 'aria-pressed', selectedCategories.indexOf( chip.dataset.category ) !== -1 ? 'true' : 'false' );
				} );
			}
			if ( categoryDropdownPanel ) {
				categoryDropdownPanel.querySelectorAll( '.vonarx-locator__category-dropdown-checkbox' ).forEach( function ( checkbox ) {
					checkbox.checked = selectedCategories.indexOf( checkbox.value ) !== -1;
				} );
			}
			if ( categoryDropdownLabelEl ) {
				categoryDropdownLabelEl.textContent = selectedCategories.length ?
					categoryDropdownDefaultLabel + ' (' + selectedCategories.length + ')' :
					categoryDropdownDefaultLabel;
			}
			if ( clearChipsBtn ) {
				clearChipsBtn.hidden = selectedCategories.length === 0;
			}
		}

		function toggleCategory( category ) {
			var index = selectedCategories.indexOf( category );
			if ( index === -1 ) {
				selectedCategories.push( category );
			} else {
				selectedCategories.splice( index, 1 );
			}
			syncCategoryFilterUi();
			onCategoryFilterChange( selectedCategories );
		}

		if ( chipsContainer ) {
			chipsContainer.addEventListener( 'click', function ( e ) {
				var chip = e.target.closest( '.vonarx-chip' );
				if ( chip ) {
					toggleCategory( chip.dataset.category );
				}
			} );
		}

		function closeCategoryDropdown() {
			if ( ! categoryDropdown || ! categoryDropdown.classList.contains( 'is-open' ) ) {
				return;
			}
			categoryDropdown.classList.remove( 'is-open' );
			categoryDropdownToggle.setAttribute( 'aria-expanded', 'false' );
			categoryDropdownPanel.hidden = true;
		}

		if ( categoryDropdown && categoryDropdownToggle && categoryDropdownPanel ) {
			categoryDropdownToggle.addEventListener( 'click', function () {
				if ( categoryDropdown.classList.contains( 'is-open' ) ) {
					closeCategoryDropdown();
					return;
				}
				categoryDropdown.classList.add( 'is-open' );
				categoryDropdownToggle.setAttribute( 'aria-expanded', 'true' );
				categoryDropdownPanel.hidden = false;
			} );

			categoryDropdownPanel.addEventListener( 'change', function ( e ) {
				var checkbox = e.target.closest( '.vonarx-locator__category-dropdown-checkbox' );
				if ( checkbox ) {
					toggleCategory( checkbox.value );
				}
			} );

			document.addEventListener( 'click', function ( e ) {
				if ( ! categoryDropdown.contains( e.target ) ) {
					closeCategoryDropdown();
				}
			} );

			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					closeCategoryDropdown();
				}
			} );
		}

		if ( clearChipsBtn ) {
			clearChipsBtn.addEventListener( 'click', function () {
				selectedCategories = [];
				syncCategoryFilterUi();
				onCategoryFilterChange( selectedCategories );
			} );
		}

		/**
		 * Collapsible sidebar handle (stacked layout only; hidden via CSS
		 * above --bp-tablet-portrait where the sidebar is always expanded).
		 */
		if ( sidebar && sidebarToggle ) {
			sidebarToggle.addEventListener( 'click', function () {
				var expanded = sidebarToggle.getAttribute( 'aria-expanded' ) === 'true';
				sidebarToggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
				sidebar.classList.toggle( 'is-collapsed', expanded );
			} );
		}

		loadStores();
	} );
})();
