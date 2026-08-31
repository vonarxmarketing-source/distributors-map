<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Distributor Location custom post type and its admin meta box.
 */
class Vonarx_Locator_Post_Type {

	const POST_TYPE = 'vonarx_location';
	const TAXONOMY  = 'vonarx_product_group';

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'post_edit_form_tag', array( $this, 'add_form_enctype' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_logo_error' ) );
	}

	public function maybe_show_logo_error() {
		global $post;
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}
		$message = get_transient( 'vonarx_logo_error_' . $post->ID );
		if ( $message ) {
			delete_transient( 'vonarx_logo_error_' . $post->ID );
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	/**
	 * The post edit screen's <form> doesn't include enctype="multipart/form-data"
	 * by default, so a browser submits any <input type="file"> in a meta box
	 * (like the logo uploader below) as an empty value — the upload silently
	 * does nothing, while text fields save normally since they don't need it.
	 */
	public function add_form_enctype( $post ) {
		if ( $post && self::POST_TYPE === $post->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Distributor Locations', 'vonarx-distributor-locator' ),
					'singular_name' => __( 'Distributor Location', 'vonarx-distributor-locator' ),
					'add_new_item'  => __( 'Add New Location', 'vonarx-distributor-locator' ),
					'edit_item'     => __( 'Edit Location', 'vonarx-distributor-locator' ),
					'search_items'  => __( 'Search Locations', 'vonarx-distributor-locator' ),
					'all_items'     => __( 'Distributor Locations', 'vonarx-distributor-locator' ),
				),
				'public'       => true,
				'show_in_menu' => true,
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-location-alt',
				'supports'     => array( 'title' ),
				'has_archive'  => false,
				'rewrite'      => false,
			)
		);
	}

	/**
	 * Hierarchical (category-style) taxonomy so the admin editor gets the
	 * standard checkbox meta box with an inline "+ Add New Product Group"
	 * link — no custom UI needed to grow the list beyond the seeded terms.
	 */
	public function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Product Groups', 'vonarx-distributor-locator' ),
					'singular_name' => __( 'Product Group', 'vonarx-distributor-locator' ),
					'add_new_item'  => __( 'Add New Product Group', 'vonarx-distributor-locator' ),
					'search_items'  => __( 'Search Product Groups', 'vonarx-distributor-locator' ),
				),
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'rewrite'           => false,
			)
		);
	}

	public function add_meta_boxes() {
		add_meta_box(
			'vonarx_location_details',
			__( 'Location Details', 'vonarx-distributor-locator' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'vonarx_location_save', 'vonarx_location_nonce' );

		$fields = array(
			'address' => __( 'Street Address', 'vonarx-distributor-locator' ),
			'city'    => __( 'City', 'vonarx-distributor-locator' ),
			'state'   => __( 'State / Region', 'vonarx-distributor-locator' ),
			'zip'     => __( 'ZIP / Postal Code', 'vonarx-distributor-locator' ),
			'country' => __( 'Country', 'vonarx-distributor-locator' ),
			'phone'   => __( 'Phone', 'vonarx-distributor-locator' ),
			'email'   => __( 'Email', 'vonarx-distributor-locator' ),
			'website' => __( 'Website', 'vonarx-distributor-locator' ),
		);

		echo '<table class="form-table"><tbody>';
		foreach ( $fields as $key => $label ) {
			$value = get_post_meta( $post->ID, '_vonarx_' . $key, true );
			printf(
				'<tr><th><label for="vonarx_%1$s">%2$s</label></th><td><input type="text" id="vonarx_%1$s" name="vonarx_%1$s" value="%3$s" class="regular-text" /></td></tr>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_attr( $value )
			);
		}
		echo '</tbody></table>';

		$logo_id  = get_post_meta( $post->ID, '_vonarx_logo_id', true );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
		?>
		<hr />
		<h4><?php esc_html_e( 'Logo', 'vonarx-distributor-locator' ); ?></h4>
		<div class="vonarx-logo-uploader">
			<button type="button" class="vonarx-logo-dropzone" aria-label="<?php esc_attr_e( 'Upload logo', 'vonarx-distributor-locator' ); ?>">
				<img
					src="<?php echo esc_url( $logo_url ); ?>"
					alt=""
					class="vonarx-logo-preview"
					style="<?php echo $logo_url ? '' : 'display:none;'; ?>"
				/>
				<span class="vonarx-logo-placeholder-text" style="<?php echo $logo_url ? 'display:none;' : ''; ?>">
					<?php esc_html_e( 'Upload logo', 'vonarx-distributor-locator' ); ?>
				</span>
			</button>
			<input type="file" name="vonarx_location_logo" accept="image/png,image/jpeg,image/gif,image/webp" class="vonarx-logo-file-input" />
			<input type="hidden" name="vonarx_location_remove_logo" class="vonarx-logo-remove-flag" value="0" />
			<button type="button" class="button-link vonarx-logo-remove" style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
				<?php esc_html_e( 'Remove image', 'vonarx-distributor-locator' ); ?>
			</button>
			<p class="description"><?php esc_html_e( 'Shown next to this location in the sidebar list and on its map popup.', 'vonarx-distributor-locator' ); ?></p>
		</div>

		<?php
		$lat = get_post_meta( $post->ID, '_vonarx_lat', true );
		$lng = get_post_meta( $post->ID, '_vonarx_lng', true );
		?>
		<hr />
		<h4><?php esc_html_e( 'Map Coordinates', 'vonarx-distributor-locator' ); ?></h4>
		<p><?php esc_html_e( 'Click the map to drop a pin, drag it to fine-tune, or use "Find on map" to geocode the address above.', 'vonarx-distributor-locator' ); ?></p>
		<p><button type="button" class="button" id="vonarx-geocode-btn"><?php esc_html_e( 'Find on map', 'vonarx-distributor-locator' ); ?></button></p>
		<div id="vonarx-admin-map" style="height:320px;margin-bottom:10px;border:1px solid #ccd0d4;"></div>
		<p>
			<label>
				<?php esc_html_e( 'Latitude', 'vonarx-distributor-locator' ); ?>
				<input type="text" id="vonarx_lat" name="vonarx_lat" value="<?php echo esc_attr( $lat ); ?>" class="small-text" />
			</label>
			&nbsp;
			<label>
				<?php esc_html_e( 'Longitude', 'vonarx-distributor-locator' ); ?>
				<input type="text" id="vonarx_lng" name="vonarx_lng" value="<?php echo esc_attr( $lng ); ?>" class="small-text" />
			</label>
		</p>
		<?php
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['vonarx_location_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vonarx_location_nonce'] ) ), 'vonarx_location_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text_fields = array( 'address', 'city', 'state', 'zip', 'country', 'phone', 'email' );
		foreach ( $text_fields as $field ) {
			if ( isset( $_POST[ 'vonarx_' . $field ] ) ) {
				update_post_meta(
					$post_id,
					'_vonarx_' . $field,
					sanitize_text_field( wp_unslash( $_POST[ 'vonarx_' . $field ] ) )
				);
			}
		}

		if ( isset( $_POST['vonarx_website'] ) ) {
			update_post_meta( $post_id, '_vonarx_website', esc_url_raw( wp_unslash( $_POST['vonarx_website'] ) ) );
		}

		if ( isset( $_POST['vonarx_lat'] ) ) {
			update_post_meta( $post_id, '_vonarx_lat', sanitize_text_field( wp_unslash( $_POST['vonarx_lat'] ) ) );
		}

		if ( isset( $_POST['vonarx_lng'] ) ) {
			update_post_meta( $post_id, '_vonarx_lng', sanitize_text_field( wp_unslash( $_POST['vonarx_lng'] ) ) );
		}

		$old_logo_id = (int) get_post_meta( $post_id, '_vonarx_logo_id', true );
		$remove_logo = ! empty( $_POST['vonarx_location_remove_logo'] );
		$has_upload  = ! empty( $_FILES['vonarx_location_logo']['name'] );

		if ( $has_upload ) {
			$new_logo_id = Vonarx_Locator_Settings::handle_logo_upload( 'vonarx_location_logo' );
			if ( is_wp_error( $new_logo_id ) ) {
				set_transient( 'vonarx_logo_error_' . $post_id, $new_logo_id->get_error_message(), 60 );
			} else {
				if ( $old_logo_id ) {
					wp_delete_attachment( $old_logo_id, true );
				}
				update_post_meta( $post_id, '_vonarx_logo_id', $new_logo_id );
			}
		} elseif ( $remove_logo && $old_logo_id ) {
			wp_delete_attachment( $old_logo_id, true );
			update_post_meta( $post_id, '_vonarx_logo_id', 0 );
		}
	}

	public function enqueue_admin_assets( $hook ) {
		global $post_type;

		if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && self::POST_TYPE === $post_type ) {
			wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
			wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
			wp_enqueue_script(
				'vonarx-admin-map',
				VONARX_LOCATOR_URL . 'admin/js/admin-map.js',
				array( 'leaflet' ),
				VONARX_LOCATOR_VERSION,
				true
			);

			$css_path = VONARX_LOCATOR_PATH . 'admin/css/settings.css';
			$js_path  = VONARX_LOCATOR_PATH . 'admin/js/logo-uploader.js';

			wp_enqueue_style(
				'vonarx-locator-admin-settings',
				VONARX_LOCATOR_URL . 'admin/css/settings.css',
				array(),
				file_exists( $css_path ) ? filemtime( $css_path ) : VONARX_LOCATOR_VERSION
			);
			wp_enqueue_script(
				'vonarx-locator-logo-uploader',
				VONARX_LOCATOR_URL . 'admin/js/logo-uploader.js',
				array(),
				file_exists( $js_path ) ? filemtime( $js_path ) : VONARX_LOCATOR_VERSION,
				true
			);
		}
	}
}
