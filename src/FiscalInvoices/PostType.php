<?php

namespace NeZnam\FiscalInvoices;

use Com\Tecnick\Barcode\Type\Square\QrCode;

class PostType extends Instance {
	public function __construct() {
		add_action( 'init', [
			$this,
			'register'
		] );

	}

	public function register() {
		register_post_type( 'neznam_invoice', [
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
				'edit_post'          => 'manage_options',
				'read_post'          => true,
				'delete_post'        => false,
				'edit_posts'         => false,
				'edit_others_posts'  => false,
				'publish_posts'      => false,
				'read_private_posts' => false,
			),
			'supports'             => [ 'title' ],
			'register_meta_box_cb' => [
				$this,
				'meta_boxes'
			]
		] );
	}

	public function meta_boxes() {
		add_meta_box( $this->slug . '_invoice_data', 'Fiskalni podaci računa', [
			$this,
			'fiscal_data'
		] );
		remove_meta_box( 'submitdiv', 'neznam_invoice', 'side' );
		remove_meta_box( 'slugdiv', 'neznam_invoice', 'normal' );
	}

	public function fiscal_data( $post ) {
		$url = get_post_meta( $post->ID, $this->slug . '_qr_code_link', true );
		$qr  = new QrCode( $url, 200, 200 );
		?>
		<p>
			Invoice: <?php echo get_post_meta( $post->ID, '_invoice_number', true ) ?>
			<br>
			JIR: <?php echo get_post_meta( $post->ID, $this->slug . '_jir', true ) ?>
			<br>
			ZKI: <?php echo get_post_meta( $post->ID, $this->slug . '_zki', true ) ?>
			<br>
			<img
				src="data:image/png;base64,<?php echo base64_encode( $qr->getPngData() ) ?>">
		</p>
		<?php
	}
}
