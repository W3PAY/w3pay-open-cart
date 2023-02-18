<?php
/**
 * W3PAY - Web3 Crypto Payments
 * Website: https://w3pay.dev
 * GitHub Website: https://w3pay.github.io/
 * GitHub: https://github.com/w3pay
 * GitHub plugin: https://github.com/w3pay-open-cart
 * Copyright (c)
 */

namespace Opencart\Catalog\Controller\Extension\W3pay\Payment;

class W3pay extends \Opencart\System\Engine\Controller {

	public function index(): string {
		$this->load->language('extension/w3pay/payment/w3pay');
		$data['logged'] = $this->customer->isLogged();
        $data['img_url'] = '/extension/w3pay/w3pay/w3payFrontend/files/imgs/wallets-img.png';
		$data['confirm_url'] = $this->url->link('extension/w3pay/payment/w3pay|confirm', 'lang='.$this->getLang(), true);
		return $this->load->view('extension/w3pay/payment/w3pay', $data);
	}

    public function confirm(): void {
        $this->load->language('extension/w3pay/payment/w3pay');
        $this->response->addHeader('Content-Type: application/json');

        if (!isset($this->session->data['order_id'])) {
            $this->response->setOutput(json_encode(['error' => 1, 'data' => $this->language->get('error_order')]));
            return;
        }

        if (empty($this->session->data['payment_method']) || $this->session->data['payment_method'] != 'w3pay') {
            $this->response->setOutput(json_encode(['error' => 1, 'data' => $this->language->get('error_payment_method')]));
            return;
        }

        // TODO Opencart\Catalog\Model\Checkout in function editOrder() - currency_id, currency_code, currency_value update not working. We change the currency ourselves
        // TODO Accept only in USD coins
        $order_data = [];
        $order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
        $order_data['currency_code'] = $this->session->data['currency'];
        $order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET `currency_id` = '" . (int)$order_data['currency_id'] . "', `currency_code` = '" . $this->db->escape((string)$order_data['currency_code']) . "',  `currency_value` = '" . (float)$order_data['currency_value'] . "', `date_modified` = NOW() WHERE `order_id` = '" . (int)$this->session->data['order_id'] . "'");

        $PaymentData = $this->getPaymentData($this->session->data['order_id']);
        if(!empty($PaymentData['error'])){
            $this->response->setOutput(json_encode($PaymentData));
            return;
        }

        $this->load->model('checkout/order');
        $order_data = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        /*if(empty($order_data['order_status_id'])){
            $this->load->model('checkout/order');
            $comments = $this->language->get('Selected_Payment_Method').' '.$this->language->get('Order_ID').': '.$this->session->data['order_id'].'. '.$this->language->get('waiting_for_payment').' '.$this->language->get('Link_for_payment').': <a target="_blank" href="'.$PaymentData['PaymentData']['pay_url'].'">'.$PaymentData['PaymentData']['pay_url'].'</a>';
            $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_w3pay_pending_status_id'), $comments, true);
        }*/

        $this->response->setOutput(json_encode(['error' => 0, 'data' => 'success ', 'redirect'=>$PaymentData['PaymentData']['pay_url']]));
        return;
    }

    public function payment(): void {
        $this->load->language('extension/w3pay/payment/w3pay');
        if(empty($_GET['order'])){ echo 'order is empty'; exit; }
        $this->pagePayment($_GET['order']);
        exit;
    }

    public function checkpayment(): void {
        $this->load->language('extension/w3pay/payment/w3pay');
        $this->pageCheckPayment();
        exit;
    }

    public function getLang(){
        return mb_substr($this->config->get('config_language'), 0, 2);
    }

