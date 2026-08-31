<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a selectable page template (Page Attributes → Template) that
 * skips the active theme's header/footer/menu entirely, for a page whose
 * only content is the [vonarx_locator] shortcode.
 */
class Vonarx_Locator_Page_Template {

	const TEMPLATE = 'vonarx-locator-fullwidth.php';

	public function __construct() {
		add_filter( 'theme_page_templates', array( $this, 'register_template' ) );
		add_filter( 'template_include', array( $this, 'load_template' ) );
	}

	public function register_template( $templates ) {
		$templates[ self::TEMPLATE ] = __( 'VonArx Locator (Full Width, No Header/Footer)', 'vonarx-distributor-locator' );
		return $templates;
	}

	public function load_template( $template ) {
		if ( is_page() && self::TEMPLATE === get_page_template_slug() ) {
			$plugin_template = VONARX_LOCATOR_PATH . 'templates/' . self::TEMPLATE;
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}
}
