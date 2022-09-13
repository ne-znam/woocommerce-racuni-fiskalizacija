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
use WC_Order;
use WC_Order_Item_Product;
use WC_Tax;
use WP_Query;

class Order extends Instance {

	public function __construct() {
		add_action( 'woocommerce_order_status_changed', [
			$this,
			'process_completed_order'
		], 1, 4 );
		add_action( 'woocommerce_email_customer_details', [
			$this,
			'add_data_to_email'
		], 30, 4 );
	}

	/**
	 * @param WC_Order $order
	 * @param $sent_to_admin
	 * @param $plain_text
	 * @param $email
	 *
	 * @return void
	 */
	public function add_data_to_email( $order, $sent_to_admin, $plain_text, $email ) {

		if ( ! $order->get_meta( '_invoice_number' ) ) {
			// we don't have fiscalisation data, so we skip this part
			return;
		}
		$invoice_id = $order->get_meta( '_invoice_number' );
		if ( $plain_text ) {
			echo "JIR: " . get_post_meta( $invoice_id, $this->slug . '_jir' ) . "\n";
			echo "ZKI: " . $order->get_meta( $invoice_id, $this->slug . '_zki' ) . "\n";
			echo "URL za provjeru: " . $order->get_meta( $invoice_id, $this->slug . '_qr_code_link' );
		} else {
			$url = get_post_meta( $invoice_id, $this->slug . '_qr_code_link', true );
			$qr  = new QrCode( $url, 200, 200 );
			?>
			<p>
				Račun broj: <?php echo sprintf('%s/%s/%s', get_post_meta($invoice_id, '_invoice_number', true), get_option( $this->slug . '_business_area' ), get_option( $this->slug . '_device_number' )) ?>
				<br>
				JIR: <?php echo get_post_meta( $invoice_id, $this->slug . '_jir' ) ?>
				<br>
				ZKI: <?php echo get_post_meta( $invoice_id, $this->slug . '_zki' ) ?>
				<br>
				<img
					src="data:image/png;base64,<?php echo base64_encode( $qr->getPngData() ) ?>">
			</p>
			<?php
		}
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
		$status = get_option($this->slug . '_when_fis');
		if ( $status != 'manually' && $to === $status ) {
			return $this->process_order($order);
		}
		return 0;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return int|\WP_Error
	 */
	public function process_order( WC_Order $order) {
		$area         = get_option( $this->slug . '_business_area' );
		$device       = get_option( $this->slug . '_device_number' );
		$invoice_format = get_option( $this->slug . '_invoice_format', '%s/%s/%s');
		// get sequential number for invoice
		$invoice_number = Invoice::instance()->generateInvoiceNumber( $order->get_date_paid()->format('Y') );
		// create new invoice, store data for invoice
		$id = wp_insert_post([
			'post_type' => 'neznam_invoice',
			'post_title' => apply_filters($this->slug . '_invoice_format', sprintf($invoice_format, $invoice_number, $area, $device), $invoice_number, $area, $device),
			'post_status' => 'publish',
		]);
		add_post_meta($id, '_order_id', $order->get_id());
		add_post_meta($id, '_invoice_number', $invoice_number);
		Invoice::instance()->processOrder($order, $id);

		Invoice::instance()->processFiscal($id, $order);
		return $id;
	}
}
