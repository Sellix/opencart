<?php
namespace Opencart\Admin\Controller\Extension\Sellixpay\Payment;

class Sellixpay extends \Opencart\System\Engine\Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/sellixpay/payment/sellixpay');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_sellixpay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $data['success'] = $this->session->data['success'];
        }

        if (isset($this->error['warning'])) {
                $data['error_warning'] = $this->error['warning'];
        } else {
                $data['error_warning'] = '';
        }

        if (isset($this->error['email'])) {
                $data['error_email'] = $this->error['email'];
        } else {
                $data['error_email'] = '';
        }

        if (isset($this->error['api_key'])) {
                $data['error_api_key'] = $this->error['api_key'];
        } else {
                $data['error_api_key'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/sellixpay/payment/sellixpay', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/sellixpay/payment/sellixpay', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->getNewOrderStatuses($this->model_localisation_order_status->getOrderStatuses());

        if (isset($this->request->post['payment_sellixpay_email'])) {
                $data['payment_sellixpay_email'] = $this->request->post['payment_sellixpay_email'];
        } else {
                $data['payment_sellixpay_email'] = $this->config->get('payment_sellixpay_email');
        }

        if (isset($this->request->post['payment_sellixpay_api_key'])) {
                $data['payment_sellixpay_api_key'] = $this->request->post['payment_sellixpay_api_key'];
        } else {
                $data['payment_sellixpay_api_key'] = $this->config->get('payment_sellixpay_api_key');
        }

        if (isset($this->request->post['payment_sellixpay_prefix'])) {
                $data['payment_sellixpay_prefix'] = $this->request->post['payment_sellixpay_prefix'];
        } else {
                $data['payment_sellixpay_prefix'] = $this->config->get('payment_sellixpay_prefix');
        }

        if (isset($this->request->post['payment_sellixpay_standard_geo_zone_id'])) {
                $data['payment_sellixpay_standard_geo_zone_id'] = $this->request->post['payment_sellixpay_standard_geo_zone_id'];
        } else {
                $data['payment_sellixpay_standard_geo_zone_id'] = $this->config->get('payment_sellixpay_standard_geo_zone_id');
        }

        if (isset($this->request->post['payment_sellixpay_order_status_id'])) {
                $data['payment_sellixpay_order_status_id'] = $this->request->post['payment_sellixpay_order_status_id'];
        } else {
                $data['payment_sellixpay_order_status_id'] = $this->config->get('payment_sellixpay_order_status_id');
                if (!$data['payment_sellixpay_order_status_id']) {
                    $data['payment_sellixpay_order_status_id'] = 2;
                }
        }

        if (isset($this->request->post['payment_sellixpay_status'])) {
                $data['payment_sellixpay_status'] = $this->request->post['payment_sellixpay_status'];
        } else {
                $data['payment_sellixpay_status'] = $this->config->get('payment_sellixpay_status');
        }

        if (isset($this->request->post['payment_sellixpay_sort_order'])) {
                $data['payment_sellixpay_sort_order'] = $this->request->post['payment_sellixpay_sort_order'];
        } else {
                $data['payment_sellixpay_sort_order'] = $this->config->get('payment_sellixpay_sort_order');
        }

        if (isset($this->request->post['payment_sellixpay_title'])) {
                $data['payment_sellixpay_title'] = $this->request->post['payment_sellixpay_title'];
        } else {
                $data['payment_sellixpay_title'] = $this->config->get('payment_sellixpay_title');
        }


        if (isset($this->request->post['payment_sellixpay_desc'])) {
                $data['payment_sellixpay_desc'] = $this->request->post['payment_sellixpay_desc'];
        } else {
                $data['payment_sellixpay_desc'] = $this->config->get('payment_sellixpay_desc');
        }

        if (isset($this->request->post['payment_sellixpay_debug'])) {
                $data['payment_sellixpay_debug'] = $this->request->post['payment_sellixpay_debug'];
        } else {
                $data['payment_sellixpay_debug'] = $this->config->get('payment_sellixpay_debug');
        }

        if (isset($this->request->post['payment_sellixpay_layout'])) {
                $data['payment_sellixpay_layout'] = $this->request->post['payment_sellixpay_layout'];
        } else {
                $data['payment_sellixpay_layout'] = $this->config->get('payment_sellixpay_layout');
        }

        if (isset($this->request->post['payment_sellixpay_confirmations'])) {
                $data['payment_sellixpay_confirmations'] = $this->request->post['payment_sellixpay_confirmations'];
        } else {
                $data['payment_sellixpay_confirmations'] = $this->config->get('payment_sellixpay_confirmations');
        }

        $methods = $this->getPaymentMethods();
        foreach ($methods as $method) {
            $key = $method['id'];
            if (isset($this->request->post['payment_sellixpay_'.$key])) {
                $data['payment_sellixpay'][$key] = $this->request->post['payment_sellixpay_'.$key];
            } else {
                $data['payment_sellixpay'][$key] = $this->config->get('payment_sellixpay_'.$key);
            }
        }
        $data['payment_methods'] = $methods;

        //Defaults
        if (empty($data['payment_sellixpay_title'])) {
            $data['payment_sellixpay_title'] = 'Sellix Pay';
        }
        if (empty($data['payment_sellixpay_desc'])) {
            $data['payment_sellixpay_desc'] = 'Pay with PayPal, Bitcoin, Ethereum, Litecoin and many more gateways via Sellix';
        }
        if (empty($data['payment_sellixpay_prefix'])) {
            $data['payment_sellixpay_prefix'] = 'Order #';
        }
        if (empty($data['payment_sellixpay_confirmations'])) {
            $data['payment_sellixpay_confirmations'] = 1;
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');


        $this->response->setOutput($this->load->view('extension/sellixpay/payment/sellixpay', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/sellixpay/payment/sellixpay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (isset($this->request->post['payment_sellixpay_email']) && !$this->request->post['payment_sellixpay_email']) {
                $this->error['email'] = $this->language->get('error_email');
        }

        if (isset($this->request->post['payment_sellixpay_api_key']) && !$this->request->post['payment_sellixpay_api_key']) {
                $this->error['api_key'] = $this->language->get('error_api_key');
        }
        return !$this->error;
    }
    
    public function getPaymentMethods()
    {
        $this->load->language('extension/payment/sellixpay');
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
    
    public function getNewOrderStatuses($statuses) {
        $result = array();
        $skipStatuses = array(
            'Canceled',
            'Canceled Reversal',
            'Chargeback',
            'Denied',
            'Expired',
            'Failed',
            'Refunded',
            'Reversed',
            'Voided'
        );
        foreach ($statuses as $key => $status) {
            if (!in_array($status['name'], $skipStatuses)) {
                $result[] = $status;
            }
        }
        return $result;
    }
    
    public function install() {
        $this->load->model('extension/sellixpay/payment/sellixpay');
        $this->model_extension_sellixpay_payment_sellixpay->install();
    }

    public function uninstall() {
            $this->load->model('extension/sellixpay/payment/sellixpay');
            $this->model_extension_sellixpay_payment_sellixpay->uninstall();
    }
}