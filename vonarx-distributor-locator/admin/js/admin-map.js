(function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		var mapEl = document.getElementById( 'vonarx-admin-map' );
		if ( ! mapEl || typeof L === 'undefined' ) {
			return;
		}

		var latInput = document.getElementById( 'vonarx_lat' );
		var lngInput = document.getElementById( 'vonarx_lng' );

		var hasCoords = latInput.value && lngInput.value;
		var startLat = parseFloat( latInput.value ) || 39.5;
		var startLng = parseFloat( lngInput.value ) || -98.35;

		var map = L.map( mapEl ).setView( [ startLat, startLng ], hasCoords ? 12 : 4 );
		L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap contributors',
			maxZoom: 18,
		} ).addTo( map );

		var marker = null;

		function setMarker( lat, lng ) {
			if ( marker ) {
				marker.setLatLng( [ lat, lng ] );
			} else {
				marker = L.marker( [ lat, lng ], { draggable: true } ).addTo( map );
				marker.on( 'dragend', function () {
					var pos = marker.getLatLng();
					latInput.value = pos.lat.toFixed( 6 );
					lngInput.value = pos.lng.toFixed( 6 );
				} );
			}
		}

		if ( hasCoords ) {
			setMarker( startLat, startLng );
		}

		map.on( 'click', function ( e ) {
			latInput.value = e.latlng.lat.toFixed( 6 );
			lngInput.value = e.latlng.lng.toFixed( 6 );
			setMarker( e.latlng.lat, e.latlng.lng );
		} );

		var geocodeBtn = document.getElementById( 'vonarx-geocode-btn' );
		if ( ! geocodeBtn ) {
			return;
		}

		geocodeBtn.addEventListener( 'click', function () {
			var address = [
				document.getElementById( 'vonarx_address' ).value,
				document.getElementById( 'vonarx_city' ).value,
				document.getElementById( 'vonarx_state' ).value,
				document.getElementById( 'vonarx_zip' ).value,
			].filter( Boolean ).join( ', ' );

			if ( ! address ) {
				window.alert( 'Enter an address first.' );
				return;
			}

			geocodeBtn.disabled = true;
			var originalLabel = geocodeBtn.textContent;
			geocodeBtn.textContent = 'Searching…';

			fetch( 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent( address ) )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( results ) {
					if ( ! results.length ) {
						window.alert( 'No match found for that address. Enter coordinates manually.' );
						return;
					}
					var lat = parseFloat( results[ 0 ].lat );
					var lng = parseFloat( results[ 0 ].lon );
					latInput.value = lat.toFixed( 6 );
					lngInput.value = lng.toFixed( 6 );
					setMarker( lat, lng );
					map.setView( [ lat, lng ], 14 );
				} )
				.catch( function () {
					window.alert( 'Geocoding request failed. Enter coordinates manually.' );
				} )
				.finally( function () {
					geocodeBtn.disabled = false;
					geocodeBtn.textContent = originalLabel;
				} );
		} );
	} );
})();
