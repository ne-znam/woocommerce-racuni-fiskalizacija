<?php

namespace NeZnam\FiscalInvoices;

use Nticaric\Fiskalizacija\Bill\Bill;
use Nticaric\Fiskalizacija\Bill\BillNumber;
use Nticaric\Fiskalizacija\Bill\BillRequest;
use Nticaric\Fiskalizacija\Bill\TaxRate;
use Nticaric\Fiskalizacija\Fiskalizacija;
use WC_Order;
use WC_Tax;
use Exception;
use DOMDocument;

class Invoice extends Instance {

	public function __construct() {}

	/**
	 * @var WC_Order $order
	 * @var int $invoice_id
	 */
	public function processOrder( $order, $invoice_id ) {
		// save all items, quantities, unit prices, taxes, totals
		// save all totals, foreach tax, and total
		// special notes for invoice
		// billing address
		$content = array(
			'line_items'      => array(),
			'note'            => $order->get_customer_note(),
			'billing_address' => array(
				'first_line'  => $order->get_billing_address_1(),
				'second_line' => $order->get_billing_address_2(),
				'postcode'    => $order->get_billing_postcode(),
				'city'        => $order->get_billing_city(),
				'state'       => $order->get_billing_state(),
				'country'     => $order->get_billing_country(),
				'company'     => $order->get_billing_company(),
			),
			'date'            => date( 'U' ),
		);

		$tax_rates = array();
		$_tax      = new WC_Tax();
		/** @var \WC_Order_Item_Product $item */
		foreach ( $order->get_items() as $item ) {
			$line_item = array(
				'name'        => $item->get_name(),
				'unit_price'  => $item->get_product()->get_price(),
				'quantity'    => $item->get_quantity(),
				'total'       => $item->get_total(),
				'total_tax'   => $item->get_total_tax(),
				'tax_percent' => 0,
			);
			if ( $item->get_tax_status() === 'taxable' ) {
				$taxes = $_tax->get_rates( $item->get_tax_class() );
				$tax   = array_shift( $taxes );
				if ( $tax ) {
					if ( ! isset( $tax_rates[ $tax['rate'] ] ) ) {
						$tax_rates[ $tax['rate'] ] = array(
							'base'    => 0,
							'tax'     => 0,
							'percent' => $tax['rate'],
						);
					}
					$tax_rates[ $tax['rate'] ]['base'] += $item->get_total();
					$tax_rates[ $tax['rate'] ]['tax']  += $item->get_total_tax();
					$line_item['tax_percent']           = $tax['rate'];
				}
			}
			if ( $item->get_tax_status() === 'none' ) {
				if ( ! isset( $tax_rates[0] ) || ! $tax_rates[0] ) {
					$tax_rates[0] = array(
						'base'    => 0,
						'tax'     => 0,
						'percent' => 0,
					);
				}
				$tax_rates[0]['base'] += $item->get_total();
				$tax_rates[0]['tax']  += $item->get_total_tax();
			}
			$content['line_items'][] = $line_item;

		}

		// add shipping
		if ( $order->set_shipping_total( 'edit' ) > 0 ) {
			$rates                               = $_tax->get_shipping_tax_rates();
			$rate                                = array_shift( $rates );
			$tax_rates[ $rate['rate'] ]['base'] += (float) $order->get_shipping_total( 'edit' );
			$tax_rates[ $rate['rate'] ]['tax']  += (float) $order->get_shipping_tax( 'edit' );
		}
		$content['tax_rates'] = $tax_rates;
		$content['total']     = $order->get_total();
		// payment method
		$payment_method            = $order->get_payment_method();
		$content['payment_method'] = get_option( $this->slug . '_' . $payment_method );
		wp_update_post(
			array(
				'ID'           => $invoice_id,
				'post_content' => maybe_serialize( $content ),
			)
		);
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function processFiscal( $post_id, $order = null ) {
		if ( get_post_meta( $post_id, $this->slug . '_jir', true ) ) {
			return;
		}
		// process the fiscalization
		$invoice = get_post( $post_id );
		$content = maybe_unserialize( $invoice->post_content );
		if ( $content['payment_method'] === 'N' ) {
			// user doesn't want to fiscal
			return;
		}
		$certPath     = get_option( $this->slug . '_cert_path' );
		$certPass     = get_option( $this->slug . '_cert_password' );
		$sandbox      = (bool) get_option( $this->slug . '_sandbox', false );
		$area         = get_option( $this->slug . '_business_area' );
		$device       = get_option( $this->slug . '_device_number' );
		$company_oib  = get_option( $this->slug . '_company_oib' );
		$operator_oib = get_option( $this->slug . '_operator_oib' );
		try {
			$fis        = new Fiskalizacija( $certPath, $certPass, 'TLS', $sandbox );
			$billNumber = new BillNumber( get_post_meta( $post_id, '_invoice_number', true ), $area, $device );

			$listPnp          = apply_filters( $this->slug . '_porez_na_potrosnju', array() );
			$listPdv          = array();
			$listOtherTaxRate = apply_filters( $this->slug . '_drugi_porezi', array() );

			$total = $content['total'];
			foreach ( $content['tax_rates'] as $rate ) {
				$listPdv[] = new TaxRate( $rate['percent'], $rate['base'], $rate['tax'], null );
			}

			$bill = new Bill();
			$bill->setOib( $company_oib );
			$bill->setHavePDV( true );
			$bill->setNoteOfOrder( 'N' );
			$bill->setDateTime( date( 'd.m.Y\TH:i:s', $content['date'] ) );
			$bill->setBillNumber( $billNumber );
			$bill->setListPDV( $listPdv );
			$bill->setListPNP( $listPnp );
			$bill->setListOtherTaxRate( $listOtherTaxRate );
			$bill->setTotalValue( $total );
			$bill->setTypeOfPlacanje( $content['payment_method'] );
			$bill->setOibOperative( $operator_oib );

			$bill->setSecurityCode(
				$bill->securityCode(
					$fis->getPrivateKey(),
					$bill->oib,
					$bill->dateTime,
					$billNumber->numberNoteBill,
					$billNumber->noteOfBusinessArea,
					$billNumber->noteOfExcangeDevice,
					$bill->totalValue
				)
			);

			$bill->setNoteOfRedelivary( false );
			$billRequest = new BillRequest( $bill );
			$soapMessage = $fis->signXML( $billRequest->toXML() );
			$res         = $fis->sendSoap( $soapMessage );
			$DOMResponse = new DOMDocument();
			$DOMResponse->loadXML( $res );
			$jir = $DOMResponse->getElementsByTagName( 'Jir' )->item( 0 )->textContent;
			update_post_meta( $post_id, $this->slug . '_jir', $jir );
			update_post_meta( $post_id, $this->slug . '_zki', $bill->securityCode );
			$created_at = date( 'Ymd_Hi', $content['date'] );
			$total      = number_format( $total, 2, '', '' );
			update_post_meta( $post_id, $this->slug . '_qr_code_link', "https://porezna.gov.hr/rn?jir=$jir&datv=$created_at&izn=$total", true );
			if ( $order ) {
				$order->add_meta_data( '_invoice_number', $post_id );
				$order->add_order_note( 'Fiskalizacija uspješno obavljena - ' . $billNumber->numberNoteBill . '/' . $billNumber->noteOfBusinessArea . '/' . $billNumber->noteOfExcangeDevice );
				$order->save();
			}
		} catch ( Exception $e ) {
			if ( $order ) {
				$order->add_order_note( __( 'Fiskalizacija nije uspjela s greškom: ' . $e->getMessage(), $this->slug ) );
			}
		}
	}

	public function createStorno( $post_id ) {
		$post    = get_post( $post_id );
		$content = maybe_unserialize( $post->post_content );
		foreach ( $content['tax_rates'] as &$tax_rate ) {
			$tax_rate['base'] = -$tax_rate['base'];
			$tax_rate['tax']  = -$tax_rate['tax'];
		}
		$content['total'] = -$content['total'];
		$area             = get_option( $this->slug . '_business_area' );
		$device           = get_option( $this->slug . '_device_number' );
		$invoice_format   = get_option( $this->slug . '_invoice_format', '%s/%s/%s' );
		$sandbox = !(get_option( $this->slug . '_sandbox', 'no' ) === 'no');
		// get sequential number for invoice
		$invoice_number = self::instance()->generateInvoiceNumber( date( 'Y' ), $sandbox );
		// create new invoice, store data for invoice
		$id = wp_insert_post(
			array(
				'post_type'    => 'neznam_invoice',
				'post_title'   => apply_filters( $this->slug . '_invoice_format', sprintf( $invoice_format, $invoice_number, $area, $device ), $invoice_number, $area, $device ),
				'post_status'  => 'publish',
				'post_content' => maybe_serialize( $content ),
			)
		);
		add_post_meta( $id, '_invoice_number', $invoice_number );
		add_post_meta( $id, '_storno', $post_id );
		$this->processFiscal( $id );
		return $id;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return int
	 */
	public function generateInvoiceNumber( $year, $sandbox = false ) {
		// get last number in year of order
		$q = new \WP_Query(
			array(
				'post_status'         => 'any',
				'posts_per_page'      => 1,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'post_type'           => 'neznam_invoice',
				'year'                => $year,
				'orderby'             => 'meta_value_num',
				'meta_key'            => '_invoice_number',
				'order'               => 'desc',
				'meta_query' => [
					[
						'key' => '_sandbox',
						'compare' => $sandbox ? 'EXISTS' : 'NOT EXISTS'
					]
				]
			)
		);
		if ( $q->have_posts() ) {
			$q->the_post();
			$receipt_number = (int) get_post_meta( $q->post->ID, '_invoice_number', true );
			++$receipt_number;
		} else {
			$receipt_number = 1;
		}

		return $receipt_number;
	}
}
