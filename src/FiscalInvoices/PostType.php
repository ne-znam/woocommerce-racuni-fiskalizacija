<?php

namespace NeZnam\FiscalInvoices;

use Automattic\Jetpack\Constants;
use Com\Tecnick\Barcode\Type\Square\QrCode;

class PostType extends Instance {

	private static $saved_meta_boxes;

	public function __construct() {
		add_action(
			'init',
			array(
				$this,
				'register',
			)
		);
		add_action( 'save_post', array( $this, 'save_invoice_actions' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
	}

	public function row_actions( $actions, $post ) {
		if ( $post->post_type === 'neznam_invoice' ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	public function register() {
		register_post_type(
			'neznam_invoice',
			array(
				'label'                => __( 'Računi', $this->slug ),
				'description'          => __( 'Fiskalni računi', $this->slug ),
				'public'               => false,
				'hieararchical'        => false,
				'show_ui'              => true,
				'show_in_menu'         => 'woocommerce',
				'show_in_rest'         => false,
				'menu_position'        => 40,
				'rewrite'              => false,
				'has_archive'          => false,
				'can_export'           => false,
				'capabilities'         => array(
					'create_posts'       => false,
					'edit_post'          => 'manage_woocommerce',
					'read_post'          => true,
					'delete_post'        => false,
					'edit_posts'         => 'manage_woocommerce',
					'edit_others_posts'  => 'manage_woocommerce',
					'publish_posts'      => false,
					'read_private_posts' => false,
				),
				'supports'             => array( 'title' ),
				'register_meta_box_cb' => array(
					$this,
					'meta_boxes',
				),
			)
		);
	}

	public function meta_boxes() {
		add_meta_box(
			$this->slug . '_invoice_data',
			'Fiskalni podaci računa',
			array(
				$this,
				'fiscal_data',
			),
			'neznam_invoice',
			'normal'
		);
		add_meta_box(
			$this->slug . '_invoice_actions',
			'Akcije',
			array(
				$this,
				'invoice_actions',
			),
			'neznam_invoice',
			'side'
		);

		remove_meta_box( 'submitdiv', 'neznam_invoice', 'side' );
		remove_meta_box( 'slugdiv', 'neznam_invoice', 'normal' );
	}

	public function fiscal_data( $post ) {
		$url = get_post_meta( $post->ID, $this->slug . '_qr_code_link', true );
		$order_id = get_post_meta( $post->ID, '_order_id', true );
		$content = maybe_unserialize($post->post_content);
		?>
		<p>
			Invoice: <?php echo get_post_meta( $post->ID, '_invoice_number', true ); ?><br>
			Order: <a href="<?php echo get_edit_post_link( $order_id, true ); ?>"><?php echo $order_id; ?></a></p>
		<p>
			Način plaćanja: <?php echo $content['payment_method']; ?>
		</p>
		<?php
		if ( strlen( $url ) ) {
			$qr       = new QrCode( $url, 200, 200 );
			?>
			<p>
			<br>
			JIR: <?php echo get_post_meta( $post->ID, $this->slug . '_jir', true ); ?>
			<br>
			ZKI: <?php echo get_post_meta( $post->ID, $this->slug . '_zki', true ); ?>
			<br>
			<img
				src="data:image/png;base64,<?php echo base64_encode( $qr->getPngData() ); ?>">
			</p>
			<?php
		} else {
			$order_id = get_post_meta( $post->ID, '_order_id', true );
			?>
			<p>Račun nije fiskaliziran</p>
			<p>Provjerite poruke na narudžbi: <a href="<?php echo get_edit_post_link( $order_id, true ); ?>"><?php echo $order_id; ?></a></p>
			<?php
		}
	}

	public function invoice_actions( $post ) {
		wp_nonce_field( $this->slug . '_save_data', $this->slug . '_meta_nonce' );
		if ( ! get_post_meta( $post->ID, 'storno', true ) ) {
			wp_nonce_field( $this->slug . '_save_data', $this->slug . '_meta_nonce' );
			?>
			<button type="submit" class="button save_order button-primary" name="save" value="storno">Storno</button>
			<?php
		}
		if ( ! get_post_meta( $post->ID, '_jir', true ) ) {
			?>
			<button type="submit" class="button save_order button-primary" name="save" value="fiscal">Fiskaliziraj</button>
			<?php
		}
	}

	public function save_invoice_actions( $post_id, $post ) {
		$post_id = absint( $post_id );

		// $post_id and $post are required
		if ( empty( $post_id ) || empty( $post ) || self::$saved_meta_boxes ) {
			return;
		}

		// Dont' save meta boxes for revisions or autosaves.
		if ( Constants::is_true( 'DOING_AUTOSAVE' ) || is_int( wp_is_post_revision( $post ) ) || is_int( wp_is_post_autosave( $post ) ) ) {
			return;
		}

		// Check the nonce.
		if ( empty( $_POST[ $this->slug . '_meta_nonce' ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ $this->slug . '_meta_nonce' ] ), $this->slug . '_save_data' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

		// Check the post being saved == the $post_id to prevent triggering this call for other save_post events.
		if ( empty( $_POST['post_ID'] ) || absint( $_POST['post_ID'] ) !== $post_id ) {
			return;
		}

		// Check user has permission to edit.
		if ( ! current_user_can( 'manage_woocommerce', $post_id ) ) {
			return;
		}

		self::$saved_meta_boxes = true;

		// switch action and perform operation
		switch ( $_POST['save'] ) {
			case 'storno':
				$id = Invoice::instance()->createStorno( $post_id );
				wp_redirect( get_edit_post_link( $id, true ) );
				exit;
			case 'fiscal':
				$order = wc_get_order( get_post_meta( $post_id, '_order_id', true ) );
				Invoice::instance()->processFiscal( $post_id, $order );
				wp_redirect( get_edit_post_link( $post_id, true ) );
				exit;
		}
	}
}
