<?php
namespace Opencart\Catalog\Model\Extension\Sellixpay\Payment;

class Sellixpay extends \Opencart\System\Engine\Model {
    public function getMethod($address) {
        $this->load->language('extension/sellixpay/payment/sellixpay');

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_hitpay_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

        $status = $this->config->get('payment_sellixpay_status');
        if ($status) {
            if (!$this->config->get('payment_sellixpay_geo_zone_id')) {
                    $status = true;
            } elseif ($query->num_rows) {
                    $status = true;
            } else {
                    $status = false;
            }
        }

        $method_data = array();

        $title = $this->config->get('payment_sellixpay_title');
        $title = trim($title);
        if (empty($title)) {
            $title = $this->language->get('text_title');
        }

        if ($status) {
                $method_data = array(
                        'code'       => 'sellixpay',
                        'title'      => $title,
                        'terms'      => '',
                        'sort_order' => $this->config->get('payment_sellixpay_sort_order')
                );
        }

        return $method_data;
    }

    public function updateOrderData($order_id, $param, $value)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "order SET {$param} = '" . $this->db->escape($value) . "' WHERE order_id = '" . (int)$order_id . "'");
    }

    public function getTransactionByOrderId($order_id)
    {
        $qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "sellixpay_order` WHERE order_id = '" . (int)$order_id . "' LIMIT 1");

        if ($qry->num_rows) {
                $row = $qry->row;
                return $row;
        } else {
                return false;
        }
    }

    public function getTransactionByCode($code)
    {
        $qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "sellixpay_order` WHERE transaction_id = '" . $this->db->escape($code) . "' LIMIT 1");

        if ($qry->num_rows) {
                $row = $qry->row;
                return $row;
        } else {
                return false;
        }
    }

    public function updateTransaction($order_id, $transaction_id, $response='')
    {
        $transaction = $this->getTransactionByOrderId($order_id);
        if ($transaction) {
            $this->db->query("UPDATE " . DB_PREFIX . "sellixpay_order"
                    . " SET response = '" . $this->db->escape($response) . "', transaction_id = '" . $this->db->escape($transaction_id) . "'"
                    . " WHERE order_id = '" . (int)$order_id . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "sellixpay_order`"
                . " SET response = '" . $this->db->escape($response) . "', "
                . "transaction_id = '" . $this->db->escape($transaction_id) . "', "
                . "order_id = {$order_id}"
            );
        }
    }

    public function prepareOrderId($order_id)
    {
        $prefix = $this->config->get('payment_sellixpay_prefix');
        if (empty($prefix)) {
            $prefix = 'Order #';
        }
        return $prefix.$order_id;
    }

    public function log($content) {
        $debug = $this->config->get('payment_sellixpay_debug');
        if ($debug == true) {
            $file = DIR_STORAGE.'logs/sellixpay.log';
            $fp = fopen($file, 'a+');
            fwrite($fp, "\n");
            fwrite($fp, date("Y-m-d H:i:s").": ");
            fwrite($fp, print_r($content, true));
            fclose($fp);
        }
    }
}