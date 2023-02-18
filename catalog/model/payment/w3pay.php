<?php
/**
 * W3PAY - Web3 Crypto Payments
 * Website: https://w3pay.dev
 * GitHub Website: https://w3pay.github.io/
 * GitHub: https://github.com/w3pay
 * GitHub plugin: https://github.com/w3pay-open-cart
 * Copyright (c)
 */

namespace Opencart\Catalog\Model\Extension\W3pay\Payment;

class W3pay extends \Opencart\System\Engine\Model {
	public function getMethod(array $address): array {
		$this->load->language('extension/w3pay/payment/w3pay');

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_w3pay_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");

		if (!$this->config->get('payment_w3pay_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = [];

		if ($status) {
			$method_data = [
				'code'       => 'w3pay',
				'title'      => $this->language->get('heading_title'),
				'sort_order' => $this->config->get('payment_w3pay_sort_order')
			];
		}

		return $method_data;
	}
}
