<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the [vonarx_locator] shortcode and its frontend assets.
 */
class Vonarx_Locator_Shortcode {

	public function __construct() {
		add_shortcode( 'vonarx_locator', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Top bar filter chips, sourced from the vonarx_product_group taxonomy
	 * (slug => name) so newly added Product Groups show up automatically.
	 */
	private function get_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => Vonarx_Locator_Post_Type::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$categories = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[ $term->slug ] = $term->name;
			}
		}
		return $categories;
	}

	/**
	 * Applies the admin's Typography & Colors settings (Vonarx_Locator_
	 * Settings) as inline overrides of the .vonarx-locator CSS custom
	 * properties — attached to our own registered stylesheet via
	 * wp_add_inline_style(), so it always loads after (and wins over)
	 * locator.css's defaults, independent of whatever the active theme does.
	 */
	private function enqueue_style_overrides() {
		$settings = Vonarx_Locator_Settings::get_settings();
		$map      = array(
			'font_size_base'       => '--font-size-base',
			'font_size_small'      => '--font-size-sm',
			'font_size_chip'       => '--font-size-chip',
			'font_size_popup_tag'  => '--font-size-popup-tag',
			'font_size_popup_btn'  => '--font-size-popup-btn',
			'color_primary'        => '--color-primary',
			'color_accent'         => '--color-accent',
			'color_text'           => '--color-text',
			'color_text_muted'     => '--color-text-muted',
			'color_popup_btn_bg'   => '--color-popup-btn-bg',
			'color_popup_btn_text' => '--color-popup-btn-text',
		);

		$declarations = array();
		foreach ( $map as $setting_key => $css_var ) {
			$default = isset( Vonarx_Locator_Settings::STYLE_DEFAULTS[ $setting_key ] ) ? Vonarx_Locator_Settings::STYLE_DEFAULTS[ $setting_key ] : '';
			if ( ! empty( $settings[ $setting_key ] ) && $settings[ $setting_key ] !== $default ) {
				$declarations[] = $css_var . ': ' . $settings[ $setting_key ] . ';';
			}
		}

		if ( $declarations ) {
			wp_add_inline_style( 'vonarx-locator', '.vonarx-locator { ' . implode( ' ', $declarations ) . ' }' );
		}
	}

	public function register_assets() {
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
			array( 'leaflet' ),
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
				'categories' => $this->get_categories(),
			)
		);
	}

	/**
	 * Returns hardcoded inline SVG markup for one of a fixed set of icon
	 * names, echoed unescaped by callers below. Safe: $name is always a
	 * hardcoded literal passed by this class's own render() (never derived
	 * from request/user input), and every returned string is one of the
	 * static SVG literals below — there is nothing here for an attacker to
	 * control, so escaping (which would just entity-encode the markup into
	 * visible text) isn't applicable.
	 */
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
		wp_enqueue_style( 'vonarx-locator' );
		wp_enqueue_script( 'leaflet' );
		wp_enqueue_script( 'vonarx-locator' );

		$this->enqueue_style_overrides();

		ob_start();
		?>
		<div class="vonarx-locator" id="vonarx-locator">

			<div class="vonarx-locator__topbar">
				<div class="vonarx-locator__search-wrap">
					<div class="vonarx-locator__search-row">
						<span class="vonarx-locator__search-icon" aria-hidden="true"><?php echo $this->icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG, see icon() docblock. ?></span>
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
							<?php echo $this->icon( 'locate' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG, see icon() docblock. ?>
						</button>
						<ul id="vonarx-location-search-results" class="vonarx-locator__search-results" role="listbox" hidden></ul>
					</div>
					<div id="vonarx-locate-status" class="vonarx-locator__locate-status" role="status" aria-live="polite" hidden></div>
				</div>

				<div class="vonarx-locator__chips-row">
					<div class="vonarx-locator__chips" id="vonarx-category-chips" role="group" aria-label="<?php esc_attr_e( 'Filter by category', 'vonarx-distributor-locator' ); ?>">
						<?php foreach ( $this->get_categories() as $slug => $label ) : ?>
							<button type="button" class="vonarx-chip" data-category="<?php echo esc_attr( $slug ); ?>" aria-pressed="false">
								<?php echo esc_html( $label ); ?>
							</button>
						<?php endforeach; ?>
					</div>

					<!-- Mobile/tablet-portrait (<1024px) equivalent of the chips above:
					     a checkbox-list-in-a-popover instead of a row of toggle chips.
					     Shares the same selection state via JS; CSS shows only one of
					     the two depending on viewport width. -->
					<div class="vonarx-locator__category-dropdown" id="vonarx-category-dropdown">
						<button
							type="button"
							class="vonarx-locator__category-dropdown-toggle"
							id="vonarx-category-dropdown-toggle"
							aria-haspopup="true"
							aria-expanded="false"
							aria-controls="vonarx-category-dropdown-panel"
						>
							<span class="vonarx-locator__category-dropdown-label"><?php esc_html_e( 'Filter by category', 'vonarx-distributor-locator' ); ?></span>
							<span class="vonarx-locator__category-dropdown-icon" aria-hidden="true"><?php echo $this->icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG, see icon() docblock. ?></span>
						</button>
						<div class="vonarx-locator__category-dropdown-panel" id="vonarx-category-dropdown-panel" hidden>
							<ul class="vonarx-locator__category-dropdown-list">
								<?php foreach ( $this->get_categories() as $slug => $label ) : ?>
									<li>
										<label class="vonarx-locator__category-dropdown-item">
											<input type="checkbox" class="vonarx-locator__category-dropdown-checkbox" value="<?php echo esc_attr( $slug ); ?>" />
											<span><?php echo esc_html( $label ); ?></span>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>

					<button type="button" class="vonarx-locator__clear-chips" id="vonarx-clear-chips" hidden>
						<?php echo $this->icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG, see icon() docblock. ?>
						<?php esc_html_e( 'Clear all', 'vonarx-distributor-locator' ); ?>
					</button>
				</div>
			</div>

			<div class="vonarx-locator__body">
				<div class="vonarx-locator__map-wrap">
					<div id="vonarx-map" class="vonarx-locator__map"></div>
					<button type="button" id="vonarx-map-locate-btn" class="vonarx-locator__map-locate-btn">
						<?php echo $this->icon( 'locate' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG, see icon() docblock. ?>
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
						<span class="vonarx-locator__toggle-icon" aria-hidden="true"><?php echo $this->icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG, see icon() docblock. ?></span>
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
