<?php
namespace Opencart\Catalog\Controller\Extension\Sellixpay\Payment;

class Sellixpay extends \Opencart\System\Engine\Controller {
	public function index() {
            $this->load->language('extension/sellixpay/payment/sellixpay');
            
            $data['button_confirm'] = $this->language->get('button_confirm');

            $data['action'] = $this->url->link('extension/sellixpay/payment/sellixpay|send', '', true);
            
            $methods = $this->getPaymentMethods();
            $payment_methods = [];

            $usdt = 0;
            $usdc = 0;

            $usdt_methods = array('usdt_erc20', 'usdt_bep20', 'usdt_trc20');
            $usdc_methods = array('usdc_erc20', 'usdc_bep20');

            foreach ($methods as $method) {
                $value =  $this->config->get('payment_sellixpay_'.$method['id']);
                $method['active'] = 0;
                if ($value) {
                    $method['active'] = 1;

                    if ($method['id'] == 'usdt') {
                        $usdt = 1;
                    } else if ($method['id'] == 'usdc') {
                        $usdc = 1;
                    } else {
                        if (in_array($method['id'], $usdt_methods)) {
                            if ($usdt) {
                                $payment_methods[] = $method;
                            }
                        } else if (in_array($method['id'], $usdc_methods)) {
                            if ($usdc) {
                                $payment_methods[] = $method;
                            }
                        } else {
                            $payment_methods[] = $method;
                        }
                    }
                }
            }

            $data['sellixpay_layout'] = $this->config->get('payment_sellixpay_layout');
            $data['payment_methods'] = $payment_methods;
            $data['module_path'] = HTTP_SERVER .'extension/sellixpay/catalog/view/image/payment/sellixpay/';

            $title = $this->config->get('payment_sellixpay_title');
            if (empty($title)) {
                $title = $this->language->get('text_title');
            }

            $desc = $this->config->get('payment_sellixpay_desc');
            if (empty($desc)) {
                $desc = 'Pay with PayPal, Bitcoin, Ethereum, Litecoin and many more gateways via Sellix';
            }

            $data['sellixpay_title'] = $title;
            $data['sellixpay_description'] = $desc;

            $data['error_select_gateway'] = $this->language->get('error_select_gateway');

            return $this->load->view('extension/sellixpay/payment/sellixpay', $data);
	}

	public function callback() {
            $this->load->model('extension/sellixpay/payment/sellixpay');
            $this->model_extension_sellixpay_payment_sellixpay->log('Return Page:');
            $order_id = false;
            if (isset($this->session->data['order_id'])) {
                $order_id = (int)($this->session->data['order_id']);
            } else if (isset($this->request->get['order_id'])) {
                $order_id = (int)($this->request->get['order_id']);
            }

            if ($order_id) {
                $this->load->model('checkout/order');
                $order_info = $this->model_checkout_order->getOrder($order_id);
                if ($order_info) {
                    $order_status_id = (int)$order_info['order_status_id'];
                    if ($order_status_id == 0) {
                        $comment = 'Order is just created and waiting for the payment confirmation. Order status will be updated in the webhook.';
                        $this->model_checkout_order->addHistory((int)$order_id, 1, $comment);
                    }
                }
                $this->response->redirect($this->url->link('checkout/success', '', true));
            } else {
                $this->model_extension_sellixpay_payment_sellixpay->log('Order identifier is missing');
                $this->response->redirect($this->url->link('checkout/failure', '', true));
                die;
            }
	}

    public function webhook() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $this->load->model('extension/sellixpay/payment/sellixpay');
        
        $this->model_extension_sellixpay_payment_sellixpay->log('Sellixpay Webhook received data:');
        $this->model_extension_sellixpay_payment_sellixpay->log($data);
        
