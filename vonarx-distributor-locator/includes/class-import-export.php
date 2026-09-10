<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screen for bulk-managing locations via an .xlsx spreadsheet: export
 * every location to a workbook (also usable as a fill-in-the-blanks import
 * template), then re-upload it to update existing locations (matched by the
 * ID column) and/or add new ones (left blank).
 */
class Vonarx_Locator_Import_Export {

	const PAGE_SLUG = 'vonarx-locator-import-export';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_vonarx_locator_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_vonarx_locator_import', array( $this, 'handle_import' ) );
		add_action( 'admin_notices', array( $this, 'show_notice' ) );
	}

	/**
	 * Canonical column key => spreadsheet header, in the order both the
	 * exported file and the import parser use.
	 */
	private static function get_columns() {
		return array(
			'id'             => __( 'ID', 'vonarx-distributor-locator' ),
			'title'          => __( 'Company', 'vonarx-distributor-locator' ),
			'product_groups' => __( 'Product Groups', 'vonarx-distributor-locator' ),
			'address'        => __( 'Address', 'vonarx-distributor-locator' ),
			'city'           => __( 'City', 'vonarx-distributor-locator' ),
			'state'          => __( 'State / Region', 'vonarx-distributor-locator' ),
			'zip'            => __( 'ZIP / Postal Code', 'vonarx-distributor-locator' ),
			'country'        => __( 'Country', 'vonarx-distributor-locator' ),
			'geolocation'    => __( 'Geolocation (Lat, Lng)', 'vonarx-distributor-locator' ),
			'phone'          => __( 'Phone', 'vonarx-distributor-locator' ),
			'email'          => __( 'Email', 'vonarx-distributor-locator' ),
			'website'        => __( 'Website', 'vonarx-distributor-locator' ),
		);
	}

	/**
	 * Header text (normalized) => column key, including a few forgiving
	 * aliases so a hand-edited header row still matches.
	 */
	private static function get_header_aliases() {
		$aliases = array();
		foreach ( self::get_columns() as $key => $label ) {
			$aliases[ self::normalize_header( $label ) ] = $key;
		}
		$aliases[ self::normalize_header( 'State' ) ]       = 'state';
		$aliases[ self::normalize_header( 'Region' ) ]      = 'state';
		$aliases[ self::normalize_header( 'ZIP' ) ]         = 'zip';
		$aliases[ self::normalize_header( 'Postal Code' ) ] = 'zip';
		$aliases[ self::normalize_header( 'Geolocation' ) ] = 'geolocation';
		$aliases[ self::normalize_header( 'Coordinates' ) ] = 'geolocation';
		return $aliases;
	}

	private static function normalize_header( $text ) {
		return strtolower( trim( preg_replace( '/\s+/', ' ', (string) $text ) ) );
	}

	/**
	 * Resolves Product Group names to term IDs, creating any that don't
	 * already exist. wp_set_post_terms() can't take names directly here:
	 * the taxonomy is hierarchical, and for hierarchical taxonomies it
	 * casts every entry in $terms to an int before assigning — a name
	 * would silently become 0 and be dropped.
	 */
	private function resolve_term_ids( array $names ) {
		$term_ids = array();
		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, Vonarx_Locator_Post_Type::TAXONOMY );
			if ( ! $term ) {
				$inserted = wp_insert_term( $name, Vonarx_Locator_Post_Type::TAXONOMY );
				if ( is_wp_error( $inserted ) ) {
					continue;
				}
				$term_ids[] = (int) $inserted['term_id'];
			} else {
				$term_ids[] = (int) $term->term_id;
			}
		}
		return $term_ids;
	}

	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Vonarx_Locator_Post_Type::POST_TYPE,
			__( 'Import / Export', 'vonarx-distributor-locator' ),
			__( 'Import / Export', 'vonarx-distributor-locator' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	private function redirect_url() {
		return admin_url( 'edit.php?post_type=' . Vonarx_Locator_Post_Type::POST_TYPE . '&page=' . self::PAGE_SLUG );
	}

	private function set_notice( $type, $message ) {
		set_transient( 'vonarx_import_export_notice_' . get_current_user_id(), array(
			'type'    => $type,
			'message' => $message,
		), 60 );
	}

	public function show_notice() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, self::PAGE_SLUG ) ) {
			return;
		}

		$key    = 'vonarx_import_export_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );

		$class = 'notice-success';
		if ( 'error' === $notice['type'] ) {
			$class = 'notice-error';
		} elseif ( 'warning' === $notice['type'] ) {
			$class = 'notice-warning';
		}

		printf(
			'<div class="notice %1$s is-dismissible">%2$s</div>',
			esc_attr( $class ),
			wp_kses_post( $notice['message'] )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import / Export Locations', 'vonarx-distributor-locator' ); ?></h1>

			<div class="card" style="max-width:700px;">
				<h2><?php esc_html_e( 'Export', 'vonarx-distributor-locator' ); ?></h2>
				<p><?php esc_html_e( 'Download every location as an .xlsx spreadsheet — handy as a backup, for bulk-editing in Excel/Google Sheets, or as a ready-made template if you have none yet.', 'vonarx-distributor-locator' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'vonarx_locator_export' ); ?>
					<input type="hidden" name="action" value="vonarx_locator_export" />
					<?php submit_button( __( 'Download .xlsx', 'vonarx-distributor-locator' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<div class="card" style="max-width:700px;">
				<h2><?php esc_html_e( 'Import', 'vonarx-distributor-locator' ); ?></h2>
				<p><?php esc_html_e( 'Upload a filled-in copy of the spreadsheet above. Rows with an ID matching an existing location update it in place; rows with the ID left blank are added as new locations.', 'vonarx-distributor-locator' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field( 'vonarx_locator_import' ); ?>
					<input type="hidden" name="action" value="vonarx_locator_import" />
					<p><input type="file" name="vonarx_import_file" accept=".xlsx" required /></p>
					<?php submit_button( __( 'Upload & Import', 'vonarx-distributor-locator' ), 'primary', 'submit', false ); ?>
				</form>
			</div>

			<div class="card" style="max-width:700px;">
				<h2><?php esc_html_e( 'Column Reference', 'vonarx-distributor-locator' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Column', 'vonarx-distributor-locator' ); ?></th>
							<th><?php esc_html_e( 'Notes', 'vonarx-distributor-locator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><?php esc_html_e( 'ID', 'vonarx-distributor-locator' ); ?></td><td><?php esc_html_e( 'Leave blank to create a new location. Never edit this for an existing row.', 'vonarx-distributor-locator' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Company', 'vonarx-distributor-locator' ); ?></td><td><?php esc_html_e( 'Required. Rows with no company name are skipped.', 'vonarx-distributor-locator' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Product Groups', 'vonarx-distributor-locator' ); ?></td><td><?php esc_html_e( 'Comma-separated, e.g. "Grinders, Scarifiers". Unrecognized names are created automatically.', 'vonarx-distributor-locator' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Geolocation (Lat, Lng)', 'vonarx-distributor-locator' ); ?></td><td><?php esc_html_e( 'A single "latitude, longitude" pair, e.g. "51.5074, -0.1278" — the same format Google Maps shows when you right-click a spot.', 'vonarx-distributor-locator' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Logo', 'vonarx-distributor-locator' ); ?></td><td><?php esc_html_e( 'Not included — set each location\'s logo from its own edit screen.', 'vonarx-distributor-locator' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	// -----------------------------------------------------------------
	// Export
	// -----------------------------------------------------------------

	private function post_to_row( $post ) {
		$lat = get_post_meta( $post->ID, '_vonarx_lat', true );
		$lng = get_post_meta( $post->ID, '_vonarx_lng', true );

		$terms = wp_get_post_terms( $post->ID, Vonarx_Locator_Post_Type::TAXONOMY, array( 'fields' => 'names' ) );

		return array(
			'id'             => $post->ID,
			'title'          => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'product_groups' => is_wp_error( $terms ) ? '' : implode( ', ', $terms ),
			'address'        => get_post_meta( $post->ID, '_vonarx_address', true ),
			'city'           => get_post_meta( $post->ID, '_vonarx_city', true ),
			'state'          => get_post_meta( $post->ID, '_vonarx_state', true ),
			'zip'            => get_post_meta( $post->ID, '_vonarx_zip', true ),
			'country'        => get_post_meta( $post->ID, '_vonarx_country', true ),
			'geolocation'    => ( '' !== $lat && '' !== $lng ) ? $lat . ', ' . $lng : '',
			'phone'          => get_post_meta( $post->ID, '_vonarx_phone', true ),
			'email'          => get_post_meta( $post->ID, '_vonarx_email', true ),
			'website'        => get_post_meta( $post->ID, '_vonarx_website', true ),
		);
	}

	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'vonarx-distributor-locator' ) );
		}
		check_admin_referer( 'vonarx_locator_export' );

		$columns    = self::get_columns();
		$field_keys = array_keys( $columns );

		$query = new WP_Query(
			array(
				'post_type'      => Vonarx_Locator_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$assoc_rows = array_map( array( $this, 'post_to_row' ), $query->posts );

		usort(
			$assoc_rows,
			function ( $a, $b ) {
				$country_cmp = strcasecmp( $a['country'], $b['country'] );
				return 0 !== $country_cmp ? $country_cmp : strcasecmp( $a['title'], $b['title'] );
			}
		);

		$rows = array();
		foreach ( $assoc_rows as $assoc_row ) {
			$row = array();
			foreach ( $field_keys as $key ) {
				$row[] = $assoc_row[ $key ];
			}
			$rows[] = $row;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp_file = wp_tempnam( 'vonarx-locations.xlsx' );
		$result   = Vonarx_Locator_Xlsx::write( $tmp_file, array_values( $columns ), $rows );

		if ( is_wp_error( $result ) ) {
			@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$this->set_notice( 'error', esc_html( $result->get_error_message() ) );
			wp_safe_redirect( $this->redirect_url() );
			exit;
		}

		$filename = 'vonarx-distributor-locations-' . gmdate( 'Y-m-d' ) . '.xlsx';

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $tmp_file ) );
		readfile( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		exit;
	}

	// -----------------------------------------------------------------
	// Import
	// -----------------------------------------------------------------

	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'vonarx-distributor-locator' ) );
		}
		check_admin_referer( 'vonarx_locator_import' );

		if ( empty( $_FILES['vonarx_import_file']['tmp_name'] ) || UPLOAD_ERR_OK !== $_FILES['vonarx_import_file']['error'] ) {
			$this->set_notice( 'error', esc_html__( 'Please choose a .xlsx file to import.', 'vonarx-distributor-locator' ) );
			wp_safe_redirect( $this->redirect_url() );
			exit;
		}

		$filename  = sanitize_file_name( wp_unslash( $_FILES['vonarx_import_file']['name'] ) );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'xlsx' !== $extension ) {
			$this->set_notice( 'error', esc_html__( 'That file isn\'t an .xlsx spreadsheet.', 'vonarx-distributor-locator' ) );
			wp_safe_redirect( $this->redirect_url() );
			exit;
		}

		$rows = Vonarx_Locator_Xlsx::read( $_FILES['vonarx_import_file']['tmp_name'] );
		if ( is_wp_error( $rows ) ) {
			$this->set_notice( 'error', esc_html( $rows->get_error_message() ) );
			wp_safe_redirect( $this->redirect_url() );
			exit;
		}

		if ( empty( $rows ) ) {
			$this->set_notice( 'error', esc_html__( 'That file doesn\'t contain any rows.', 'vonarx-distributor-locator' ) );
			wp_safe_redirect( $this->redirect_url() );
			exit;
		}

		$header_row    = array_shift( $rows );
		$aliases       = self::get_header_aliases();
		$field_for_col = array();
		foreach ( $header_row as $col_index => $header_text ) {
			$normalized = self::normalize_header( $header_text );
			if ( isset( $aliases[ $normalized ] ) ) {
				$field_for_col[ $col_index ] = $aliases[ $normalized ];
			}
		}

		if ( ! in_array( 'title', $field_for_col, true ) ) {
			$this->set_notice( 'error', esc_html__( 'Couldn\'t find a "Company" column in that file\'s header row.', 'vonarx-distributor-locator' ) );
			wp_safe_redirect( $this->redirect_url() );
			exit;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		$created = 0;
		$updated = 0;
		$notes   = array();

		foreach ( $rows as $row_index => $row ) {
			$line = $row_index + 2; // header is row 1, plus 1 to make it 1-based.

			$data = array();
			foreach ( $field_for_col as $col_index => $field ) {
				$data[ $field ] = isset( $row[ $col_index ] ) ? trim( $row[ $col_index ] ) : '';
			}

			if ( array() === array_filter( $data ) ) {
				continue; // Fully blank row.
			}

			if ( empty( $data['title'] ) ) {
				$notes[] = sprintf( __( 'Row %d: skipped, missing Company name.', 'vonarx-distributor-locator' ), $line );
				continue;
			}

			$post_id = 0;
			if ( ! empty( $data['id'] ) && is_numeric( $data['id'] ) ) {
				$candidate = (int) $data['id'];
				if ( Vonarx_Locator_Post_Type::POST_TYPE === get_post_type( $candidate ) ) {
					$post_id = $candidate;
				}
			}

			$title = sanitize_text_field( $data['title'] );

			if ( $post_id ) {
				wp_update_post(
					array(
						'ID'         => $post_id,
						'post_title' => $title,
					)
				);
				++$updated;
			} else {
				$post_id = wp_insert_post(
					array(
						'post_type'   => Vonarx_Locator_Post_Type::POST_TYPE,
						'post_title'  => $title,
						'post_status' => 'publish',
					)
				);
				if ( is_wp_error( $post_id ) || ! $post_id ) {
					$notes[] = sprintf( __( 'Row %1$d: couldn\'t save "%2$s".', 'vonarx-distributor-locator' ), $line, $title );
					continue;
				}
				++$created;
			}

			$text_fields = array( 'address', 'city', 'state', 'zip', 'country', 'phone', 'email' );
			foreach ( $text_fields as $field ) {
				if ( isset( $data[ $field ] ) ) {
					update_post_meta( $post_id, '_vonarx_' . $field, sanitize_text_field( $data[ $field ] ) );
				}
			}

			if ( isset( $data['website'] ) ) {
				update_post_meta( $post_id, '_vonarx_website', $data['website'] ? esc_url_raw( $data['website'] ) : '' );
			}

			if ( isset( $data['geolocation'] ) ) {
				if ( '' === $data['geolocation'] ) {
					update_post_meta( $post_id, '_vonarx_lat', '' );
					update_post_meta( $post_id, '_vonarx_lng', '' );
				} elseif ( preg_match( '/^(-?\d+(?:\.\d+)?)\s*[,;]\s*(-?\d+(?:\.\d+)?)$/', $data['geolocation'], $m )
					&& abs( (float) $m[1] ) <= 90 && abs( (float) $m[2] ) <= 180 ) {
					update_post_meta( $post_id, '_vonarx_lat', $m[1] );
					update_post_meta( $post_id, '_vonarx_lng', $m[2] );
				} else {
					$notes[] = sprintf( __( 'Row %1$d: couldn\'t understand Geolocation "%2$s" — left unchanged.', 'vonarx-distributor-locator' ), $line, $data['geolocation'] );
				}
			}

			if ( isset( $data['product_groups'] ) ) {
				$names = array_filter( array_map( 'trim', explode( ',', $data['product_groups'] ) ) );
				wp_set_post_terms( $post_id, $this->resolve_term_ids( $names ), Vonarx_Locator_Post_Type::TAXONOMY );
			}
		}

		$summary = sprintf(
			/* translators: 1: number created, 2: number updated */
			__( 'Import complete: %1$d location(s) created, %2$d updated.', 'vonarx-distributor-locator' ),
			$created,
			$updated
		);

		$message = '<p>' . esc_html( $summary ) . '</p>';
		if ( $notes ) {
			$shown = array_slice( $notes, 0, 15 );
			$message .= '<ul style="margin-left:1.5em;list-style:disc;">';
			foreach ( $shown as $note ) {
				$message .= '<li>' . esc_html( $note ) . '</li>';
			}
			$message .= '</ul>';
			if ( count( $notes ) > count( $shown ) ) {
				$message .= '<p>' . esc_html( sprintf( __( '…and %d more.', 'vonarx-distributor-locator' ), count( $notes ) - count( $shown ) ) ) . '</p>';
			}
		}

		$this->set_notice( $notes ? 'warning' : 'success', $message );
		wp_safe_redirect( $this->redirect_url() );
		exit;
	}
}
