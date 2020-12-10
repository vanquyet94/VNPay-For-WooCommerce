<?php
/**
 * 
 * 
 * @author thangnh
 * @since  1.0.0
 */

namespace vnpay\Gateways;

class vnpayGateway extends \WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'vnpay';
        $this->icon = $this->get_option('logo');
        $this->has_fields = false;
        $this->method_title = __('vnpay', 'woocommerce');

        $this->supports = array(
            'products',
            'refunds'
        );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->Url = $this->get_option('Url');
        $this->terminal = $this->get_option('terminal');
        $this->secretkey = $this->get_option('secretkey');
        $this->receipt_return_url = $this->get_option('receipt_return_url');
        $this->locale = $this->get_option('locale');

        if (!$this->isValidCurrency()) {
            $this->enabled = 'no';
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
    }

    public function getPagesList() {
        $pagesList = array();
        $pages = get_pages();
        if (!empty($pages)) {
            foreach ($pages as $page) {
                $pagesList[$page->ID] = $page->post_title;
            }
        }
        return $pagesList;
    }

    public function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable vnpay Paygate', 'woocommerce'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Tiêu đề', 'woocommerce'),
                'type' => 'text',
                'description' => 'Tiêu đề thanh toán',
                'default' => 'Thanh toán qua VNPAY',
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Mô tả', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('Mô tả phương thức thanh toán', 'woocommerce'),
                'default' => __('Thanh toán trực tuyến qua VNPAY', 'woocommerce'),
                'desc_tip' => true
            ),
            'Url' => array(
                'title' => __('Url khởi tạo GD', 'woocommerce'),
                'type' => 'text',
                'description' => 'Url khởi tạo giao dịch sang VNPAY(VNPAY Cung cấp)',
                'default' => '',
                'desc_tip' => true
            ),
            'terminal' => array(
                'title' => __('Terminal ID', 'woocommerce'),
                'type' => 'text',
                'description' => 'Mã terminal VNPAY cung cấp',
                'default' => '',
                'desc_tip' => true
            ),
            'secretkey' => array(
                'title' => __('Secret Key', 'woocommerce'),
                'type' => 'password',
                'description' => 'Key cấu hình VNPAY cung cấp',
                'default' => '',
                'desc_tip' => true
            ),
            'receipt_return_url' => array(
                'title' => __('Success Page', 'woocommerce'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('Choose success page', 'woocommerce'),
                'desc_tip' => true,
                'default' => '',
                'options' => $this->getPagesList()
            ),
            'locale' => array(
                'title' => __('Locale', 'woocommerce'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('Choose your locale', 'woocommerce'),
                'desc_tip' => true,
                'default' => 'vn',
                'options' => array(
                    'vn' => 'vn',
                    'en' => 'en'
                )
            ),
        );
    }

    public function process_payment($order_id) {
        $order = new \WC_Order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $this->redirect($order_id)
        );
    }

    public function redirect($order_id) {
        $order = new \WC_Order($order_id);
        $amount = number_format($order->order_total, 2, '.', '') * 100;
        $vnp_TxnRef = $order_id;
        $date = date('Y-m-d H:i:s');
        $vnp_Url = $this->Url;
        $vnp_Returnurl = admin_url('admin-ajax.php') . '?action=payment_response&type=international';
        $vnp_Merchant = "VNPAY";
        $vnp_AccessCode = $this->terminal;
        $hashSecret = $this->secretkey;
        $vnp_OrderInfo = 'ORDER' . $order_id;
        $vnp_OrderType = 'orther';
        $vnp_Amount = $amount;
        $vnp_Locale = $this->locale;
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
        $inputData = array(
            "vnp_AccessCode" => $vnp_AccessCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_Merchant" => $vnp_Merchant,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
            "vnp_Version" => "2.0.0",
        );

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . $key . "=" . $value;
            } else {
                $hashdata .= $key . "=" . $value;
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($hashSecret)) {
            $vnpSecureHash =hash('sha256', $hashSecret . $hashdata);
            $vnp_Url .= 'vnp_SecureHashType=SHA256&vnp_SecureHash=' . $vnpSecureHash;
        }
      
        return $vnp_Url;
    }

    public function isValidCurrency() {
        return in_array(get_woocommerce_currency(), array('VND'));
    }

    public function admin_options() {
        if ($this->isValidCurrency()) {
            parent::admin_options();
        } else {
            ?>
            <div class="inline error">
                <p>
                    <strong>
            <?php _e('Gateway Disabled', 'woocommerce'); ?>
                    </strong> : 
            <?php
            _e('vnpay does not support your store currency. Currently, vnpay only supports VND currency.', 'woocommerce');
            ?>
                </p>
            </div>
                        <?php
                    }
                }

            }
            