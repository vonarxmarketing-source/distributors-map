<?php
/**
 * Plugin Name: VonArx Distributor Locator
 * Description: Manage and display VonArx distributor locations on an interactive map via the [vonarx_locator] shortcode.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: VonArx Distributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vonarx-distributor-locator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VONARX_LOCATOR_VERSION', '1.0.0' );
define( 'VONARX_LOCATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'VONARX_LOCATOR_URL', plugin_dir_url( __FILE__ ) );

require_once VONARX_LOCATOR_PATH . 'includes/class-post-type.php';
require_once VONARX_LOCATOR_PATH . 'includes/class-rest-api.php';
require_once VONARX_LOCATOR_PATH . 'includes/class-settings.php';
require_once VONARX_LOCATOR_PATH . 'includes/class-shortcode.php';
require_once VONARX_LOCATOR_PATH . 'includes/class-page-template.php';
require_once VONARX_LOCATOR_PATH . 'includes/class-sample-data.php';

/**
 * Boot the plugin's components.
 */
function vonarx_locator_init() {
	new Vonarx_Locator_Post_Type();
	new Vonarx_Locator_REST_API();
	new Vonarx_Locator_Settings();
	new Vonarx_Locator_Shortcode();
	new Vonarx_Locator_Page_Template();
}
add_action( 'plugins_loaded', 'vonarx_locator_init' );

/**
 * Register the CPT/taxonomy, seed the default Product Groups, and flush
 * rewrite rules on activation so archive/permalinks work immediately.
 */
function vonarx_locator_activate() {
	$post_type = new Vonarx_Locator_Post_Type();
	$post_type->register_post_type();
	$post_type->register_taxonomy();

	$default_product_groups = array(
		'scarifiers'      => 'Scarifiers',
		'shavers'         => 'Shavers',
		'dust-extractors' => 'Dust extractors',
		'shotblasters'    => 'Shotblasters',
		'dust-collectors' => 'Dust collectors',
		'grinders'        => 'Grinders',
		'pneumatic-tools' => 'Pneumatic tools',
	);
	foreach ( $default_product_groups as $slug => $label ) {
		if ( ! term_exists( $slug, Vonarx_Locator_Post_Type::TAXONOMY ) ) {
			wp_insert_term( $label, Vonarx_Locator_Post_Type::TAXONOMY, array( 'slug' => $slug ) );
		}
	}

	Vonarx_Locator_Sample_Data::maybe_import();

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'vonarx_locator_activate' );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
