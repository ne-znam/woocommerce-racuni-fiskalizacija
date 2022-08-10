<?php
/**
 * Plugin Name:     Ne znam fiskalizator
 * Plugin URI:      https://nezn.am/fiskalizator
 * Description:     WooCommerce plugin za fiskalizaciju u Hrvatskoj
 * Author:          Marko Banušić
 * Author URI:      https://nezn.am
 * Text Domain:     neznam_fiskalizator
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         Neznam_fiskalizator
 */

defined( 'ABSPATH' ) || exit;

function neznam_fiskalizator_plugin() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		// You can handle this situation in a variety of ways,
		//   but adding a WordPress admin notice is often a good tactic.
		return 0;
	}

	require_once dirname(__FILE__) . '/vendor/autoload.php';

	$GLOBALS['neznam_fiskalizator'] = NeZnam\Fiskalizator\Init::instance();
}

add_action('plugin_loaded', 'neznam_fiskalizator_plugin', 10);
