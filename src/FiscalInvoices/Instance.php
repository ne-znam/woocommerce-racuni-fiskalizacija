<?php

namespace NeZnam\FiscalInvoices;

abstract class Instance {

	private static $instances = [];

	public $slug = 'neznam_racuni_fiskalizacija';

	/**
	 * Constructor
	 *
	 */
	abstract protected function __construct();

	public static function instance() {
		$classname = get_called_class();
		if ( ! isset( self::$instances[ $classname ] ) ) {
			self::$instances[ $classname ] = new $classname();
		}

		return self::$instances[ $classname ];
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		// Override this PHP function to prevent unwanted copies of your instance.
		_doing_it_wrong( 'clone', 'No cloning', '1.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		// Override this PHP function to prevent unwanted copies of your instance.
		_doing_it_wrong( 'clone', 'No cloning', '1.0' );
	}
}
