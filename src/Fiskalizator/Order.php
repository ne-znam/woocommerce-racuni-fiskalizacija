<?php

namespace NeZnam\Fiskalizator;

use Nticaric\Fiskalizacija\Bill\Bill;
use Nticaric\Fiskalizacija\Bill\BillNumber;
use Nticaric\Fiskalizacija\Bill\BillRequest;
use Nticaric\Fiskalizacija\Bill\TaxRate;
use Nticaric\Fiskalizacija\Fiskalizacija;

class Order extends Instance {

	public function __construct() {
		add_action('woocommerce_order_status_changed', [$this, 'process_completed_order'], 10, 4);
	}

	/**
	 * @param $order_id
	 * @param $from
	 * @param $to
	 * @param $order
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function process_completed_order($order_id, $from, $to, $order) {
		if ($to === 'completed') {
			$this->generateReceiptNumber( $order );
			$this->processOrder( $order );
		}
	}

	/**
	 * @param \WC_Order $order
	 *
	 * @return int
	 */
	private function generateReceiptNumber(\WC_Order $order) {
		//check if already has new receipt number
		if ($order->get_meta($this->slug . '_receipt_number')) {
			return (int)$order->get_meta('receipt_number');
		}
		//get last number in year of order
		$paid_at = $order->get_date_paid();
		$q = new \WP_Query([
			'post_status' => 'any',
			'posts_per_page' => 1,
			'no_found_rows' => true,
			'ignore_sticky_posts' => true,
			'post_type' => 'shop_order',
			'year' => $paid_at->date('Y'),
			'orderby' => 'meta_value_num',
			'meta_key' => $this->slug . '_receipt_number',
			'order' => 'desc'
		]);
		if ($q->have_posts()) {
			$q->the_post();
			$receipt_number = (int)get_post_meta($q->post->ID, $this->slug . '_receipt_number', true);
			$receipt_number++;
		} else {
			$receipt_number = 1;
		}
		//store new number
		$order->add_meta_data($this->slug . '_receipt_number', $receipt_number, true);
		$order->save_meta_data();
		return $receipt_number;
	}

	/**
	 * @param \WC_Order $order
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function processOrder(\WC_Order $order) {
		if ($order->get_meta($this->slug . '_jir')) {
			return;
		}
		$certPath = get_option($this->slug . '_cert_path');
		$certPass = get_option($this->slug . '_cert_password');
		$sandbox = (bool)get_option($this->slug . '_sandbox', false);
		$area = get_option($this->slug . '_business_area');
		$device = get_option($this->slug . '_device_number');
		$company_oib = get_option($this->slug . '_company_oib');
		$operator_oib = get_option($this->slug . '_operator_oib');
		$fis = new Fiskalizacija($certPath, $certPass, 'TLS', true);
		return;
		$billNumber = new BillNumber($order->get_meta($this->slug . '_receipt_number'), $area, $device);

		$listPnp = apply_filters($this->slug . '_porez_na_potrosnju', array());
		$listPdv = array();
		$listOtherTaxRate = apply_filters($this->slug . '_drugi_porezi', array());
		$total = $order->get_total('edit');
		$tax_rates = [];
		$_tax = new \WC_Tax();
		foreach ($order->get_items() as $item) {
			/** @var \WC_Order_Item_Product $item */
			if ($item->get_tax_status() === 'taxable') {

				$taxes = $_tax->get_rates($item->get_tax_class());
				$tax = array_shift($taxes);
				if (!isset($tax_rates[$tax['rate']])) {
					$tax_rates[$tax['rate']] = [
						'base' => 0,
						'tax' => 0,
						'percent' => $tax['rate'],
					];
				}
				$tax_rates[$tax['rate']]['base'] += $item['total'];
				$tax_rates[$tax['rate']]['tax'] += $item['total_tax'];
			}
		}
		if ($order->get_shipping_tax()) {
			$rates = $_tax->get_shipping_tax_rates();
			$rate  = array_shift( $rates );
			$tax_rates[$rate['rate']]['base'] += (float)$order->get_shipping_total('edit');
			$tax_rates[$rate['rate']]['tax'] += (float)$order->get_shipping_tax('edit');
		}
		foreach ($tax_rates as $rate) {
			$listPdv[] = new TaxRate($rate['percent'], $rate['base'], $rate['tax'], null);
		}

		$bill = new Bill();
		$bill->setOib($company_oib);
		$bill->setHavePDV(true);
		$bill->setNoteOfOrder("N");
		$bill->setDateTime($order->get_date_paid()->format('d.m.Y\TH:i:s'));
		$bill->setBillNumber($billNumber);
		$bill->setListPDV($listPdv);
		$bill->setListPNP($listPnp);
		$bill->setListOtherTaxRate($listOtherTaxRate);
		$bill->setTotalValue($total);
		$bill->setTypeOfPlacanje('K');
		$bill->setOibOperative($operator_oib);

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

		$bill->setNoteOfRedelivary(false);
		$billRequest = new BillRequest($bill);

		$soapMessage = $fis->signXML($billRequest->toXML());
		$res = $fis->sendSoap($soapMessage);
		$DOMResponse = new \DOMDocument();
		$DOMResponse->loadXML($res);
		$jir = $DOMResponse->getElementsByTagName('Jir')->item(0)->textContent;
		$order->add_meta_data($this->slug . '_jir', $jir, true);
		$order->add_meta_data($this->slug . '_zki', $bill->securityCode, true);
		$created_at = $order->get_date_paid()->format('Ymd_Hi');
		$total = number_format($total, 2, '','');
		$order->add_meta_data($this->slug . '_qr_code_link', "https://porezna.gov.hr/rn?jir=$jir&datv=$created_at&izn=$total", true);
		$order->save_meta_data();
	}
}
