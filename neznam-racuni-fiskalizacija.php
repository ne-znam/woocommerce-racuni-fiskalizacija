<?php
/**
 * Plugin Name:     Ne znam fiskalizator
 * Plugin URI:      https://github.com/ne-znam/woocommerce-racuni-fiskalizacija
 * Description:     WooCommerce plugin za izdavanje računa i fiskalizaciju u Hrvatskoj
 * Author:          Marko Banušić
 * Author URI:      https://nezn.am
 * Text Domain:     neznam_racuni_fiskalizacija
 * Domain Path:     /languages
 * Version:         0.4.0
 */

defined( 'ABSPATH' ) || exit;

function neznam_racuni_fiskalizacija_plugin() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		// You can handle this situation in a variety of ways,
		// but adding a WordPress admin notice is often a good tactic.
		return 0;
	}

	require_once __DIR__ . '/vendor/autoload.php';

	$GLOBALS['neznam_racuni_fiskalizacija'] = NeZnam\FiscalInvoices\Init::instance();
}

add_action( 'plugin_loaded', 'neznam_racuni_fiskalizacija_plugin', 10 );
