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
	 * @return void
	 * @throws Exception
	 */
	public function process_completed_order( $order_id, $from, $to, $order ) {
		// TODO: set order status as user desires
		if ( $to === 'completed' ) {
			$area         = get_option( $this->slug . '_business_area' );
			$device       = get_option( $this->slug . '_device_number' );
			// get sequential number for invoice
			$invoice_number = $this->generateInvoiceNumber( $order );
			// create new invoice, store data for invoice
			$id = wp_insert_post([
				'post_type' => 'neznam_invoice',
				'post_title' => sprintf("%s/%s/%s", $invoice_number, $area, $device),
				'post_status' => 'publish',
			]);
			add_post_meta($id, '_order_id', $order_id);
			add_post_meta($id, '_invoice_number', $invoice_number);
			Invoice::instance()->processFiscal($id);
		}
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return int
	 */
	public function generateInvoiceNumber( WC_Order $order ) {
		//get last number in year of order
		$paid_at = $order->get_date_paid();
		$q       = new WP_Query( [
			'post_status'         => 'any',
			'posts_per_page'      => 1,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'post_type'           => 'neznam_invoice',
			'year'                => $paid_at->date( 'Y' ),
			'orderby'             => 'meta_value_num',
			'meta_key'            => '_invoice_number',
			'order'               => 'desc',
		] );
		if ( $q->have_posts() ) {
			$q->the_post();
			$receipt_number = (int) get_post_meta( $q->post->ID, '_invoice_number', true );
			$receipt_number ++;
		} else {
			$receipt_number = 1;
		}

		return $receipt_number;
	}

}
