<?php

namespace NeZnam\Fiskalizator;

class Admin extends Instance {

	public function __construct() {
		add_filter( 'woocommerce_get_sections_tax', [$this, 'add_section']);
		add_filter( 'woocommerce_get_settings_tax', [$this, 'all_settings'], 10, 2 );
	}

	public function add_section( $sections ) {
		$sections['neznam_fiskalizator'] = __( 'Fiskalizacija', 'neznam_fiskalizator' );
		return $sections;
	}

	function all_settings( $settings, $current_section ) {
		var_dump($current_section);
		/**
		 * Check the current section is what we want
		 **/
		if ( $current_section == 'neznam_fiskalizator' ) {
			$settings_slider = array();
			// Add Title to the Settings
			$settings_slider[] = array( 'name' => __( 'Postavke za fiskalizaciju', 'neznam_fiskalizator' ), 'type' => 'title', 'desc' => __( 'Ovdje možete namjestiti sve postavke vezane uz fiskalizaciju', 'neznam_fiskalizator' ), 'id' => 'neznam_fiskalizator' );
			// Add text field option
			$settings_slider[] = array(
				'name'     => __( 'Lokacija certifikata', 'neznam_fiskalizator' ),
				'desc_tip' => __( 'Unesite apsolutnu lokaciju certifikata', 'neznam_fiskalizator' ),
				'id'       => 'neznam_fiskalizator_cert_path',
				'type'     => 'text',
				'desc'     => __( 'Morate unijeti točnu lokaciju FINA certifikata. Certifikat postavite na neku sigurno mjesto koje nije dostupno s weba.', 'neznam_fiskalizator' ),
				'default'  => '/home/user/FISKAL_1.p12'
			);

			$settings_slider[] = array(
				'name'     => __( 'Lozinka certifikata', 'neznam_fiskalizator' ),
				'desc_tip' => __( 'Unesite lozinku certifikata', 'neznam_fiskalizator' ),
				'id'       => 'neznam_fiskalizator_cert_password',
				'type'     => 'password',
				'desc'     => __( 'Unesite lozinku koju ste namjestili za certifikat.', 'neznam_fiskalizator' ),
			);

			$settings_slider[] = array(
				'name'     => __( 'Oznaka poslovnog prostora', 'neznam_fiskalizator' ),
				'desc_tip' => __( 'Unesite oznaku poslovnog prostora koji ste registrirali u PP', 'neznam_fiskalizator' ),
				'id'       => 'neznam_fiskalizator_bussiness_area',
				'type'     => 'text',
				'desc'     => __( 'Unesite oznaku poslovnog prostora koji ste registrirali u PP', 'neznam_fiskalizator' ),
				'default'  => 'POSL1'
			);
			$settings_slider[] = array(
				'name'     => __( 'Oznaka uređaja', 'neznam_fiskalizator' ),
				'desc_tip' => __( 'Unesite oznaku uređaja', 'neznam_fiskalizator' ),
				'id'       => 'neznam_fiskalizator_bussiness_area',
				'type'     => 'text',
				'desc'     => __( 'Unesite oznaku uređaja', 'neznam_fiskalizator' ),
				'default'  => 1
			);

			$settings_slider[] = array( 'type' => 'sectionend', 'id' => 'neznam_fiskalizator' );
			return $settings_slider;

			/**
			 * If not, return the standard settings
			 **/
		} else {
			return $settings;
		}
	}
}