    public function pagePayment($order_id){
        $PaymentData = $this->getPaymentData($order_id);
        if(!empty($PaymentData['error'])){
            echo $PaymentData['data'];
            exit;
        }
        $PluginPaths = $this->getPluginPaths();

        $this->addSessionStartPay($order_id);

        // Set the right paths
        if(!defined('_W3PAY_w3payFrontend_')){ define('_W3PAY_w3payFrontend_', $PluginPaths['w3payFrontend']); }
        if(!defined('_W3PAY_w3payBackend_')){ define('_W3PAY_w3payBackend_', $PluginPaths['w3payBackend']); }

        // Include php class widget wW3pay.php
        include_once(_W3PAY_w3payBackend_. '/widget/wW3pay.php');

        // Set prices to receive tokens
        $orderId = $PaymentData['PaymentData']['orderId']; // Please enter your order number
        $payAmountInReceiveToken = $PaymentData['PaymentData']['payAmountInReceiveToken']; // Please enter a price for the order
        $OrderData = [
            'orderId' => $orderId,
            'payAmounts' => [
                ['chainId' => 97, 'payAmountInReceiveToken' => $payAmountInReceiveToken], // Binance Smart Chain Mainnet - Testnet (BEP20)
                ['chainId' => 56, 'payAmountInReceiveToken' => $payAmountInReceiveToken], // Binance Smart Chain Mainnet (BEP20)
                ['chainId' => 137, 'payAmountInReceiveToken' => $payAmountInReceiveToken], // Polygon (MATIC)
                ['chainId' => 43114, 'payAmountInReceiveToken' => $payAmountInReceiveToken], // Avalanche C-Chain
                ['chainId' => 250, 'payAmountInReceiveToken' => $payAmountInReceiveToken], // Fantom Opera
            ],
        ];
        $showPayment = \wW3pay::instance()->showPayment([
            'checkPaymentPageUrl'=>$PaymentData['PaymentData']['checkPaymentPageUrl'],
            'OrderData' => $OrderData,
        ]);
        $content = '';
        if(!empty($showPayment['head'])){ $content .= $showPayment['head']; } // Show js, css files
        if(!empty($showPayment['html'])){ $content .= $showPayment['html']; } // Show html content

        $dataHtml = [
            'title' => 'W3PAY - Web3 Crypto Payments',
            'description' => 'W3PAY - Web3 Crypto Payments',
            'content' => $content,
        ];

        $PluginPaths = $this->getPluginPaths();
        echo $this->getTemplateHtml($PluginPaths['template'], $dataHtml);
        exit;
    }

