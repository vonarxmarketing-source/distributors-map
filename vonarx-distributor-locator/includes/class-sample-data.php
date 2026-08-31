<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-time import of the bundled sample distributor locations (data/seed-
 * locations.json + data/logos/), so a fresh install already has demo/test
 * data to look at instead of an empty list. Runs once on activation, guarded
 * by an option flag so deactivating/reactivating the plugin later doesn't
 * re-import and duplicate everything.
 */
class Vonarx_Locator_Sample_Data {

	const IMPORTED_OPTION = 'vonarx_locator_sample_data_imported';

	public static function maybe_import() {
		if ( get_option( self::IMPORTED_OPTION ) ) {
			return;
		}
		self::import();
		update_option( self::IMPORTED_OPTION, 1 );
	}

	public static function import() {
		$json_path = VONARX_LOCATOR_PATH . 'data/seed-locations.json';
		if ( ! file_exists( $json_path ) ) {
			return;
		}

		$locations = json_decode( file_get_contents( $json_path ), true );
		if ( ! is_array( $locations ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$taxonomy    = Vonarx_Locator_Post_Type::TAXONOMY;
		$text_fields = array( 'address', 'city', 'state', 'zip', 'country', 'phone', 'email', 'website' );

		foreach ( $locations as $location ) {
			if ( empty( $location['title'] ) ) {
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => Vonarx_Locator_Post_Type::POST_TYPE,
					'post_title'  => $location['title'],
					'post_status' => 'publish',
				)
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			foreach ( $text_fields as $field ) {
				if ( ! empty( $location[ $field ] ) ) {
					update_post_meta( $post_id, '_vonarx_' . $field, $location[ $field ] );
				}
			}

			if ( ! empty( $location['lat'] ) && ! empty( $location['lng'] ) ) {
				update_post_meta( $post_id, '_vonarx_lat', $location['lat'] );
				update_post_meta( $post_id, '_vonarx_lng', $location['lng'] );
			}

			if ( ! empty( $location['product_groups'] ) ) {
				$term_ids = array();
				foreach ( $location['product_groups'] as $slug ) {
					$term = get_term_by( 'slug', $slug, $taxonomy );
					if ( $term ) {
						$term_ids[] = $term->term_id;
					}
				}
				if ( $term_ids ) {
					wp_set_post_terms( $post_id, $term_ids, $taxonomy );
				}
			}

			if ( ! empty( $location['logo'] ) ) {
				$logo_id = self::sideload_logo( $location['logo'] );
				if ( $logo_id ) {
					update_post_meta( $post_id, '_vonarx_logo_id', $logo_id );
				}
			}
		}
	}

	/**
	 * Sideloads a bundled logo into the media library. Copies it to a temp
	 * file first — wp_handle_sideload() moves/deletes its source file, and
	 * the bundled original in data/logos/ must survive future imports (e.g.
	 * on a different site, or if this ever needs to run again).
	 */
	private static function sideload_logo( $filename ) {
		$source = VONARX_LOCATOR_PATH . 'data/logos/' . $filename;
		if ( ! file_exists( $source ) ) {
			return 0;
		}

		$tmp_copy = wp_tempnam( $filename );
		if ( ! copy( $source, $tmp_copy ) ) {
			return 0;
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_copy,
		);
		$overrides = array(
			'test_form' => false,
			'action'    => 'wp_handle_sideload',
		);

		$moved = wp_handle_sideload( $file_array, $overrides );
		if ( isset( $moved['error'] ) ) {
			return 0;
		}

		$max_width = 250;
		$editor    = wp_get_image_editor( $moved['file'] );
		if ( ! is_wp_error( $editor ) ) {
			$size = $editor->get_size();
			if ( $size['width'] > $max_width ) {
				$target_height = (int) round( $max_width * ( $size['height'] / $size['width'] ) );
				$editor->resize( $max_width, $target_height, false );
				$saved = $editor->save( $moved['file'] );
				if ( ! is_wp_error( $saved ) ) {
					$moved['file'] = $saved['path'];
					$moved['url']  = str_replace( basename( $moved['url'] ), $saved['file'], $moved['url'] );
				}
			}
		}

		$attachment_id = Vonarx_Locator_Settings::create_attachment( $moved['file'], $moved['url'], $moved['type'] );
		return is_wp_error( $attachment_id ) ? 0 : $attachment_id;
	}
}
