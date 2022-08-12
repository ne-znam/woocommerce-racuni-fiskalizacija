<?php
namespace NeZnam\FiscalInvoices;

class Cli extends \WP_CLI_Command {

	private static $_instance = null;

	public function __construct() {
		\WP_CLI::add_command( 'fiskalizacija', $this );
	}

	public static function instance () {
		$classname = get_called_class();
		if ( is_null( self::$_instance ) )
			self::$_instance = new $classname();
		return self::$_instance;
	}

	public function test() {
		$order = Order::instance();
		$o = wc_get_order(17);
		$order->generateReceiptNumber($o);
		$order->processOrder($o);
	}
}