    public function pageCheckPayment(){
        $CheckPaymentData = $this->getCheckPaymentData();
        if(!empty($CheckPaymentData['error'])){
            echo $CheckPaymentData['data'];
            exit;
        }
        $PluginPaths = $this->getPluginPaths();

        // Set the right paths
        if(!defined('_W3PAY_w3payFrontend_')){ define('_W3PAY_w3payFrontend_', $PluginPaths['w3payFrontend']); }
        if(!defined('_W3PAY_w3payBackend_')){ define('_W3PAY_w3payBackend_', $PluginPaths['w3payBackend']); }

        // Include php class widget wW3pay.php
        include_once(_W3PAY_w3payBackend_. '/widget/wW3pay.php');

        $showCheckPayment = \wW3pay::instance()->showCheckPayment([
            'htmlSuccess' => '<a class="checkPaymentBtn" href="'.$this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true).'">'.$this->language->get('Continue').'</a>',
            'htmlError' => '<a class="checkPaymentBtn" href="'.$this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true).'">'.$this->language->get('Continue').'</a>',
        ]);
        if(!empty($showCheckPayment['CheckPaymentData']['showSuccess'])){
            $orderId = $showCheckPayment['CheckPaymentData']['checkSign']['checkSign']['orderId'];
            // TODO The administrator can mark $orderId the successful payment in the database.
            $this->paySave($showCheckPayment, true);
        } else {
            if(!empty($showCheckPayment['CheckPaymentData']['checkSign']['typeError']) && $showCheckPayment['CheckPaymentData']['checkSign']['typeError']=='SignaturFalse'){
                //$orderId = $showCheckPayment['CheckPaymentData']['checkSign']['checkSign']['orderId'];
                // TODO The administrator can mark $orderId the failed payment in the database.
                $this->paySave($showCheckPayment, false);
            }
        }
        $content = '';
        if(!empty($showCheckPayment['head'])){ $content .= $showCheckPayment['head']; } // Show js, css files
        if(!empty($showCheckPayment['html'])){ $content .= $showCheckPayment['html']; } // Show html content

        $dataHtml = [
            'title' => 'W3PAY Backend check of payment',
            'description' => 'W3PAY Backend check of payment',
            'content' => $content,
        ];

        $PluginPaths = $this->getPluginPaths();
        echo $this->getTemplateHtml($PluginPaths['template'], $dataHtml);
        exit;
    }

    public function getTemplateHtml($template, $data = []){
        ob_start();
        try {
            include $template;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    public function paySave($showCheckPayment, $PaySuccess = false){

        if(empty($showCheckPayment['CheckPaymentData']['checkSign']['checkSign']['orderId'])){
            return ['error' => 1, 'data' => 'checkSign orderId is empty'];
        }
        $order_id = $showCheckPayment['CheckPaymentData']['checkSign']['checkSign']['orderId'];

        $PaymentData = $this->getPaymentData($order_id);
        if(!empty($PaymentData['error'])){
            return $PaymentData;
        }

        $chainid = $showCheckPayment['CheckPaymentData']['chainid'];
        $tx = $showCheckPayment['CheckPaymentData']['tx'];
        $Link_payment_result = $PaymentData['PaymentData']['checkPaymentPageUrl'].'&chainid='.$chainid.'&tx='.$tx;

        $comments = $this->language->get('Order_ID').': '.$order_id.'. '.$this->language->get('Chain_ID').': '.$chainid.'. '.$this->language->get('Transaction_Hash').': '.$tx.'. '.$this->language->get('Payment_result').': <a target="_blank" href="'.$Link_payment_result.'">'.$Link_payment_result.'</a>';

        $this->load->model('checkout/order');
        $order_data = $this->model_checkout_order->getOrder($order_id);

        if($PaySuccess){
            $this->load->model('checkout/order');
            $comments = $this->language->get('Payment_completed_successfully').'. '.$comments;
            $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_w3pay_order_status_id'), $comments, true);

            // Deposit
            /*$this->load->model('account/customer');
            if (!$this->model_account_customer->getTotalTransactionsByOrderId($order_id)) {
                $this->model_account_customer->addTransaction($order_data['customer_id'], $comments, (float)$order_data['total'], $order_id);
            }*/

            // Save payment method
            /*$this->load->model('account/payment_method');
            $payment_method_data = [
                'name'        => 'W3PAY - Web3 Crypto Payments',
                'image'       => 'w3pay.png',
                'type'        => 'w3pay',
                'extension'   => 'w3pay',
                'code'        => 'w3pay',
                'token'       => 'chain_id:'.$chainid.';tx:'.$tx,
                'date_expire' => '9999-01-01',
                'default'     => !$this->model_account_payment_method->getTotalPaymentMethods() ? true : false
            ];
            $this->model_account_payment_method->addPaymentMethod($payment_method_data);*/
        } else {
            $this->load->model('checkout/order');
            $comments = $this->language->get('Payment_with_an_error').'. '.$comments;
            $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_w3pay_failed_status_id'), $comments, true);
        }

        return ['error' => 0, 'data' => 'Success'];
    }

    public function getCheckPaymentData()
    {
        $PluginPaths = $this->getPluginPaths();

        if(!file_exists($PluginPaths['wW3payPath'])){
            return ['error' => 1, 'data' => 'File wW3pay not found'];
        }

        if(!file_exists($PluginPaths['sSettingsPath'])){
            return ['error' => 1, 'data' => 'File sSettings not found. The administrator can make <a target="blank" href="'.$PluginPaths['settings_url'].'">settings</a>.'];
        }

        return ['error' => 0, 'data' => 'Success'];
    }

    public function clearCart(){
        if (isset($this->session->data['order_id'])) {
            $this->cart->clear();
        }
    }

    public function sessionClear(){
        if (isset($this->session->data['order_id'])) {
            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
            unset($this->session->data['payment_method']);
            unset($this->session->data['payment_methods']);
            unset($this->session->data['guest']);
            unset($this->session->data['comment']);
            unset($this->session->data['order_id']);
            unset($this->session->data['coupon']);
            unset($this->session->data['reward']);
            unset($this->session->data['voucher']);
            unset($this->session->data['vouchers']);
            unset($this->session->data['totals']);
        }
    }

    public function  getPaymentData($order_id){
        $this->load->language('extension/w3pay/payment/w3pay');

        $PluginPaths = $this->getPluginPaths();
        $wW3payPath = $PluginPaths['wW3payPath'];
        if(!file_exists($wW3payPath)){
            return ['error' => 1, 'data' => 'File wW3pay not found'];
        }
        $sSettingsPath = $PluginPaths['sSettingsPath'];
        if(!file_exists($sSettingsPath)){
            return ['error' => 1, 'data' => 'File sSettings not found. The administrator can make <a target="blank" href="'.$PluginPaths['settings_url'].'">settings</a>.'];
        }

        $this->load->model('checkout/order');
        $order_data = $this->model_checkout_order->getOrder($order_id);

        if (empty($order_data['order_id'])) {
            return ['error' => 1, 'data' => $this->language->get('error_order')];
        }
        if (empty($order_data['payment_code']) || $order_data['payment_code'] != 'w3pay') {
            return ['error' => 1, 'data' => $this->language->get('error_payment_method')];
        }
        if(!empty($order_data['order_status_id']) && $order_data['order_status_id']!=$this->config->get('payment_w3pay_pending_status_id')){
            $this->clearCart();
            $this->sessionClear();
            return ['error' => 1, 'data' => 'Order status is not Pending or empty'];
        }
        if(empty($order_data['currency_code'])){
            return ['error' => 1, 'data' => 'Order currency is empty'];
        }
        if($order_data['currency_code']!='USD'){
            return ['error' => 1, 'data' => 'Select USD currency'];
        }
        if(empty($order_data['total'])){
            return ['error' => 1, 'data' => 'Order total is empty'];
        }
        if(empty($order_data['currency_value'])){
            return ['error' => 1, 'data' => 'currency_value is empty'];
        }
        $amount = $order_data['total'] * $order_data['currency_value'];
        if(empty($amount)){
            return ['error' => 1, 'data' => 'amount is empty'];
        }

        // Set prices to receive tokens
        $orderId = (int)$order_data['order_id']; // Please enter your order number
        $payAmountInReceiveToken = floatval($amount);; // Please enter a price for the order

        $checkPaymentPageUrl = $this->url->link('extension/w3pay/payment/w3pay|checkpayment', 'lang='.$this->getLang(), true);
        $pay_url = htmlspecialchars_decode($this->url->link('extension/w3pay/payment/w3pay|payment', 'order='.$orderId).'&lang='.$this->getLang(), true);

        $PaymentData=[
            'orderId' => $orderId,
            'payAmountInReceiveToken' => $payAmountInReceiveToken,
            'wW3payPath' => $wW3payPath,
            'pay_url' => $pay_url,
            'checkPaymentPageUrl' => $checkPaymentPageUrl,
        ];
        return ['error' => 0, 'data' => 'Success', 'PaymentData'=>$PaymentData];
    }

    public function getPluginPaths(){

        $__DIR = str_replace("\\", "/", __DIR__);
        $PluginPath = strstr($__DIR, '/catalog/controller/', true);

        $curUrl = str_replace($_SERVER['DOCUMENT_ROOT'], "", $__DIR);
        $plugin_url = strstr($curUrl, '/catalog/controller/', true);

        $w3payFrontend = $plugin_url.'/w3pay/w3payFrontend';
        $w3payBackend = $PluginPath . '/w3pay/w3payBackend';

        $wW3payPath = $w3payBackend.'/widget/wW3pay.php';
        $sSettingsPath = $w3payBackend.'/settings/sSettings.php';

        $settingsSend_url = '#';
        $template = $PluginPath.'/catalog/view/template/payment/template.php';


        $PluginPaths = [
            'w3payFrontend' => $w3payFrontend,
            'w3payBackend' => $w3payBackend,
            'wW3payPath' => $wW3payPath,
            'sSettingsPath' => $sSettingsPath,
            'settings_url' => $settingsSend_url,
            'template' => $template,
            'PluginPath' => $PluginPath,
            'plugin_url' => $plugin_url,
        ];

        return $PluginPaths;
    }

    public function addSessionStartPay($order_id){
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if(session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['w3pay'][$order_id]['startPay'] = 1;
            return true;
        }
        return false;
    }
}
