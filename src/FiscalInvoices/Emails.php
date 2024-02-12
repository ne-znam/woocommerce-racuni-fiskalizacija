<?php

namespace NeZnam\FiscalInvoices;

use Com\Tecnick\Barcode\Type\Square\QrCode;
use PHPMailer\PHPMailer\PHPMailer;

class Emails extends Instance {

	public function __construct() {
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
		$attachments = $phpmailer->getAttachments();
		$phpmailer->clearAttachments();
		foreach ($attachments as $attachment) {
			if ($attachment[2] === 'qr-code.png') {
				$phpmailer->addEmbeddedImage($attachment[0], 'qr-code.png', 'qr-code.png', 'base64', 'image/png', 'inline');
			} else {
				$phpmailer->addAttachment($attachment[0], $attachment[1], $attachment[2], $attachment[3]);
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
		if (!$jir) {
			return;
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

	public function create_pdf($invoice) {
		$invoice_id = $invoice->get_meta( '_invoice_number' );
		if ( ! $invoice_id ) {
			// we don't have fiscalisation data, so we skip this part
			return;
		}
		$jir = get_post_meta( $invoice_id, $this->slug . '_jir', true );
		if (!$jir) {
			return;
		}
		$url = get_post_meta( $invoice_id, $this->slug . '_qr_code_link', true );
		$qr = new QrCode( $url, 200, 200 );
		$png = $qr->getPngData();
		$mpdf = new \Mpdf\Mpdf();
		$mpdf->WriteHTML('<p>Račun broj: ' . get_post_meta( $invoice_id, '_invoice_number', true ) . '</p>');
		$mpdf->WriteHTML('<p>JIR: ' . get_post_meta( $invoice_id, $this->slug . '_jir', true ) . '</p>');
		$mpdf->WriteHTML('<p>ZKI: ' . get_post_meta( $invoice_id, $this->slug . '_zki', true ) . '</p>');
		$mpdf->WriteHTML('<p><a href="' . $url . '" target="_blank">Provjerite svoj račun ovdje.</a></p>');
		$mpdf->WriteHTML('<img src="data:image/png;base64,' . base64_encode($png) . '">');
		$mpdf->Output(WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.pdf', 'F');
	}
}
