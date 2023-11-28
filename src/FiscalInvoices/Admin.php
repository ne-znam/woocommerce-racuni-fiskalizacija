<?php

namespace NeZnam\FiscalInvoices;

use Exception;
use Nticaric\Fiskalizacija\Fiskalizacija;
use WC_Admin_Settings;

class Admin extends Instance {

	public function __construct() {
		add_filter( 'woocommerce_get_sections_tax', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_tax', array( $this, 'all_settings' ), 10, 2 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . $this->slug . '_cert_path', array( $this, 'validate_cert' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . $this->slug . '_cert_password', array( $this, 'validate_cert_pass' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . $this->slug . '_company_oib', array( $this, 'validate_oib' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . $this->slug . '_operator_oib', array( $this, 'validate_oib' ), 10, 3 );
		add_filter( 'plugin_action_links_neznam-racuni-fiskalizacija/neznam-racuni-fiskalizacija.php', array( $this, 'settings_link' ), 10, 1 );
		add_filter( 'woocommerce_order_actions', array( $this, 'order_actions' ), 10, 2 );
		add_action( 'woocommerce_order_action_' . $this->slug . '_get_invoice', array( $this, 'do_order_action' ), 10, 1 );
		add_action('restrict_manage_posts', array($this, 'restrict_posts'), 10);
		add_filter('parse_query', array( $this, 'parse_query'));

	}

		function restrict_posts($post_type) {
			if ('neznam_invoice' !== $post_type) {
				return;
			}
			$current_v = isset($_GET['neznam_payment_method'])? $_GET['neznam_payment_method'] : '';
			$methods = array(
				'N' => 'Ne fiskalizirati',
                 'T' => 'Transakcijski račun',
                 'G' => 'Gotovina',
                 'K' => 'Kartice',
                 'C' => 'Ček',
                 'O' => 'Ostalo'
			)
			?>
			<select name="neznam_payment_method">
				<option value="">Odaberi način plaćanja</option>
				<?php foreach ($methods as $key => $method) {
					?><option value="<?php echo $key; ?>" <?php selected($current_v, $key) ?>><?php echo $method ?></option><?php
				}?>
			</select>
			<?php
		}

		function parse_query($query) {
			global $pagenow;
			$post_type = (isset($_GET['post_type'])) ? $_GET['post_type'] : 'post';
			if ($post_type != 'neznam_invoice' ||  $pagenow != 'edit.php') {
				return;
			}
			$method = false;
			if ( !empty($_GET['neznam_payment_method']) ) {
				$method = $_GET['neznam_payment_method'];
			}

			if (!$method) {
				return;
			}

			$query->set('meta_key',  'payment_method');
			$query->set('meta_value',  $method);
		}

	function do_order_action( $order ) {
		$c  = Order::instance();
		$id = $c->process_order( $order );
		wp_redirect( get_edit_post_link( $id, true ) );
		exit();
	}

	function order_actions( $actions, $order ) {
		$actions[ $this->slug . '_get_invoice' ] = __( 'Izdaj fiskalni račun', $this->slug );

		return $actions;
	}

	function settings_link( $links ) {
		// Build and escape the URL.
		$url = esc_url(
			add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'tax',
					'section' => $this->slug,
				),
				get_admin_url() . 'admin.php'
			)
		);
		// Create the link.
		$settings_link = "<a href='$url'>" . __( 'Postavke', $this->slug ) . '</a>';
		// Adds the link to the end of the array.
		array_unshift(
			$links,
			$settings_link
		);
		return $links;
	}

	public function add_section( $sections ) {
		$sections[ $this->slug ] = __( 'Fiskalizacija', $this->slug );
		return $sections;
	}

	function all_settings( $settings, $current_section ) {
		/**
		 * Check the current section is what we want
		 */
		if ( $current_section === $this->slug ) {
			$settings_invoices   = array();
			$settings_invoices[] = array(
				'name'
						=> __( 'Postavke za fiskalizaciju', $this->slug ),
				'type' => 'title',
				'desc' => __( 'Ovdje možete namjestiti sve postavke vezane uz fiskalizaciju', $this->slug ),
				'id'   => $this->slug . '_basic',
			);
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
				'default'  => 'POSL1',
			);
			$settings_invoices[] = array(
				'name'     => __( 'Oznaka uređaja', $this->slug ),
				'desc_tip' => __( 'Unesite oznaku uređaja', $this->slug ),
				'id'       => $this->slug . '_device_number',
				'type'     => 'text',
				'desc'     => __( 'Unesite oznaku uređaja', $this->slug ),
			);

			$settings_invoices[] = array(
				'name'     => __( 'Izgled broja računa', $this->slug ),
				'desc_tip' => __( 'Unesite željeni izgled broja računa', $this->slug ),
				'id'       => $this->slug . '_invoice_format',
				'type'     => 'text',
				'desc'     => __( 'Unesite željeni izgled broja računa za sprintf("%1$s/%2$s/%3$s", broj racuna, oznaka poslovnog prostora, oznaka uredaja)', $this->slug ),
				'default'  => '%s/%s/%s',
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
				'default'  => 1,
			);

			// order status
			$statuses             = wc_get_order_statuses();
			$statuses['manually'] = __( 'Ručno', $this->slug );
			$settings_invoices[]  = array(
				'title'   => 'Kada fiskalizirati',
				'id'      => $this->slug . '_when_fis',
				'default' => 'manually',
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'options' => $statuses,
			);

			$settings_invoices[] = array(
				'type' => 'sectionend',
				'id'   => $this->slug . '_basic',
			);

			$settings_invoices[] = array(
				'name' => __( 'Postavke za načine plaćanja', $this->slug ),
				'type' => 'title',
				'desc' => __( 'Uredite za koje vrste plaćanja treba izdati račun i fiskalizirati. ', $this->slug ),
				'id'   => $this->slug . '_payments',
			);

			// get all active payment types
			$gateways = new \WC_Payment_Gateways();
			$payments = $gateways->get_available_payment_gateways();
			/** @var \WC_Payment_Gateway $payment */
			foreach ( $payments as $payment ) {
				$settings_invoices[] = array(
					'name' => $payment->get_title(),
					'type' => 'title',
					'id'   => $this->slug . '_payments_'.$payment->id,
				);
				$settings_invoices[] = array(
					'title'   => 'Izdati račun:',
					'id'      => $this->slug . '_' . $payment->id . '_invoice',
					'default' => 'no',
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'options' => array(
						'no' => 'ne',
						'da' => 'da',
					),
				);
				$settings_invoices[] = array(
					'title'   => 'Fiskalizirati:',
					'id'      => $this->slug . '_' . $payment->id,
					'default' => 'N',
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'options' => array(
						'N' => 'Ne fiskalizirati',
						'T' => 'Transakcijski račun',
						'G' => 'Gotovina',
						'K' => 'Kartice',
						'C' => 'Ček',
						'O' => 'Ostalo',
					),
				);

				$settings_invoices[] = array(
					'type' => 'sectionend',
					'id'   => $this->slug . '_payments_'.$payment->id,
				);

			}

			$settings_invoices[] = array(
				'type' => 'sectionend',
				'id'   => $this->slug . '_payments',
			);

			return $settings_invoices;

			/**
			 * If not, return the standard settings
			 */
		} else {
			return $settings;
		}
	}

	public function validate_cert( $value, $option, $raw_value ) {
		// try to open cert
		$f = file_get_contents( $value );
		if ( false === $f ) {
			WC_Admin_Settings::add_error( __( 'Lokacija certifikata nije ispravna', 'woocommerce' ) );
		}

		return $value;
	}

	public function validate_cert_pass( $value, $option, $raw_value ) {

		$certPath = $_POST[ $this->slug . '_cert_path' ];

		try {
			$fis = new Fiskalizacija( $certPath, $value, 'TLS', false );
			if ( ! $fis->getPrivateKey() ) {
				WC_Admin_Settings::add_error( __( 'Lozinka i certifikat se ne podudaraju', 'woocommerce' ) );
			}
		} catch ( Exception $e ) {
			WC_Admin_Settings::add_error( __( 'Lozinka i certifikat se ne podudaraju1', 'woocommerce' ) );
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
	public function validate_oib( $oib, $option, $raw_value ) {
		if ( 11 !== strlen( $oib ) || ! is_numeric( $oib ) ) {
			WC_Admin_Settings::add_error( __( $option['name'] . ' nema ispravan broj znamenki', $this->slug ) );
		}
		$a = 10;
		for ( $i = 0; $i < 10; $i++ ) {
			$a += (int) $oib[ $i ];
			$a %= 10;
			if ( 0 === $a ) {
				$a = 10;
			}
			$a *= 2;
			$a %= 11;
		}
		$kontrolni = 11 - $a;
		if ( 10 === $kontrolni ) {
			$kontrolni = 0;
		}
		if ( $kontrolni !== intval( substr( $oib, 10, 1 ), 10 ) ) {
			WC_Admin_Settings::add_error( __( $option['name'] . ' nije ispravan', $this->slug ) );
		}

		return $oib;
	}
}
