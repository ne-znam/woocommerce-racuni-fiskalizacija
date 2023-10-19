<?php

namespace NeZnam\FiscalInvoices;

use Com\Tecnick\Barcode\Type\Square\QrCode;
use DOMDocument;
use Exception;
use Nticaric\Fiskalizacija\Bill\Bill;
use Nticaric\Fiskalizacija\Bill\BillNumber;
use Nticaric\Fiskalizacija\Bill\BillRequest;
use Nticaric\Fiskalizacija\Bill\TaxRate;
use Nticaric\Fiskalizacija\Fiskalizacija;
use PHPMailer\PHPMailer\PHPMailer;
use WC_Order;
use WC_Order_Item_Product;
use WC_Tax;
use WP_Query;

class Order extends Instance {

	public function __construct() {
		add_action(
			'woocommerce_order_status_changed',
			array(
				$this,
				'process_completed_order',
			),
			1,
			4
		);
	}

	/**
	 * @param $order_id
	 * @param $from
	 * @param $to
	 * @param $order
	 *
	 * @return int
	 * @throws Exception
	 */
	public function process_completed_order( $order_id, $from, $to, $order ) {
		$status = get_option( $this->slug . '_when_fis' );
		if ( $status === 'manually' ) {
			return 0;
		}
		$status = str_replace( 'wc-', '', $status );
		if ( $to === $status ) {
			return $this->process_order( $order );
		}
		return 0;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return int|\WP_Error
	 */
	public function process_order( WC_Order $order ) {
		$payment = $order->get_payment_method();
		$create_invoice = !(get_option( $this->slug . '_' . $payment . '_invoice', 'no' ) === 'no');
		if (!$create_invoice) {
			return;
		}
		$area           = get_option( $this->slug . '_business_area' );
		$device         = get_option( $this->slug . '_device_number' );
		$invoice_format = get_option( $this->slug . '_invoice_format', '%s/%s/%s' );
		$sandbox = !(get_option( $this->slug . '_sandbox', 'no' ) === 'no');
		// get sequential number for invoice
		// use the current year
		$invoice_number = Invoice::instance()->generateInvoiceNumber( date('Y'), $sandbox );
		// create new invoice, store data for invoice
		$id = wp_insert_post(
			array(
				'post_type'   => 'neznam_invoice',
				'post_title'  => apply_filters( $this->slug . '_invoice_format', sprintf( $invoice_format, $invoice_number, $area, $device ), $invoice_number, $area, $device ),
				'post_status' => 'publish',
			)
		);
		add_post_meta( $id, '_order_id', $order->get_id() );
		add_post_meta( $id, '_invoice_number', $invoice_number );
		if ($sandbox) {
			add_post_meta( $id, '_sandbox', true );
		}
		//create invoice
		Invoice::instance()->processOrder( $order, $id );

		//fiscal
		Invoice::instance()->processFiscal( $id, $order );

		// send email
		WC()->payment_gateways();
		WC()->shipping();
		WC()->mailer()->customer_invoice( $order );
		return $id;
	}
}
