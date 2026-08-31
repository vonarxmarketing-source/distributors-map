<?php
/**
 * Template Name: VonArx Locator (Full Width, No Header/Footer)
 * Description: Outputs the page content with no theme header, footer, or
 * navigation menu — for a page whose entire content is the [vonarx_locator]
 * shortcode, shown edge-to-edge.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
	<style>
		html, body { margin: 0; padding: 0; height: 100%; }
	</style>
</head>
<body <?php body_class( 'vonarx-locator-fullwidth' ); ?>>
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
	<?php wp_footer(); ?>
</body>
</html>
