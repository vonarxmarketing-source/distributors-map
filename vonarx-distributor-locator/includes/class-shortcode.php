<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the [vonarx_locator] shortcode and its frontend assets.
 */
class Vonarx_Locator_Shortcode {

	/**
	 * Product categories for the top bar's filter chips. UI-only for now —
	 * locations don't carry a category yet, so selections are tracked in JS
	 * (onCategoryFilterChange) but don't filter results until that data exists.
	 */
	const CATEGORIES = array(
		'scarifiers'      => 'Scarifiers',
		'shavers'         => 'Shavers',
		'dust-extractors' => 'Dust extractors',
		'shotblasters'    => 'Shotblasters',
		'dust-collectors' => 'Dust collectors',
		'grinders'        => 'Grinders',
		'pneumatic-tools' => 'Pneumatic tools',
	);

	public function __construct() {
		add_shortcode( 'vonarx_locator', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets() {
		wp_register_style(
			'vonarx-google-fonts',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
			array(),
			null
		);

		wp_register_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

		// filemtime() rather than the static plugin version: assets are still
		// being iterated on frequently, and a fixed ?ver= lets browsers keep
		// serving a stale cached copy after an edit. Falls back to the plugin
		// version if the file can't be read for some reason.
		$css_path = VONARX_LOCATOR_PATH . 'public/css/locator.css';
		$js_path  = VONARX_LOCATOR_PATH . 'public/js/locator.js';

		wp_register_style(
			'vonarx-locator',
			VONARX_LOCATOR_URL . 'public/css/locator.css',
			array( 'leaflet', 'vonarx-google-fonts' ),
			file_exists( $css_path ) ? filemtime( $css_path ) : VONARX_LOCATOR_VERSION
		);

		wp_register_script(
			'vonarx-locator',
			VONARX_LOCATOR_URL . 'public/js/locator.js',
			array( 'leaflet' ),
			file_exists( $js_path ) ? filemtime( $js_path ) : VONARX_LOCATOR_VERSION,
			true
		);

		wp_localize_script(
			'vonarx-locator',
			'vonarxLocatorSettings',
			array(
				'restUrl'    => esc_url_raw( rest_url( 'vonarx/v1/locations' ) ),
				'categories' => self::CATEGORIES,
			)
		);
	}

	private function icon( $name ) {
		$icons = array(
			'search' => '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="9" cy="9" r="6.25" stroke="currentColor" stroke-width="1.5"/><path d="M17 17L13.6 13.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
			'pin'    => '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 18s6-5.5 6-10a6 6 0 10-12 0c0 4.5 6 10 6 10z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/></svg>',
			'mail'   => '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="2.5" y="4.5" width="15" height="11" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M3.5 5.5L10 11l6.5-5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'phone'  => '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 3h2.2l1 3.6-1.7 1.2a9 9 0 004.7 4.7l1.2-1.7 3.6 1V15a2 2 0 01-2.2 2 14 14 0 01-10.8-10.8A2 2 0 015 3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
			'chevron' => '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'x'      => '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
			'locate' => '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M10 2v2.5M10 15.5V18M2 10h2.5M15.5 10H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
		);
		return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
	}

	public function render( $atts ) {
		wp_enqueue_style( 'leaflet' );
		wp_enqueue_style( 'vonarx-google-fonts' );
		wp_enqueue_style( 'vonarx-locator' );
		wp_enqueue_script( 'leaflet' );
		wp_enqueue_script( 'vonarx-locator' );

		ob_start();
		?>
		<div class="vonarx-locator" id="vonarx-locator">

			<div class="vonarx-locator__topbar">
				<div class="vonarx-locator__search-wrap">
					<span class="vonarx-locator__search-icon" aria-hidden="true"><?php echo $this->icon( 'search' ); ?></span>
					<input
						type="text"
						id="vonarx-location-search"
						class="vonarx-locator__search-input"
						role="combobox"
						aria-expanded="false"
						aria-autocomplete="list"
						aria-controls="vonarx-location-search-results"
						autocomplete="off"
						placeholder="<?php esc_attr_e( 'Search location...', 'vonarx-distributor-locator' ); ?>"
					/>
					<button
						type="button"
						id="vonarx-locate-btn"
						class="vonarx-locator__locate-btn"
						aria-label="<?php esc_attr_e( 'Use my location', 'vonarx-distributor-locator' ); ?>"
						title="<?php esc_attr_e( 'Use my location', 'vonarx-distributor-locator' ); ?>"
					>
						<?php echo $this->icon( 'locate' ); ?>
					</button>
					<ul id="vonarx-location-search-results" class="vonarx-locator__search-results" role="listbox" hidden></ul>
					<div id="vonarx-locate-status" class="vonarx-locator__locate-status" role="status" aria-live="polite" hidden></div>
				</div>

				<div class="vonarx-locator__chips-row">
					<div class="vonarx-locator__chips" id="vonarx-category-chips" role="group" aria-label="<?php esc_attr_e( 'Filter by category', 'vonarx-distributor-locator' ); ?>">
						<?php foreach ( self::CATEGORIES as $slug => $label ) : ?>
							<button type="button" class="vonarx-chip" data-category="<?php echo esc_attr( $slug ); ?>" aria-pressed="false">
								<?php echo esc_html( $label ); ?>
							</button>
						<?php endforeach; ?>
					</div>
					<button type="button" class="vonarx-locator__clear-chips" id="vonarx-clear-chips" hidden>
						<?php echo $this->icon( 'x' ); ?>
						<?php esc_html_e( 'Clear all', 'vonarx-distributor-locator' ); ?>
					</button>
				</div>
			</div>

			<div class="vonarx-locator__body">
				<div class="vonarx-locator__map-wrap">
					<div id="vonarx-map" class="vonarx-locator__map"></div>
					<button type="button" id="vonarx-map-locate-btn" class="vonarx-locator__map-locate-btn">
						<?php echo $this->icon( 'locate' ); ?>
						<span><?php esc_html_e( 'Use my location', 'vonarx-distributor-locator' ); ?></span>
					</button>
				</div>

				<aside class="vonarx-locator__sidebar" id="vonarx-locator-sidebar">
					<button
						type="button"
						class="vonarx-locator__toggle-handle"
						id="vonarx-sidebar-toggle"
						aria-expanded="true"
						aria-controls="vonarx-locator-sidebar-content"
					>
						<span class="vonarx-locator__toggle-label"><?php esc_html_e( 'Distributors', 'vonarx-distributor-locator' ); ?></span>
						<span class="vonarx-locator__toggle-icon" aria-hidden="true"><?php echo $this->icon( 'chevron' ); ?></span>
					</button>

					<div class="vonarx-locator__sidebar-content" id="vonarx-locator-sidebar-content">

						<div class="vonarx-locator__results">
							<h3 class="vonarx-locator__results-heading"><?php esc_html_e( 'Distributor Locations', 'vonarx-distributor-locator' ); ?></h3>
							<ul id="vonarx-store-list" class="vonarx-locator__list"></ul>
						</div>

					</div>
				</aside>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
