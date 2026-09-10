<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side geocoding via Nominatim (OpenStreetMap), for use where there's
 * no browser to run the admin map's own client-side "Find on map" lookup —
 * currently just the .xlsx importer, auto-filling coordinates for rows whose
 * Geolocation cell was left blank.
 */
class Vonarx_Locator_Geocoder {

	/**
	 * Nominatim's usage policy caps unattended/bulk use at one request per
	 * second. Tracked as a static so it's enforced across every geocode()
	 * call within a single import loop, not just back-to-back calls.
	 */
	private static $last_request_time = 0.0;

	/**
	 * @param string $address Free-form address to look up.
	 * @return array{lat:string,lng:string}|false Coordinates (6 decimal places), or false if none found / the request failed.
	 */
	public static function geocode( $address ) {
		$address = trim( $address );
		if ( '' === $address ) {
			return false;
		}

		if ( self::$last_request_time ) {
			$elapsed = microtime( true ) - self::$last_request_time;
			if ( $elapsed < 1.0 ) {
				usleep( (int) ( ( 1.0 - $elapsed ) * 1000000 ) );
			}
		}

		$url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . rawurlencode( $address );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				// Identifies the requesting app + site, per Nominatim's usage
				// policy (https://operations.osmfoundation.org/policies/nominatim/).
				'user-agent' => 'VonArx Distributor Locator/' . VONARX_LOCATOR_VERSION . ' (' . home_url() . ')',
			)
		);

		self::$last_request_time = microtime( true );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$results = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $results[0]['lat'] ) || empty( $results[0]['lon'] ) ) {
			return false;
		}

		return array(
			'lat' => (string) round( (float) $results[0]['lat'], 6 ),
			'lng' => (string) round( (float) $results[0]['lon'], 6 ),
		);
	}
}
