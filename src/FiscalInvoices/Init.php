<?php

namespace NeZnam\FiscalInvoices;

class Init extends Instance {
	public function __construct() {
		$this->init_hooks();
		do_action( 'neznam_fiskalizator_loaded' );
	}

	public function init_hooks() {
		Admin::instance();
		Order::instance();
		if (defined( 'WP_CLI' ) && WP_CLI) {
			Cli::instance();
		}
	}
}


