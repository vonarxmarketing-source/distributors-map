<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes published distributor locations at GET /wp-json/vonarx/v1/locations
 */
class Vonarx_Locator_REST_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'vonarx/v1',
			'/locations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_locations' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'search'   => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'category' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function get_locations( $request ) {
		$search   = $request->get_param( 'search' );
		$category = $request->get_param( 'category' );

		$requested_categories = array();
		if ( $category ) {
			$requested_categories = array_filter( array_map( 'trim', explode( ',', $category ) ) );
		}

		$query = new WP_Query(
			array(
				'post_type'      => Vonarx_Locator_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$locations = array();

		foreach ( $query->posts as $post ) {
			$lat = get_post_meta( $post->ID, '_vonarx_lat', true );
			$lng = get_post_meta( $post->ID, '_vonarx_lng', true );

			if ( '' === $lat || '' === $lng ) {
				continue;
			}

			$logo_id = get_post_meta( $post->ID, '_vonarx_logo_id', true );

			$location = array(
				'id'      => $post->ID,
				// get_the_title() runs wptexturize, which HTML-entity-encodes
				// things like " - " into " &#8211; ". Decode here since the
				// frontend inserts this as plain text (escapeHtml + innerHTML),
				// which would otherwise double-escape it into literal "&#8211;".
				'name'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'address' => get_post_meta( $post->ID, '_vonarx_address', true ),
				'city'    => get_post_meta( $post->ID, '_vonarx_city', true ),
				'state'   => get_post_meta( $post->ID, '_vonarx_state', true ),
				'zip'     => get_post_meta( $post->ID, '_vonarx_zip', true ),
				'country' => get_post_meta( $post->ID, '_vonarx_country', true ),
				'phone'   => get_post_meta( $post->ID, '_vonarx_phone', true ),
				'email'   => get_post_meta( $post->ID, '_vonarx_email', true ),
				'website' => get_post_meta( $post->ID, '_vonarx_website', true ),
				'logo'    => $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '',
				'lat'     => (float) $lat,
				'lng'     => (float) $lng,
				'product_groups' => wp_get_post_terms(
					$post->ID,
					Vonarx_Locator_Post_Type::TAXONOMY,
					array( 'fields' => 'slugs' )
				),
			);

			if ( $search ) {
				$haystack = strtolower( implode( ' ', array( $location['name'], $location['city'], $location['state'], $location['country'], $location['zip'] ) ) );
				if ( false === strpos( $haystack, strtolower( $search ) ) ) {
					continue;
				}
			}

			if ( $requested_categories && ! array_intersect( $requested_categories, $location['product_groups'] ) ) {
				continue;
			}

			$locations[] = $location;
		}

		usort(
			$locations,
			function ( $a, $b ) {
				$country_cmp = strcasecmp( $a['country'], $b['country'] );
				return 0 !== $country_cmp ? $country_cmp : strcasecmp( $a['name'], $b['name'] );
			}
		);

		return rest_ensure_response( $locations );
	}
}
