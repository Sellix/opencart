<?php
class ControllerExtensionPaymentSellixpay extends Controller {
    public function index() {
            $this->load->language('extension/payment/sellixpay');

            $this->load->model('checkout/order');

            $data['module_path'] = HTTPS_SERVER .'image/catalog/sellixpay/';

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

            return $this->load->view('extension/payment/sellixpay', $data);
    }

    public function confirm() {
        $json = array();

        if ($this->session->data['payment_method']['code'] == 'sellixpay') {
            $json = $this->getRedirectUrl();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));		
    }

    public function getRedirectUrl()
    {
        $json = array();
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/sellixpay');

        $error = false;
        $message = '';

        $order_id = (int)($this->session->data['order_id']);

        $order_info = $this->model_checkout_order->getOrder($order_id);

        try {
            $payment_url = $this->generateSellixPayment($order_info);
            $this->model_extension_payment_sellixpay->log('Payment process concerning order '.$order_id.' returned: '.$payment_url);
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
    public function callback()
    {
        $this->load->model('extension/payment/sellixpay');
        $this->model_extension_payment_sellixpay->log('Return Page:');
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
                    $this->model_checkout_order->addOrderHistory((int)$order_id, 1, $comment);
                }
            }
            $this->response->redirect($this->url->link('checkout/success', '', true));
        } else {
            echo 'Order identifier is missing';
            die;
        }
    }
    
    public function webhook()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $this->load->model('extension/payment/sellixpay');
        
        $this->model_extension_payment_sellixpay->log('Sellixpay Webhook received data:');
        $this->model_extension_payment_sellixpay->log($data);
        
        try {
            if ((null ===$data['data']['uniqid']) || empty($data['data']['uniqid'])) {
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
                    $this->model_extension_payment_sellixpay->log('Concerning Sellix order:');
                    $this->model_extension_payment_sellixpay->log($sellix_order);
                    
                    
                    $transaction_id = $sellix_order['uniqid'];
                    $this->model_extension_payment_sellixpay->log('Sellixpay: Order #' . $order_id . ' (' . $transaction_id . '). Status: ' . $sellix_order['status']);
                    if ($sellix_order['status'] == 'PROCESSING') {
                        $comment = sprintf(('Sellix payment processing. '));
                        $comment .= sprintf(('Transaction ID: '). $sellix_order['uniqid']);
                        $comment .= sprintf((' Status: '). $sellix_order['status']);
                        
                        $this->model_extension_payment_sellixpay->updateTransaction($order_id, $transaction_id, json_encode($sellix_order));
                        $order_status_id = (int)$this->config->get('payment_sellixpay_order_status_id');
                        $this->model_checkout_order->addOrderHistory((int)$order_id, $order_status_id, $comment);
                    } elseif ($sellix_order['status'] == 'COMPLETED') {
                        $comment = sprintf(('Sellix payment successful. '));
                        $comment .= sprintf(('Transaction ID: '). $sellix_order['uniqid']);
                        $comment .= sprintf((' Status: '). $sellix_order['status']);
                        
                        $this->model_extension_payment_sellixpay->updateTransaction($order_id, $transaction_id, json_encode($sellix_order));
                        $order_status_id = (int)$this->config->get('payment_sellixpay_order_status_id');
                        $this->model_checkout_order->addOrderHistory((int)$order_id, $order_status_id, $comment);
                    } elseif ($sellix_order['status'] == 'WAITING_FOR_CONFIRMATIONS') {
                        $comment = sprintf(('Awaiting crypto currency confirmations. '));
                        $comment .= sprintf(('Transaction ID: '). $sellix_order['uniqid']);
                        $comment .= sprintf((' Status: '). $sellix_order['status']);

                        $order_status_id = 1;
                        $this->model_checkout_order->addOrderHistory((int)$order_id, $order_status_id, $comment);
                    } elseif ($sellix_order['status'] == 'PARTIAL') {
                        $comment = sprintf(('Cryptocurrency payment only partially paid. '));
                        $comment .= sprintf(('Transaction ID: '). $sellix_order['uniqid']);
                        $comment .= sprintf((' Status: '). $sellix_order['status']);

                        $order_status_id = 1;
                        $this->model_checkout_order->addOrderHistory((int)$order_id, $order_status_id, $comment);
                    } elseif ($sellix_order['status'] == 'PENDING') {
                        $comment = sprintf(('You Sellix Payment status is still pending. '));
                        $comment .= sprintf(('Transaction ID: '). $sellix_order['uniqid']);
                        $comment .= sprintf((' Status: '). $sellix_order['status']);

                        $order_status_id = 1;
                        $this->model_checkout_order->addOrderHistory((int)$order_id, $order_status_id, $comment);    
                    } else {
                        $comment = sprintf(('Order canceled. '));
                        $comment .= sprintf(('Transaction ID: '). $sellix_order['uniqid']);
                        $comment .= sprintf((' Status: '). $sellix_order['status']);

                        $order_status_id = 7;
                        $this->model_checkout_order->addOrderHistory((int)$order_id, $order_status_id, $comment);
                    }
                } else {
                    throw new \Exception('Sellixpay: suspected fraud. Code-003');
                }
            } else {
                throw new \Exception('Sellixpay: suspected fraud. Code-002');
            }
        } catch (\Exception $e) {
            $this->model_extension_payment_sellixpay->log($e->getMessage());
            die;
        }
    }
    
    public function getApiUrl()
    {
        return 'https://dev.sellix.io';
    }
    
    function sellixPostAuthenticatedJsonRequest($route, $body = false, $extra_headers = false, $method="POST")
    {
        $this->load->model('extension/payment/sellixpay');
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
        
        $this->model_extension_payment_sellixpay->log($url);
        $this->model_extension_payment_sellixpay->log($headers);
        $this->model_extension_payment_sellixpay->log($body);

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
        $this->model_extension_payment_sellixpay->log($response['body']);
        $response['error'] = curl_error($ch);
        
        return $response;
    }
    
    public function generateSellixPayment($order_info)
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/sellixpay');
        $order_id = $order_info['order_id'];

        $total = (float)$this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);

        $return_url = $this->url->link('extension/payment/sellixpay/callback', 'order_id='.$order_id, true);
        $webhook_url = $this->url->link('extension/payment/sellixpay/webhook', 'order_id='.$order_id, true);

        $return_url = str_replace('&amp;', '&', $return_url);
        $webhook_url = str_replace('&amp;', '&', $webhook_url);

        $params = [
            'title' => $this->model_extension_payment_sellixpay->prepareOrderId($order_id),
            'currency' => $order_info['currency_code'],
            'return_url' => $return_url,
            'webhook' => $webhook_url,
            'email' => $order_info['email'],
            'value' => $total,
            'origin' => 'OPENCART',
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
            $this->model_extension_payment_sellixpay->updateTransaction($order_id, $transaction_id, $response['body']);

            $url = $responseDecode['data']['url'];
            if ($this->config->get('payment_sellixpay_url_branded')) {
                if (isset($responseDecode['data']['url_branded'])) {
                    $url = $responseDecode['data']['url_branded'];
                }
            }

            return $url;
        } else {
            throw new \Exception ('Payment error: '.$response['error']);
        }
    }
    
    public function validSellixOrder($order_uniqid)
    {
        $this->load->model('extension/payment/sellixpay');
        $route = "/v1/orders/" . $order_uniqid;
        $response = $this->sellixPostAuthenticatedJsonRequest($route,'','','GET');

        $this->model_extension_payment_sellixpay->log('Order validation returned:'.$response['body']);
        
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