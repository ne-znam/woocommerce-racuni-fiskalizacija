<?php

namespace NeZnam\Fiskalizator;

class Admin extends Instance {

	public function __construct() {
		add_filter( 'woocommerce_get_sections_tax', [$this, 'add_section']);
		add_filter( 'woocommerce_get_settings_tax', [$this, 'all_settings'], 10, 2 );
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
			$settings_slider = array();
			// Add Title to the Settings
			$settings_slider[] = array( 'name' => __( 'Postavke za fiskalizaciju', $this->slug ), 'type' => 'title', 'desc' => __( 'Ovdje možete namjestiti sve postavke vezane uz fiskalizaciju', $this->slug ), 'id' => $this->slug );
			// Add text field option
			$settings_slider[] = array(
				'name'     => __( 'Lokacija certifikata', $this->slug ),
				'desc_tip' => __( 'Unesite apsolutnu lokaciju certifikata', $this->slug ),
				'id'       => $this->slug . '_cert_path',
				'type'     => 'text',
				'desc'     => __( 'Morate unijeti točnu lokaciju FINA certifikata. Certifikat postavite na neku sigurno mjesto koje nije dostupno s weba.', $this->slug ),
				'default'  => '/home/user/FISKAL_1.p12'
			);

			$settings_slider[] = array(
				'name'     => __( 'Lozinka certifikata', $this->slug ),
				'desc_tip' => __( 'Unesite lozinku certifikata', $this->slug ),
				'id'       => $this->slug . '_cert_password',
				'type'     => 'password',
				'desc'     => __( 'Unesite lozinku koju ste namjestili za certifikat.', $this->slug ),
			);

			$settings_slider[] = array(
				'name'     => __( 'Oznaka poslovnog prostora', $this->slug ),
				'desc_tip' => __( 'Unesite oznaku poslovnog prostora koji ste registrirali u PP', $this->slug ),
				'id'       => $this->slug . '_business_area',
				'type'     => 'text',
				'desc'     => __( 'Unesite oznaku poslovnog prostora koji ste registrirali u PP', $this->slug ),
				'default'  => 'POSL1'
			);
			$settings_slider[] = array(
				'name'     => __( 'Oznaka uređaja', $this->slug ),
				'desc_tip' => __( 'Unesite oznaku uređaja', $this->slug ),
				'id'       => $this->slug . '_device_number',
				'type'     => 'text',
				'desc'     => __( 'Unesite oznaku uređaja', $this->slug ),
			);

			$settings_slider[] = array(
				'name'     => __( 'OIB webshopa', $this->slug ),
				'desc_tip' => __( 'Unesite OIB firme', $this->slug ),
				'id'       => $this->slug . '_company_oib',
				'type'     => 'text',
				'desc'     => __( 'OIB firme', $this->slug ),
			);

			$settings_slider[] = array(
				'name'     => __( 'OIB operatera', $this->slug ),
				'desc_tip' => __( 'Unesite OIB operatera/firme', $this->slug ),
				'id'       => $this->slug . '_operator_oib',
				'type'     => 'text',
				'desc'     => __( 'OIB operatera', $this->slug ),
			);

			$settings_slider[] = array(
				'name'     => __( 'Demo okruženje', $this->slug ),
				'desc_tip' => __( 'Odaberite ako želite samo testirati', $this->slug ),
				'id'       => $this->slug . '_sandbox',
				'type'     => 'checkbox',
				'desc'     => __( 'Za korištenje demo okruženja.', $this->slug ),
				'default'  => 1
			);

			$settings_slider[] = array( 'type' => 'sectionend', 'id' => $this->slug );
			return $settings_slider;

			/**
			 * If not, return the standard settings
			 **/
		} else {
			return $settings;
		}
	}
}
