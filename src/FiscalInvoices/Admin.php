<?php

namespace NeZnam\FiscalInvoices;



use Nticaric\Fiskalizacija\Fiskalizacija;

class Admin extends Instance {

	public function __construct() {
		add_filter( 'woocommerce_get_sections_tax', [$this, 'add_section']);
		add_filter( 'woocommerce_get_settings_tax', [$this, 'all_settings'], 10, 2 );
		add_filter('woocommerce_admin_settings_sanitize_option_'.$this->slug.'_cert_path', [$this, 'validate_cert'], 10, 3);
		add_filter('woocommerce_admin_settings_sanitize_option_'.$this->slug.'_cert_password', [$this, 'validate_cert_pass'], 10, 3);
		add_filter('woocommerce_admin_settings_sanitize_option_'.$this->slug.'_company_oib', [$this, 'validate_oib'], 10, 3);
		add_filter('woocommerce_admin_settings_sanitize_option_'.$this->slug.'_operator_oib', [$this, 'validate_oib'], 10, 3);
	}

	public function add_section( $sections ) {
		$sections[$this->slug] = __( 'Fiskalizacija', $this->slug );
		return $sections;
	}

	function all_settings( $settings, $current_section ) {
		/**
		 * Check the current section is what we want
		 **/
		if ( $current_section == $this->slug ) {
			$settings_invoices = array();
			// Add Title to the Settings
			$settings_invoices[] = array( 'name' => __( 'Postavke za fiskalizaciju', $this->slug ), 'type' => 'title', 'desc' => __( 'Ovdje možete namjestiti sve postavke vezane uz fiskalizaciju', $this->slug ), 'id' => $this->slug );
			// Add text field option
			$settings_invoices[] = array(
				'name'     => __( 'Lokacija certifikata', $this->slug ),
				'desc_tip' => __( 'Unesite apsolutnu lokaciju certifikata', $this->slug ),
				'id'       => $this->slug . '_cert_path',
				'type'     => 'text',
				'desc'     => __( 'Morate unijeti točnu lokaciju FINA certifikata. Certifikat postavite na neku sigurno mjesto koje nije dostupno s weba.', $this->slug ),
				'default'  => '/home/user/FISKAL_1.p12',
			);

			$settings_invoices[] = array(
				'name'     => __( 'Lozinka certifikata', $this->slug ),
				'desc_tip' => __( 'Unesite lozinku certifikata', $this->slug ),
				'id'       => $this->slug . '_cert_password',
				'type'     => 'password',
				'desc'     => __( 'Unesite lozinku koju ste namjestili za certifikat.', $this->slug ),
			);

			$settings_invoices[] = array(
				'name'     => __( 'Oznaka poslovnog prostora', $this->slug ),
				'desc_tip' => __( 'Unesite oznaku poslovnog prostora koji ste registrirali u PP', $this->slug ),
				'id'       => $this->slug . '_business_area',
				'type'     => 'text',
				'desc'     => __( 'Unesite oznaku poslovnog prostora koji ste registrirali u PP', $this->slug ),
				'default'  => 'POSL1'
			);
			$settings_invoices[] = array(
				'name'     => __( 'Oznaka uređaja', $this->slug ),
				'desc_tip' => __( 'Unesite oznaku uređaja', $this->slug ),
				'id'       => $this->slug . '_device_number',
				'type'     => 'text',
				'desc'     => __( 'Unesite oznaku uređaja', $this->slug ),
			);

			$settings_invoices[] = array(
				'name'     => __( 'OIB webshopa', $this->slug ),
				'desc_tip' => __( 'Unesite OIB obveznika fiskalizacije', $this->slug ),
				'id'       => $this->slug . '_company_oib',
				'type'     => 'text',
				'desc'     => __( 'OIB obveznika fiskalizacije', $this->slug ),
			);

			$settings_invoices[] = array(
				'name'     => __( 'OIB operatera', $this->slug ),
				'desc_tip' => __( 'Unesite OIB operatera/firme', $this->slug ),
				'id'       => $this->slug . '_operator_oib',
				'type'     => 'text',
				'desc'     => __( 'OIB operatera', $this->slug ),
			);

			$settings_invoices[] = array(
				'name'     => __( 'Demo okruženje', $this->slug ),
				'desc_tip' => __( 'Odaberite ako želite samo testirati', $this->slug ),
				'id'       => $this->slug . '_sandbox',
				'type'     => 'checkbox',
				'desc'     => __( 'Za korištenje demo okruženja.', $this->slug ),
				'default'  => 1
			);

			$settings_invoices[] = array( 'type' => 'sectionend', 'id' => $this->slug );
			return $settings_invoices;

			/**
			 * If not, return the standard settings
			 **/
		} else {
			return $settings;
		}
	}

	public function validate_cert($value, $option, $raw_value) {
		// try to open cert
		$f = file_get_contents($value);
		if (false === $f) {
			\WC_Admin_Settings::add_error( __( 'Lokacija certifikata nije ispravna', 'woocommerce' ) );
		}
		return $value;
	}

	public function validate_cert_pass($value, $option, $raw_value) {
		$certPath = get_option($this->slug . '_cert_path');
		try {
			$fis = new Fiskalizacija($certPath, $value, 'TLS', true);
			if (!$fis->getPrivateKey()) {
				\WC_Admin_Settings::add_error( __( 'Lozinka i certifikat se ne podudaraju', 'woocommerce' ) );
			}
		} catch (\Exception $e) {
			\WC_Admin_Settings::add_error( __( 'Lozinka i certifikat se ne podudaraju', 'woocommerce' ) );
		}
		return $value;
	}

	/**
	 * @param $oib
	 * @param $option
	 * @param $raw_value
	 *
	 *
	 * https://github.com/domagojpa/oib-validation/blob/main/PHP/oib-validation.php
	 */
	public function validate_oib($oib, $option, $raw_value) {
		if (strlen($oib) != 11 || !is_numeric($oib)) {
			\WC_Admin_Settings::add_error( __( $option['name'] . ' nema ispravan broj znamenki', $this->slug ) );
		}
		$a = 10;
		for ($i = 0; $i < 10; $i++) {
			$a += (int)$oib[$i];
			$a %= 10;
			if ( $a == 0 ) { $a = 10; }
			$a *= 2;
			$a %= 11;
		}
		$kontrolni = 11 - $a;
		if ( $kontrolni == 10 ) { $kontrolni = 0; }
		if ($kontrolni != intval(substr($oib, 10, 1), 10)) {
			\WC_Admin_Settings::add_error( __( $option['name'] . ' nije ispravan', $this->slug ) );
		}
		return $oib;
	}
}
