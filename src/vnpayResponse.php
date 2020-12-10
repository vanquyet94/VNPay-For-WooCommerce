<?php

/**
 * 
 * 
 * @author thangnh
 * @since  1.0.2
 */

namespace vnpay\Responses;

use vnpay\Gateways\vnpayGateway;
use vnpay\Facades\FacadeResponse;

abstract class vnpayResponse implements FacadeResponse {

    protected $hashCode;

    public function __construct() {
        $this->action();
    }

    public function action() {
        add_action('wp_ajax_payment_response', array($this, 'checkResponse'));
        add_action('wp_ajax_nopriv_payment_response', array($this, 'checkResponse'));
        add_action('wp_ajax_payment_response_vnpay', array($this, 'ipn_url_vnpay'));
        add_action('wp_ajax_nopriv_payment_response_vnpay', array($this, 'ipn_url_vnpay'));
    }

    public function checkResponse($txnResponseCode) {
        global $woocommerce;
        $checkoutUrl = $woocommerce->cart->get_checkout_url();
        $successUrl = get_page_link($this->thankyou());
        $txnResponseCode = $_GET["vnp_ResponseCode"];
        $order = $this->getOrder($_GET["vnp_OrderInfo"]);
        $amount = $_GET["vnp_Amount"] / 100;
        $gateway = new vnpayGateway;
        $hashSecret = $gateway->get_option('secretkey');
        //  ($hashSecret);
        $params = array();
        $returnData = array();
        $data = $_GET;
        foreach ($data as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $params[$key] = $value;
            }
        }
        $vnp_SecureHash = $params['vnp_SecureHash'];
        unset($params['vnp_SecureHashType']);
        unset($params['action']);
        unset($params['type']);
        unset($params['vnp_SecureHash']);
        ksort($params);
        $i = 0;
        $hashData = "";
        foreach ($params as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . $key . "=" . $value;
            } else {
                $hashData = $hashData . $key . "=" . $value;
                $i = 1;
            }
        }
        $secureHash = hash('sha256', $hashSecret . $hashData);
        if ($secureHash == $vnp_SecureHash) {
            if ($txnResponseCode == "00") {
                $transStatus = "Giao dịch Thành công";
                //$url = get_site_url() . '/checkout/order-received/' . $order->id . '/?key=' . $order->order_key;
                //wp_redirect($url);
                wp_redirect($successUrl . '?message=' . $transStatus . '&vnp_TxnRef=' . $_GET["vnp_TxnRef"] . '&vnp_BankCode=' . $_GET["vnp_BankCode"] . '&amount=' . $amount);
            } else {
                $error = new \WP_Error('wooonepay_failed', __($transStatus, 'woocommerce'));
                wc_add_wp_error_notices($error);
                // $url = get_site_url() . '/checkout/order-received/' . $order->id . '/?key=' . $order->order_key;
                // wp_redirect($url);

                $transStatus = "Giao dịch không thành công";
                wp_redirect($successUrl . '?message=' . $transStatus . '&vnp_TxnRef=' . $_GET["vnp_TxnRef"] . '&vnp_BankCode=' . $_GET["vnp_BankCode"] . '&amount=' . $amount);
            }
        } else {
            $error = new \WP_Error('wooonepay_failed', __($transStatus, 'woocommerce'));
            wc_add_wp_error_notices($error);
//            $url = get_site_url() . '/checkout/order-received/' . $order->id . '/?key=' . $order->order_key;
//            wp_redirect($url);
            $transStatus = "Sai chữ ký";
            wp_redirect($successUrl . '?message=' . $transStatus . '&vnp_TxnRef=' . $_GET["vnp_TxnRef"] . '&vnp_BankCode=' . $_GET["vnp_BankCode"] . '&amount=' . $amount);
        }

        exit();
    }

    public function ipn_url_vnpay($txnResponseCode) {
        global $woocommerce;
        $transStatus = '';
        $checkoutUrl = $woocommerce->cart->get_checkout_url();
        $successUrl = get_page_link($this->thankyou());
        $txnResponseCode = $_GET["vpc_TxnResponseCode"];
        $order = $this->getOrder($_GET["vnp_TxnRef"]);
        $gateway = new vnpayGateway;
        $hashSecret = $gateway->get_option('secretkey');

        //  ($hashSecret);
        $params = array();
        $returnData = array();
        $data = $_GET;

        foreach ($data as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $params[$key] = $value;
            }
        }
        $vnp_SecureHash = $params['vnp_SecureHash'];
        unset($params['vnp_SecureHashType']);
        unset($params['action']);
        unset($params['type']);
        unset($params['vnp_SecureHash']);
        ksort($params);
        $i = 0;
        $hashData = "";
        foreach ($params as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . $key . "=" . $value;
            } else {
                $hashData = $hashData . $key . "=" . $value;
                $i = 1;
            }
        }
        $secureHash = hash('sha256', $hashSecret . $hashData);
//Check Orderid 
        if ($order->post_status != \NULL && $order->post_status != '') {
            //Check chữ ký
            if ($secureHash == $vnp_SecureHash) {
                //Check Status của đơn hàng
                if ($order->post_status != \NULL && $order->post_status != 'wc-completed') {
                    if ($params['vnp_ResponseCode'] == '00') {
                        $returnData['RspCode'] = '00';
                        $returnData['Message'] = 'Confirm Success';
                        $returnData['Signature'] = $secureHash;
                        $transStatus = $this->getResponseDescription($txnResponseCode);
                        $order->update_status('completed');
                        $order->add_order_note(__($transStatus, 'woocommerce'));
                        $woocommerce->cart->empty_cart();
                    } else {
                        $returnData['RspCode'] = '00';
                        $returnData['Message'] = 'Confirm Success';
                        $returnData['Signature'] = $secureHash;
                        $transStatus = $this->getResponseDescription($txnResponseCode);
                        $order->add_order_note(__($transStatus, 'woocommerce'));
                        $order->update_status('failed');
                        $woocommerce->cart->empty_cart();
                    }
                } else {
                    $returnData['RspCode'] = '02';
                    $returnData['Message'] = 'Order already confirmed';
                    $woocommerce->cart->empty_cart();
                }
            } else {
                $returnData['RspCode'] = '97';
                $returnData['Message'] = 'Chu ky khong hop le';
                $returnData['Signature'] = $secureHash;
                $woocommerce->cart->empty_cart();
            }
        } else {
            $returnData['RspCode'] = '01';
            $returnData['Message'] = 'Order not found';
            $woocommerce->cart->empty_cart();
        }
        echo json_encode($returnData);
        exit();
    }

    abstract public function thankyou();

    abstract public function getResponseDescription($responseCode);

    public function getOrder($orderId) {
        preg_match_all('!\d+!', $orderId, $matches);
        $order = new \WC_Order($matches[0][0]);
        return $order;
    }

}
