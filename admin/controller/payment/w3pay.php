<?php
/**
 * W3PAY - Web3 Crypto Payments
 * Website: https://w3pay.dev
 * GitHub Website: https://w3pay.github.io/
 * GitHub: https://github.com/w3pay
 * GitHub plugin: https://github.com/w3pay-open-cart
 * Copyright (c)
 */

namespace Opencart\Admin\Controller\Extension\W3pay\Payment;

class W3pay extends \Opencart\System\Engine\Controller
{
    public function index(): void
    {

        $__DIR = str_replace("\\", "/", __DIR__);
        $PluginPath = strstr($__DIR, '/admin/controller/', true);

        $curUrl = str_replace($_SERVER['DOCUMENT_ROOT'], "", $__DIR);
        $plugin_url = strstr($curUrl, '/admin/controller/', true);

        $w3payFrontend = $plugin_url . '/w3pay/w3payFrontend';
        $w3payBackend = $PluginPath . '/w3pay/w3payBackend';

        // Set the right paths
        if (!defined('_W3PAY_w3payFrontend_')) {
            define('_W3PAY_w3payFrontend_', $w3payFrontend);
        }
        if (!defined('_W3PAY_w3payBackend_')) {
            define('_W3PAY_w3payBackend_', $w3payBackend);
        }

        include_once(_W3PAY_w3payBackend_ . '/widget/wW3pay.php');

        if (!empty($_GET['pagesettings']) && $_GET['pagesettings'] == 'load') {
            \wW3pay::instance()->showLoad(['checkAuthRequired' => true]);
            exit;
        }

        $this->load->language('extension/w3pay/payment/w3pay');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['settings_1Url'] = $this->url->link('extension/w3pay/payment/w3pay', 'user_token=' . $this->session->data['user_token'], true);
        $data['settings_2Url'] = $this->url->link('extension/w3pay/payment/w3pay', 'user_token=' . $this->session->data['user_token'] . '&settings=2', true);
        $data['settings_3Url'] = $this->url->link('extension/w3pay/payment/w3pay', 'user_token=' . $this->session->data['user_token'] . '&settings=3', true);

        $rLink = (strpos($_SERVER['REQUEST_URI'], '?') !== false) ? '&' : '?';
        $loadLink = $_SERVER['REQUEST_URI'] . $rLink . 'pagesettings=load';

        $data['settingsPage'] = 0;
        $content = '';
        if (!empty($_GET['settings'])) {
            $data['settingsPage'] = $_GET['settings'];
            if ($_GET['settings'] == 3) {
                $checkPaymentPageUrl = $this->url->link('extension/w3pay/payment/w3pay|checkpayment', 'lang=' . mb_substr($this->config->get('config_language'), 0, 2), true);

                $Transactions = \wW3pay::instance()->showTransactions([
                    'checkAuthRequired' => false,
                    'sendurl' => $loadLink,
                    'checkPaymentPageUrl' => $checkPaymentPageUrl,
                ]);
                if (!empty($Transactions['head'])) {
                    $content .= $Transactions['head'];
                } // Show js, css files
                if (!empty($Transactions['html'])) {
                    $content .= $Transactions['html'];
                } // Show html content
            }
        } else {
            $FormSettings = \wW3pay::instance()->showFormSettings(['checkAuthRequired' => false, 'cms' => 'oc', 'sendurl' => $loadLink]);
            if (!empty($FormSettings['head'])) {
                $content .= $FormSettings['head'];
            } // Show js, css files
            if (!empty($FormSettings['html'])) {
                $content .= $FormSettings['html'];
            } // Show html content
        }

        $data['FormSettings'] = $content;

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/w3pay/payment/w3pay', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link('extension/w3pay/payment/w3pay|save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        $data['payment_w3pay_pending_status_id'] = $this->config->get('payment_w3pay_pending_status_id');
        $data['payment_w3pay_failed_status_id'] = $this->config->get('payment_w3pay_failed_status_id');
        $data['payment_w3pay_order_status_id'] = $this->config->get('payment_w3pay_order_status_id');

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['payment_w3pay_geo_zone_id'] = $this->config->get('payment_w3pay_geo_zone_id');

        $this->load->model('localisation/geo_zone');

        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['payment_w3pay_status'] = $this->config->get('payment_w3pay_status');
        $data['payment_w3pay_sort_order'] = $this->config->get('payment_w3pay_sort_order');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/w3pay/payment/w3pay', $data));
    }

    public function save(): void
    {
        $this->load->language('extension/w3pay/payment/w3pay');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/w3pay/payment/w3pay')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('payment_w3pay', $this->request->post);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
