<?php

namespace NeZnam\FiscalInvoices;

use Com\Tecnick\Barcode\Type\Square\QrCode;
use Dompdf\Dompdf;
use PHPMailer\PHPMailer\PHPMailer;

class Emails extends Instance {

	public function __construct() {
		//add_action('template_redirect', [$this, 'create_pdf']);
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
		add_action( 'wp_mail_succeeded', array($this, 'after_sent'));
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
				$phpmailer->addAttachment($attachment[0], $attachment[2], $attachment[3], $attachment[4], $attachment[6]);
			}
		}
	}

	/**
	 * @param $mail_data
	 *
	 * @return void
	 */
	public function after_sent($mail_data) {
		$attachments = $mail_data['attachments'];
		foreach ($attachments as $attachment) {
			if ($attachment[2] === 'qr-code.png' || $attachment[2] === 'racun.pdf') {
				unlink($attachment[0]);
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
			$invoice_id = $order->get_meta( '_invoice_number' );
			$url = get_post_meta( $invoice_id, $this->slug . '_qr_code_link', true );
			$jir = get_post_meta( $invoice_id, $this->slug . '_jir', true );
			if (!$url) {
				return $attachments;
			}
			$attachments['qr-code.png'] = $this->create_png($url, $jir);
			$attachments['racun.pdf'] = $this->create_pdf($order, $jir);
		}
		return $attachments;
	}

	private function create_png($url, $jir) {
		global $wp_filesystem;
		require_once ( ABSPATH . '/wp-admin/includes/file.php' );
		WP_Filesystem();
		$qr = new QrCode( $url, 200, 200 );
		$png = $qr->getPngData();
		if ( !$wp_filesystem->exists( WP_CONTENT_DIR . '/uploads/neznam_racuni/' ) ) {
			$wp_filesystem->mkdir( WP_CONTENT_DIR . '/uploads/neznam_racuni/' );
		}
		if (!$wp_filesystem->exists( WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.png' )) {
			$wp_filesystem->put_contents( WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.png', $png );
		}

		return WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.png';
	}

	public function create_pdf($order = null, $jir = null) {
		global $wp_filesystem;
		require_once ( ABSPATH . '/wp-admin/includes/file.php' );
		WP_Filesystem();
		if ($wp_filesystem->exists( WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.pdf' )) {
			//return WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.pdf';
		}
		$invoice_id = $order->get_meta( '_invoice_number' );
		$invoice = get_post($invoice_id);
		$url = get_post_meta( $invoice_id, $this->slug . '_qr_code_link', true );
		$content = maybe_unserialize($invoice->post_content);
		$qr = new QrCode( $url, 200, 200 );
		$png = $qr->getPngData();
		$template = locate_template('woocommerce/neznam/invoice.php');
		if (!$template) {
			$template = plugin_dir_path(__FILE__) . '/../../templates/invoice.php';
		}
		ob_start();
		load_template($template, true, [
			'invoice' => $invoice,
			'content' => $content,
			'order' => $order,
			'png' => $png,
		]);
		$html = ob_get_clean();
		$dompdf = new Dompdf();
		$dompdf->loadHtml($html);

		$dompdf->setPaper('A4');
		$dompdf->render();
		$data = $dompdf->output();
		if ( !$wp_filesystem->exists( WP_CONTENT_DIR . '/uploads/neznam_racuni/' ) ) {
			$wp_filesystem->mkdir( WP_CONTENT_DIR . '/uploads/neznam_racuni/' );
		}
		//if (!$wp_filesystem->exists( WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.pdf' )) {
			$wp_filesystem->put_contents( WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.pdf', $data );
		//}
		return WP_CONTENT_DIR . '/uploads/neznam_racuni/'. $jir .'.pdf';
	}
}
