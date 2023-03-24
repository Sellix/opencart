<?php
class ControllerExtensionPaymentSellixpay extends Controller {

	private $error = array();

	public function index() {
		$this->load->language('extension/payment/sellixpay');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

			$this->model_setting_setting->editSetting('payment_sellixpay', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'].'&type=payment', true));
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
                
                $this->load->model('localisation/order_status');

		$orderStatuses = $this->model_localisation_order_status->getOrderStatuses();
                $data['order_statuses'] = $orderStatuses;

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['api_key'])) {
			$data['error_api_key'] = $this->error['api_key'];
		} else {
			$data['error_api_key'] = '';
		}
                
                if (isset($this->error['prefix'])) {
			$data['error_prefix'] = $this->error['prefix'];
		} else {
			$data['error_prefix'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/sellixpay', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/sellixpay', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true);
                
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
                
                if (isset($this->request->post['payment_sellixpay_url_branded'])) {
			$data['payment_sellixpay_url_branded'] = $this->request->post['payment_sellixpay_url_branded'];
		} else {
			$data['payment_sellixpay_url_branded'] = $this->config->get('payment_sellixpay_url_branded');
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

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/sellixpay', $data));
	}

	public function install() {
		$this->load->model('extension/payment/sellixpay');
		$this->model_extension_payment_sellixpay->install();
	}

	public function uninstall() {
		$this->load->model('extension/payment/sellixpay');
		$this->model_extension_payment_sellixpay->uninstall();
	}
        
        protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/sellixpay')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
                if (isset($this->request->post['payment_sellixpay_api_key']) && !$this->request->post['payment_sellixpay_api_key']) {
			$this->error['api_key'] = $this->language->get('error_api_key');
		}
		return !$this->error;
	}
 }
