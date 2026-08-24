<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screen for the sidebar's branding block: logo, address, email, phone.
 * Displayed statically on the frontend inside the [vonarx_locator] sidebar.
 */
class Vonarx_Locator_Settings {

	const OPTION_KEY = 'vonarx_locator_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public static function get_settings() {
		$defaults = array(
			'logo_id' => 0,
			'address' => '',
			'email'   => '',
			'phone'   => '',
		);
		return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
	}

	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Vonarx_Locator_Post_Type::POST_TYPE,
			__( 'Sidebar Settings', 'vonarx-distributor-locator' ),
			__( 'Sidebar Settings', 'vonarx-distributor-locator' ),
			'manage_options',
			'vonarx-locator-settings',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'vonarx_location_page_vonarx-locator-settings' !== $hook ) {
			return;
		}
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

	public function maybe_save() {
		if ( ! isset( $_POST['vonarx_settings_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vonarx_settings_nonce'] ) ), 'vonarx_settings_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();

		$settings['address'] = isset( $_POST['vonarx_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['vonarx_address'] ) ) : '';

		$email = isset( $_POST['vonarx_email'] ) ? sanitize_text_field( wp_unslash( $_POST['vonarx_email'] ) ) : '';
		if ( '' === $email || is_email( $email ) ) {
			$settings['email'] = $email;
		} else {
			add_settings_error( 'vonarx_locator_settings', 'invalid_email', __( 'That email address doesn\'t look valid — it was not saved.', 'vonarx-distributor-locator' ) );
		}

		$phone = isset( $_POST['vonarx_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['vonarx_phone'] ) ) : '';
		if ( '' === $phone || preg_match( '/^[0-9+()\-\s.]{6,20}$/', $phone ) ) {
			$settings['phone'] = $phone;
		} else {
			add_settings_error( 'vonarx_locator_settings', 'invalid_phone', __( 'That phone number doesn\'t look valid — it was not saved.', 'vonarx-distributor-locator' ) );
		}

		$remove_logo = ! empty( $_POST['vonarx_remove_logo'] );
		$has_upload  = ! empty( $_FILES['vonarx_logo']['name'] );

		if ( $has_upload ) {
			$new_logo_id = self::handle_logo_upload( 'vonarx_logo' );
			if ( is_wp_error( $new_logo_id ) ) {
				add_settings_error( 'vonarx_locator_settings', 'upload_failed', $new_logo_id->get_error_message() );
			} else {
				if ( $settings['logo_id'] ) {
					wp_delete_attachment( $settings['logo_id'], true );
				}
				$settings['logo_id'] = $new_logo_id;
			}
		} elseif ( $remove_logo && $settings['logo_id'] ) {
			wp_delete_attachment( $settings['logo_id'], true );
			$settings['logo_id'] = 0;
		}

		update_option( self::OPTION_KEY, $settings );

		add_settings_error( 'vonarx_locator_settings', 'saved', __( 'Sidebar settings saved.', 'vonarx-distributor-locator' ), 'success' );
	}

	/**
	 * Handles an uploaded logo file: stores it, then resizes it to a
	 * 250px-wide version (matching the logo containers in the admin UI and
	 * sidebar) so the stored asset isn't oversized. Shared by the sidebar
	 * settings logo and each location's own logo (Vonarx_Locator_Post_Type).
	 *
	 * @param string $field_name The $_FILES key the upload arrived under.
	 * @param int    $max_width  Target max width in pixels.
	 * @return int|WP_Error Attachment ID on success.
	 */
	public static function handle_logo_upload( $field_name, $max_width = 250 ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$file = $_FILES[ $field_name ];

		// SVG is deliberately excluded: wp_handle_upload() rejects it by
		// default regardless of what's allowed here (WordPress core blocks
		// SVG uploads out of the box since inline SVG can carry script
		// content — enabling it site-wide requires an `upload_mimes` filter,
		// a security-relevant call this plugin shouldn't make on its own).
		$allowed_types = array( 'image/png', 'image/jpeg', 'image/gif', 'image/webp' );
		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			return new WP_Error( 'invalid_type', __( 'Logo must be a PNG, JPG, GIF, or WebP image.', 'vonarx-distributor-locator' ) );
		}

		$overrides = array( 'test_form' => false );
		$moved     = wp_handle_upload( $file, $overrides );

		if ( isset( $moved['error'] ) ) {
			return new WP_Error( 'upload_failed', $moved['error'] );
		}

		$editor = wp_get_image_editor( $moved['file'] );
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

		return self::create_attachment( $moved['file'], $moved['url'], $moved['type'] );
	}

	public static function create_attachment( $file_path, $file_url, $mime_type, $post_parent = 0 ) {
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime_type,
				'post_title'     => 'VonArx Locator Logo',
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_parent'    => $post_parent,
				'guid'           => $file_url,
			),
			$file_path
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$logo_url = $settings['logo_id'] ? wp_get_attachment_image_url( $settings['logo_id'], 'full' ) : '';
		?>
		<div class="wrap vonarx-settings-wrap">
			<h1><?php esc_html_e( 'Sidebar Settings', 'vonarx-distributor-locator' ); ?></h1>
			<p><?php esc_html_e( 'This logo and contact info are shown in the sidebar of the [vonarx_locator] shortcode.', 'vonarx-distributor-locator' ); ?></p>

			<?php settings_errors( 'vonarx_locator_settings' ); ?>

			<form method="post" enctype="multipart/form-data" class="vonarx-settings-form">
				<?php wp_nonce_field( 'vonarx_settings_save', 'vonarx_settings_nonce' ); ?>

				<h2><?php esc_html_e( 'Logo', 'vonarx-distributor-locator' ); ?></h2>
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
					<input type="file" name="vonarx_logo" accept="image/png,image/jpeg,image/gif,image/webp" class="vonarx-logo-file-input" />
					<input type="hidden" name="vonarx_remove_logo" class="vonarx-logo-remove-flag" value="0" />
					<button type="button" class="button-link vonarx-logo-remove" style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
						<?php esc_html_e( 'Remove image', 'vonarx-distributor-locator' ); ?>
					</button>
					<p class="description"><?php esc_html_e( 'Displayed at 250px wide. Larger images are automatically resized on save.', 'vonarx-distributor-locator' ); ?></p>
				</div>

				<h2><?php esc_html_e( 'Contact Info', 'vonarx-distributor-locator' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="vonarx_address"><?php esc_html_e( 'Address', 'vonarx-distributor-locator' ); ?></label></th>
						<td><textarea name="vonarx_address" id="vonarx_address" rows="3" class="large-text"><?php echo esc_textarea( $settings['address'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="vonarx_email"><?php esc_html_e( 'Email', 'vonarx-distributor-locator' ); ?></label></th>
						<td><input type="email" name="vonarx_email" id="vonarx_email" value="<?php echo esc_attr( $settings['email'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="vonarx_phone"><?php esc_html_e( 'Phone', 'vonarx-distributor-locator' ); ?></label></th>
						<td><input type="tel" name="vonarx_phone" id="vonarx_phone" value="<?php echo esc_attr( $settings['phone'] ); ?>" class="regular-text" /></td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'vonarx-distributor-locator' ) ); ?>
			</form>
		</div>
		<?php
	}
}