        try {
            if (!isset($data['data']['uniqid'] || empty($data['data']['uniqid']))) {
                throw new \Exception(sprintf('Sellixpay: suspected fraud. Code-001'));
            }
            
            $order_id = false;
            if (isset($this->request->get['order_id'])) {
                $order_id = (int)($this->request->get['order_id']);
            }

            if ($order_id) {
                $this->load->model('checkout/order');
                $order_info = $this->model_checkout_order->getOrder($order_id);
                if ($order_info) {
                    $sellix_order = $this->validSellixOrder($data['data']['uniqid']);
                    $this->model_extension_sellixpay_payment_sellixpay->log('Concerning Sellix order:');
                    $this->model_extension_sellixpay_payment_sellixpay->log($sellix_order);
  
                    $transaction_id = $sellix_order['uniqid'];
                    $this->model_extension_sellixpay_payment_sellixpay->log('Sellixpay: Order #' . $order_id . ' (' . $transaction_id . '). Status: ' . $sellix_order['status']);
                    if ($sellix_order['status'] == 'COMPLETED') {
                        $comment = sprintf(('Sellix payment successful. '));
                        $comment .= sprintf(('Transaction ID: '). $sellix_order['uniqid'];
                        $comment .= sprintf((' Status: '). $sellix_order['status'];
                        
                        $this->model_extension_sellixpay_payment_sellixpay->updateTransaction($order_id, $transaction_id, json_encode($sellix_order));
                        $order_status_id = (int)$this->config->get('payment_sellixpay_order_status_id');
                        $this->model_checkout_order->addHistory((int)$order_id, $order_status_id, $comment);
                    } elseif ($sellix_order['status'] == 'WAITING_FOR_CONFIRMATIONS') {
                        $comment = sprintf(('Awaiting crypto currency confirmations. '));
                        $comment .= sprintf(('Transaction ID: '). $sellix_order['uniqid'];
                        $comment .= sprintf((' Status: '). $sellix_order['status'];

                        $order_status_id = 1;
                        $this->model_checkout_order->addHistory((int)$order_id, $order_status_id, $comment);
                    } elseif ($sellix_order['status'] == 'PARTIAL') {
                        $comment = sprintf(('Cryptocurrency payment only partially paid. '));
                        $comment .= sprintf(('Transaction ID: '). $sellix_order['uniqid'];
                        $comment .= sprintf((' Status: '). $sellix_order['status'];

                        $order_status_id = 1;
                        $this->model_checkout_order->addHistory((int)$order_id, $order_status_id, $comment);
                    } else {
                        $comment = sprintf(('Order canceled. '));
                        $comment .= sprintf(('Transaction ID: '). $sellix_order['uniqid'];
                        $comment .= sprintf((' Status: '). $sellix_order['status'];

                        $order_status_id = 7;
                        $this->model_checkout_order->addHistory((int)$order_id, $order_status_id, $comment);
                    }
                } else {
                    throw new \Exception('Sellixpay: suspected fraud. Code-003');
                }
            } else {
                throw new \Exception('Sellixpay: suspected fraud. Code-002');
            }
        } catch (\Exception $e) {
            $this->model_extension_sellixpay_payment_sellixpay->log($e->getMessage());
            die;
        }
    }

    public function send() {
        $json = array();
        
        $json = $this->getRedirectUrl();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));		

    }
    
    public function getRedirectUrl()
    {
        $json = array();
        $this->load->model('checkout/order');
        $this->load->model('extension/sellixpay/payment/sellixpay');

        $error = false;
        $message = '';

        $payment_gateway = $this->request->post['payment_gateway'];
        $payment_gateway = trim($payment_gateway);
        $payment_gateway = strip_tags($payment_gateway);

        $order_id = (int)($this->session->data['order_id']);

        $order_info = $this->model_checkout_order->getOrder($order_id);

        try {
            $payment_url = $this->generateSellixPayment($payment_gateway, $order_info);
            $this->model_extension_sellixpay_payment_sellixpay->log('Payment process concerning order '.$order_id.' returned: '.$payment_url);
            $json['redirect'] = $payment_url;
        } catch (\Exception $e) {
            $error = true;
            $message = $e->getMessage();
        }

        if ($error) {
            $message2 = 'Payment could not be processed, please try again. '.$message;
            $json['error'] = $message2;
        }
        return $json;
    }
        
    public function getPaymentMethods()
    {
        $this->load->language('extension/sellixpay/payment/sellixpay');
        $list = array(
            array(
                'id' => 'bitcoin',
                'value' => 'BITCOIN',
                'label' => $this->language->get('entry_bitcoin'),
                'img' => 'bitcoin'
            ),
            array(
                'id' => 'ethereum',
                'value' => 'EUTHEREUM',
                'label' => $this->language->get('entry_ethereum'),
                'img' => 'ethereum'
            ),
            array(
                'id' => 'bitcoin_cash',
                'value' => 'BITCOINCASH',
                'label' => $this->language->get('entry_bitcoin_cash'),
                'img' => 'bitcoin-cash'
            ),
            array(
                'id' => 'litecoin',
                'value' => 'LITECOIN',
                'label' => $this->language->get('entry_litecoin'),
                'img' => 'litecoin'
            ),
            array(
                'id' => 'concordium',
                'value' => 'CONCORDIUM',
                'label' => $this->language->get('entry_concordium'),
                'img' => 'concordium'
            ),
            array(
                'id' => 'tron',
                'value' => 'TRON',
                'label' => $this->language->get('entry_tron'),
                'img' => 'tron'
            ),
            array(
                'id' => 'nano',
                'value' => 'NANO',
                'label' => $this->language->get('entry_nano'),
                'img' => 'nano'
            ),
            array(
                'id' => 'monero',
                'value' => 'MONERO',
                'label' => $this->language->get('entry_monero'),
                'img' => 'monero'
            ),
            array(
                'id' => 'ripple',
                'value' => 'RIPPLE',
                'label' => $this->language->get('entry_ripple'),
                'img' => 'ripple'
            ),
            array(
                'id' => 'solana',
                'value' => 'SOLANA',
                'label' => $this->language->get('entry_solana'),
                'img' => 'solana'
            ),
            array(
                'id' => 'cronos',
                'value' => 'CRONOS',
                'label' => $this->language->get('entry_cronos'),
                'img' => 'cronos'
            ),
            array(
                'id' => 'binance_coin',
                'value' => 'BINANCE_COIN',
                'label' => $this->language->get('entry_binance_coin'),
                'img' => 'binance'
            ),
            array(
                'id' => 'paypal',
                'value' => 'PAYPAL',
                'label' => $this->language->get('entry_paypal'),
                'img' => 'paypal'
            ),
            array(
                'id' => 'stripe',
                'value' => 'STRIPE',
                'label' => $this->language->get('entry_stripe'),
                'img' => 'stripe'
            ),
            array(
                'id' => 'usdt',
                'value' => '',
                'label' => $this->language->get('entry_usdt'),
                'img' => ''
            ),
            array(
                'id' => 'usdt_erc20',
                'value' => 'USDT:ERC20',
                'label' => $this->language->get('entry_usdt_erc20'),
                'img' => 'usdt'
            ),
            array(
                'id' => 'usdt_bep20',
                'value' => 'USDT:BEP20',
                'label' => $this->language->get('entry_usdt_bep20'),
                'img' => 'usdt'
            ),
            array(
                'id' => 'usdt_trc20',
                'value' => 'USDT:TRC20',
                'label' => $this->language->get('entry_usdt_trc20'),
                'img' => 'usdt'
            ),
            array(
                'id' => 'usdc',
                'value' => '',
                'label' => $this->language->get('entry_usdc'),
                'img' => ''
            ),
            array(
                'id' => 'usdc_erc20',
                'value' => 'USDC:ERC20',
                'label' => $this->language->get('entry_usdc_erc20'),
                'img' => 'usdc'
            ),
            array(
                'id' => 'usdc_bep20',
                'value' => 'USDC:BEP20',
                'label' => $this->language->get('entry_usdc_bep20'),
                'img' => 'usdc'
            ),
            array(
                'id' => 'binance_pay',
                'value' => 'BINANCE_PAY',
                'label' => $this->language->get('entry_binance_pay'),
                'img' => 'binance'
            ),
            array(
                'id' => 'skrill',
                'value' => 'SKRILL',
                'label' => $this->language->get('entry_skrill'),
                'img' => 'skrill'
            ),
            array(
                'id' => 'perfectmoney',
                'value' => 'PERFECTMONEY',
                'label' => $this->language->get('entry_perfectmoney'),
                'img' => 'pm'
            ),
        );
        return $list;
    }
    
    public function getPaymentMethodOptions()
    {
        $options = [];
        $list = $this->getPaymentLogos();
        foreach ($list as $item) {
            $options[$item['id']] = $item;
        }
        
        return $options;
    }
    
    public function getApiUrl()
    {
        return 'https://dev.sellix.io';
    }
    
    function sellixPostAuthenticatedJsonRequest($route, $body = false, $extra_headers = false, $method="POST")
    {
        $this->load->model('extension/sellixpay/payment/sellixpay');
        $server = $this->getApiUrl();

        $url = $server . $route;

        $uaString = 'Sellix Opencart (PHP ' . PHP_VERSION . ')';
        $apiKey = trim($this->config->get('payment_sellixpay_api_key'));
        $headers = array(
            'Content-Type: application/json',
            'User-Agent: '.$uaString,
            'Authorization: Bearer ' . $apiKey
        );

        if($extra_headers && is_array($extra_headers)) {
            $headers = array_merge($headers, $extra_headers);
        }
        
        $this->model_extension_sellixpay_payment_sellixpay->log($url);
        $this->model_extension_sellixpay_payment_sellixpay->log($headers);
        $this->model_extension_sellixpay_payment_sellixpay->log($body);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if (! empty( $body )) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode( $body ));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response['body'] = curl_exec($ch);
        $response['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->model_extension_sellixpay_payment_sellixpay->log($response['body']);
        $response['error'] = curl_error($ch);
        
        return $response;
    }
    
    public function generateSellixPayment($payment_gateway, $order_info)
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/sellixpay/payment/sellixpay');
        if (!empty($payment_gateway)) {
            $order_id = $order_info['order_id'];

            $total = (float)$this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
            
            $params = [
                'title' => $this->model_extension_sellixpay_payment_sellixpay->prepareOrderId($order_id),
                'currency' => $order_info['currency_code'],
                'return_url' => $this->url->link('extension/sellixpay/payment/sellixpay|callback', 'order_id='.$order_id, true),
                'webhook' => $this->url->link('extension/sellixpay/payment/sellixpay|webhook', 'order_id='.$order_id, true),
                'email' => $order_info['email'],
                'value' => $total,
                'gateway' => $payment_gateway,
                'confirmations' => $this->config->get('payment_sellixpay_confirmations')
            ];

            $route = "/v1/payments";
            $response = $this->sellixPostAuthenticatedJsonRequest($route, $params);
            
            if (isset($response['body']) && !empty($response['body'])) {
                $responseDecode = json_decode($response['body'], true);
                if (isset($responseDecode['error']) && !empty($responseDecode['error'])) {
                    throw new \Exception ('Payment error: '.$responseDecode['status'].'-'.$responseDecode['error']);
                }
                
                $transaction_id = '';
                if (isset($responseDecode['data']['uniqid'])) {
                    $transaction_id = $responseDecode['data']['uniqid'];
                }
                $this->model_extension_sellixpay_payment_sellixpay->updateTransaction($order_id, $transaction_id, $response['body']);
                
                return $responseDecode['data']['url'];
            } else {
                throw new \Exception ('Payment error: '.$response['error']);
            }
        } else{
            throw new \Exception('Payment Gateway Error: Sellix Before API Error: Payment Method Not Selected');
        }
    }
    
    public function validSellixOrder($order_uniqid)
    {
        $this->load->model('extension/sellixpay/payment/sellixpay');
        $route = "/v1/orders/" . $order_uniqid;
        $response = $this->sellixPostAuthenticatedJsonRequest($route,'','','GET');

        $this->model_extension_sellixpay_payment_sellixpay->log('Order validation returned:'.$response['body']);
        
        if (isset($response['body']) && !empty($response['body'])) {
            $responseDecode = json_decode($response['body'], true);
            if (isset($responseDecode['error']) && !empty($responseDecode['error'])) {
                throw new \Exception ('Payment error: '.$responseDecode['status'].'-'.$responseDecode['error']);
            }

            return $responseDecode['data']['order'];
        } else {
            throw new \Exception ('Unable to verify order via Sellix Pay API');
        }
    }
}