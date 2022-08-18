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

	public function __construct() {

	}

	/**
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function processFiscal( $post_id ) {
		if ( get_post_meta( $post_id, $this->slug . '_jir', true ) ) {
			return;
		}
		$order = wc_get_order(get_post_meta($post_id, '_order_id', true));
		$certPath     = get_option( $this->slug . '_cert_path' );
		$certPass     = get_option( $this->slug . '_cert_password' );
		$sandbox      = (bool) get_option( $this->slug . '_sandbox', false );
		$area         = get_option( $this->slug . '_business_area' );
		$device       = get_option( $this->slug . '_device_number' );
		$company_oib  = get_option( $this->slug . '_company_oib' );
		$operator_oib = get_option( $this->slug . '_operator_oib' );
		try {
			$fis        = new Fiskalizacija( $certPath, $certPass, 'TLS', $sandbox );
			$billNumber = new BillNumber( get_post_meta($post_id, '_invoice_number', true), $area, $device );

			$listPnp          = apply_filters( $this->slug . '_porez_na_potrosnju', array() );
			$listPdv          = array();
			$listOtherTaxRate = apply_filters( $this->slug . '_drugi_porezi', array() );
			$total            = $order->get_total( 'edit' );
			$tax_rates        = [];
			$_tax             = new WC_Tax();
			foreach ( $order->get_items() as $item ) {
				/** @var \WC_Order_Item_Product $item */
				if ( $item->get_tax_status() === 'taxable' ) {

					$taxes = $_tax->get_rates( $item->get_tax_class() );
					$tax   = array_shift( $taxes );
					if ( ! isset( $tax_rates[ $tax['rate'] ] ) ) {
						$tax_rates[ $tax['rate'] ] = [
							'base'    => 0,
							'tax'     => 0,
							'percent' => $tax['rate'],
						];
					}
					$tax_rates[ $tax['rate'] ]['base'] += $item['total'];
					$tax_rates[ $tax['rate'] ]['tax']  += $item['total_tax'];
				}
			}
			if ( $order->get_shipping_tax() ) {
				$rates                              = $_tax->get_shipping_tax_rates();
				$rate                               = array_shift( $rates );
				$tax_rates[ $rate['rate'] ]['base'] += (float) $order->get_shipping_total( 'edit' );
				$tax_rates[ $rate['rate'] ]['tax']  += (float) $order->get_shipping_tax( 'edit' );
			}
			foreach ( $tax_rates as $rate ) {
				$listPdv[] = new TaxRate( $rate['percent'], $rate['base'], $rate['tax'], null );
			}

			$bill = new Bill();
			$bill->setOib( $company_oib );
			$bill->setHavePDV( true );
			$bill->setNoteOfOrder( "N" );
			$bill->setDateTime( $order->get_date_paid()->format( 'd.m.Y\TH:i:s' ) );
			$bill->setBillNumber( $billNumber );
			$bill->setListPDV( $listPdv );
			$bill->setListPNP( $listPnp );
			$bill->setListOtherTaxRate( $listOtherTaxRate );
			$bill->setTotalValue( $total );
			$bill->setTypeOfPlacanje( 'K' );
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
			update_post_meta( $post_id,  $this->slug . '_jir', $jir );
			update_post_meta( $post_id,  $this->slug . '_zki', $bill->securityCode );
			$created_at = $order->get_date_paid()->format( 'Ymd_Hi' );
			$total      = number_format( $total, 2, '', '' );
			update_post_meta( $post_id, $this->slug . '_qr_code_link', "https://porezna.gov.hr/rn?jir=$jir&datv=$created_at&izn=$total", true );
			$order->add_order_note( 'Fiskalizacija uspješno obavljena - ' . $billNumber->numberNoteBill . '/' .$billNumber->noteOfBusinessArea . '/' .$billNumber->noteOfExcangeDevice );
		} catch ( Exception $e ) {
			$order->add_order_note( __( 'Fiskalizacija nije uspjela s greškom: ' . $e->getMessage(), $this->slug ) );
		}
	}
}
