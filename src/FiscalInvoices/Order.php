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
		add_action(
			'woocommerce_email_customer_details',
			array(
				$this,
				'add_data_to_email',
			),
			30,
			4
		);

		add_filter(
			'woocommerce_email_attachments',
			array(
				$this,
				'add_attachment_to_email',
			),
			10,
			3
		);

		add_action('phpmailer_init', array($this, 'phpmailer_init'));
	}

	/**
	 * @param PHPMailer $phpmailer
	 *
	 * @return void
	 */
	public function phpmailer_init($phpmailer) {
		foreach ($phpmailer->getAttachments() as $attachment) {
			if ($attachment[2] === 'qr-code.png') {
				$phpmailer->addEmbeddedImage($attachment[0], 'qr-code.png', 'qr-code.png', 'base64', 'image/png', 'inline');
			}
		}
	}

	/**
	 * @param WC_Order      $order
	 * @param $sent_to_admin
	 * @param $plain_text
	 * @param $email
	 *
	 * @return void
	 */
	public function add_data_to_email( $order, $sent_to_admin, $plain_text, $email ) {
		$invoice_id = $order->get_meta( '_invoice_number' );
		if ( ! $invoice_id ) {
			// we don't have fiscalisation data, so we skip this part
			return;
		}

		$jir = get_post_meta( $invoice_id, $this->slug . '_jir', true );
		if ($jir) {
			//return;
		}
		if ( $plain_text ) {
			echo 'JIR: ' . $jir . "\n";
			echo 'ZKI: ' . get_post_meta( $invoice_id, $this->slug . '_zki', true ) . "\n";
			echo 'URL za provjeru: ' . get_post_meta( $invoice_id, $this->slug . '_qr_code_link', true );
		} else {
			$url = get_post_meta( $invoice_id, $this->slug . '_qr_code_link', true );
			?>
			<p>
				Račun broj: <?php printf( '%s/%s/%s', get_post_meta( $invoice_id, '_invoice_number', true ), get_option( $this->slug . '_business_area' ), get_option( $this->slug . '_device_number' ) ); ?>
				<br>
				JIR: <?php echo get_post_meta( $invoice_id, $this->slug . '_jir', true ); ?>
				<br>
				ZKI: <?php echo get_post_meta( $invoice_id, $this->slug . '_zki', true ); ?>
				<br>
				<a href="<?php echo $url; ?>" target="_blank">Provjerite svoj račun ovdje.</a>
				<br>
				<img
					src="cid:qr-code.png">
			</p>
			<?php
		}
	}

	function add_attachment_to_email($attachments , $email_id, $order) {
		if ($email_id === 'customer_invoice') {
			global $wp_filesystem;
			require_once ( ABSPATH . '/wp-admin/includes/file.php' );
			WP_Filesystem();
			$invoice_id = $order->get_meta( '_invoice_number' );
			$url = get_post_meta( $invoice_id, $this->slug . '_qr_code_link', true );
			$jir = get_post_meta( $invoice_id, $this->slug . '_jir', true );
			if (!$url) {
				return $attachments;
			}
			$qr = new QrCode( $url, 200, 200 );
			$png = $qr->getPngData();
			if ( !$wp_filesystem->exists( WP_CONTENT_DIR . '/uploads/neznam_racuni/' ) ) {
				$wp_filesystem->mkdir( WP_CONTENT_DIR . '/uploads/neznam_racuni/' );
			}
			if (!$wp_filesystem->exists( WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.png' )) {
				$wp_filesystem->put_contents( WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.png', $png );
			}

			$attachments['qr-code.png'] = WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.png';
		}
		return $attachments;
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
		Invoice::instance()->processOrder( $order, $id );

		Invoice::instance()->processFiscal( $id, $order );
		return $id;
	}
}
