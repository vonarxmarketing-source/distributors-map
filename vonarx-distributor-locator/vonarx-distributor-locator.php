<?php
/**
 * Plugin Name: VonArx Distributor Locator
 * Description: Manage and display VonArx distributor locations on an interactive map via the [vonarx_locator] shortcode.
 * Version: 1.3.1
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

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

define( 'VONARX_LOCATOR_VERSION', '1.3.1' );
define( 'VONARX_LOCATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'VONARX_LOCATOR_URL', plugin_dir_url( __FILE__ ) );

require_once VONARX_LOCATOR_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
require_once VONARX_LOCATOR_PATH . 'includes/class-post-type.php';
require_once VONARX_LOCATOR_PATH . 'includes/class-rest-api.php';
require_once VONARX_LOCATOR_PATH . 'includes/class-settings.php';
require_once VONARX_LOCATOR_PATH . 'includes/class-shortcode.php';
require_once VONARX_LOCATOR_PATH . 'includes/class-page-template.php';
require_once VONARX_LOCATOR_PATH . 'includes/class-sample-data.php';

/**
 * Checks the plugin's GitHub repo for updates instead of WordPress.org,
 * since this plugin isn't published there. Reads the version number and
 * download ZIP from a GitHub Release's attached asset (enableReleaseAssets())
 * rather than the repo's auto-generated branch/tag archive — required here
 * because the plugin lives in a subdirectory of the repo
 * (vonarx-distributor-locator/), not at its root, so a whole-repo archive
 * wouldn't have the right folder structure to install as an update.
 *
 * No releases exist on the repo yet, so until one is published with a
 * vonarx-distributor-locator.zip asset attached (matching dist/, built via
 * the project's own zip step), this simply finds nothing to update to.
 */
function vonarx_locator_init_update_checker() {
	$update_checker = PucFactory::buildUpdateChecker(
		'https://github.com/vonarxmarketing-source/distributors-map/',
		__FILE__,
		'vonarx-distributor-locator'
	);

	$update_checker->setBranch( 'main' );
	$update_checker->getVcsApi()->enableReleaseAssets( '/\.zip($|[?&#])/i' );
}
add_action( 'plugins_loaded', 'vonarx_locator_init_update_checker' );

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
