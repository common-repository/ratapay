<?php
function init_ratapay_gateway() {
    if(!class_exists('WooCommerce'))return;
    class WC_Ratapay_Gateway extends WC_Payment_Gateway {
        function __construct(){
            $this->id = 'ratapay_gateway';
            $this->icon = plugins_url('ratapay/ratapay-logo.png');
            $this->has_fields = false;
            $this->method_title = 'Ratapay';
            // $this->title = 'Ratapay';
            $this->description = 'Bayar via Ratapay<br>
            <img src="http://local.account.ratapay/images/logo-bca.png" style="height:20px;margin:5px;display:inline"/><img src="http://local.account.ratapay/images/logo-bni.png" style="height:20px;margin:5px;display:inline"/><img src="http://local.account.ratapay/images/logo-bri.png" style="height:20px;margin:5px;display:inline"/><img src="http://local.account.ratapay/images/logo-mandiri.png" style="height:20px;margin:5px;display:inline"/><img src="http://local.account.ratapay/images/logo-paypal.png" style="height:20px;margin:5px;display:inline"/>
            ';
            $this->description = 'Bayar Melalui Ratapay';
            $this->init_form_fields();
            $this->init_settings();

            $settingLink = admin_url('admin.php?page=ratapay_setting');

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Ratapay Payment', 'woocommerce' ),
                    'default' => 'yes'
                ),
                'ratapay_api_setting' => array(
                    'title' => __( 'RATAPAY API Setting', 'woocommerce' ),
                    'type' => 'button',
                    'default' => __('Setting', 'woocommerce'),
                    'custom_attributes' => [
                        'onclick' => "location.href='$settingLink'",
                    ],
                    'description' => __( 'Enable then save setting first before clicking this button', 'woocommerce' ),
                )
            );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_wc_ratapay_gateway', array( $this, 'check_ratapay_ipn_response' ) );
        }

        function check_ratapay_ipn_response()
        {
            if(!isset($_POST['data']) || !isset($_POST['hash'])){
                echo '{"success":0}';
                exit();
            }
            $decoded  = json_decode( sanitize_text_field(stripslashes($_POST['data'])), true );
            $encoded = json_encode($decoded);
            $hashRemote = sanitize_text_field($_POST['hash']);
            $calculatedHash = hash_hmac('sha256', $encoded, get_option('ratapay_merchant_secret'));
            if(hash_equals($calculatedHash, $hashRemote)){
                if(time() - intval($decoded['ts']) > 300){
                    error_log( 'IPN request expired. data: ' . $encoded );
                    echo '{"success":0}';
                    exit();
                }
                else {
                    // 1 means succesful payment
                    if ($decoded['action'] == 1) {
                        $prefix = get_option('ratapay_invoice_prefix');
                        $orderId = substr($decoded['invoice_id'], strlen($prefix));
                        if (!is_numeric($orderId)) {
                            error_log( 'Invalid Order ID ' . $orderId );
                            echo '{"success":1}';
                            exit();                            
                        }
                        $order = new WC_Order( $orderId );
                        $order->payment_complete();
                        $order->add_order_note( __('IPN payment completed', 'woothemes') );
                    }

                    // other than 2 can be processed through action hook
                    do_action( 'ratapay_ipn_retrieve', $decoded );

                    echo '{"success":1}';
                    exit();
                }
            }
            else{
                error_log( 'IPN hash mismatch. data: ' . $encoded );
                echo '{"success":0}';
                exit();
            }
        }

        function process_payment( $order_id, $empty_cart = true ) {
            global $woocommerce, $wpdb;
            $order = new WC_Order( $order_id );
            
            //get product description
            $product_description = [];
            $i = 0;
            //get aff and jv
            $affComm = [];
            $jvComm = [];

            $items = [];

            $merchant_id = get_option('ratapay_merchant_id');
            $defaultRefundThreshold = get_option('ratapay_refund_threshold');
            if (empty($defaultRefundThreshold)) {
                $defaultRefundThreshold = 0;
            }
            $minThreshold = $defaultRefundThreshold;
            foreach ($order->get_items() as $item_id => $item)
            {
                $itemTotal = $item->get_total();
                $product = get_post($item['product_id']);

                $splitterId = get_post_meta($item['product_id'], 'ratapay_splitter_id', true);
                if (!empty($splitterId)) {
                    $splitRecord = $wpdb->get_results("select data from {$wpdb->prefix}ratapay_splitter where id = " . $splitterId, 'ARRAY_A');
                    if (isset($splitRecord[0]['data'])) {
                        $splitData = $splitRecord[0]['data'];
                    }
                }

                $refundThreshold = get_post_meta($item['product_id'], 'refund_threshold', true);
                if (!empty($refundThreshold)) {
                    $refundable = 1;
                    $minThreshold = min($minThreshold, $refundThreshold);
                    $refundThreshold .= 'D';
                } else {
                    if (empty($defaultRefundThreshold)) {
                        $refundable = 0;
                        $refundThreshold = '0D';
                    } else {
                        $refundable = 1;
                        $refundThreshold = $defaultRefundThreshold . 'D';
                    }
                }

                if (isset($splitData)) {
                    $splitData = json_decode($splitData, true);
                    foreach ($splitData as $split) {
                        $jvComm[] = [
                            'email' => $split['email'],
                            'share_item_id' => $item['product_id'],
                            'share_amount' => $split['share_amount'],
                            'share_type' => $split['share_type'],
                            'rebill_share_amount' => 0,
                            'merchant_id' => $merchant_id,
                        ];
                    }
                }

                $product_description[] = $product->post_title;
                
                $items[] = [
                    'id' => $product->ID,
                    'qty' => $item->get_quantity(),
                    'subtotal' => $itemTotal,
                    'name' => $product->post_title,
                    // 'category' => '',
                    // 'brand' => '',
                    'type' => $item->get_type(),
                    'refundable' => $refundable,
                    'refund_threshold' => $refundThreshold,
                ];
            }

            foreach ($order->get_items( 'shipping' ) as $shipping) {
                $items[] = [
                    'id' => 'shipping',
                    'qty' => 1,
                    'subtotal' => $shipping['total'],
                    'name' => $shipping['name'],
                    'type' => $shipping['type']
                ];
            }

            // create ratapay invoice
            $note = get_option('ratapay_invoice_title');
            if (!empty($note)) {
                $placeholders = [
                    '{invoice_id}',
                    '{customer_name}',
                    '{customer_email}',
                    '{customer_phone}'
                ];

                foreach ($placeholders as $plh) {
                    if (strpos($note, $plh) >= 0) {
                        switch ($plh) {
                            case '{invoice_id}':
                                $note = str_replace($plh, $order->get_order_number(), $note);
                                break;
                            
                            case '{customer_name}':
                                $note = str_replace($plh, $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), $note);
                                break;
                            
                            case '{customer_email}':
                                $note = str_replace($plh, $order->get_billing_email(), $note);
                                break;
                            
                            case '{customer_phone}':
                                $note = str_replace($plh, $order->get_billing_phone(), $note);
                                break;
                            
                            default:
                                # code...
                                break;
                        }
                    }
                }
            } else {
                $note = $order->get_order_key();
            }

            $postData = array(
                'email'             => $order->get_billing_email(),
                'phone'             => $order->get_billing_phone(),
                'name'              => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), 
                'type'              => 0,
                'amount'            => $order->get_total(),
                'second_amount'     => 0,
                'first_period'      => '',
                'second_period'     => '',
                'rebill_times'      => '0',
                'source_invoice_id' => get_option('ratapay_invoice_prefix').$order_id,
                'note'              => $note,
                'currency'          => 'IDR',
                'ts'                => time(),
                'url_callback'      => add_query_arg( 'wc-api', 'WC_Ratapay_Gateway', home_url( '/' ) ),
                'url_success'       => $this->get_return_url( $order ),
                'aff_share'         => $affComm,
                'vendor_share'      => $jvComm,
                'refundable'        => 1,
                'refund_threshold_value'  => $minThreshold,
                'refund_threshold_unit'  => 'D',
                'merchant_id'       => $merchant_id,
                'items'             => $items
            );

            $url_success = get_option('ratapay_success_url');
            $use_success_url = get_option('use_success_url');
            if (!$use_success_url) {
                $postData['url_success'] = null;
            } else {
                if (!empty($url_success)) {
                    $postData['url_success'] = $url_success;
                }
            }

            $postData = apply_filters( 'ratapay_before_post_transaction', $postData, $order_id );

            $resBody = $this->postTransaction($postData);
            if(empty($resBody) || $resBody['success'] != 1){
                if (empty($resBody) || $resBody['message'] == 'Invalid Access Token') {
                    ratapay_renew_token();
                    $resBody = $this->postTransaction($postData);
                    if(empty($resBody) || $resBody['success'] != 1){
                        wc_add_notice( __('Payment error', 'woothemes'), 'error' );
                    }
                } else {
                    return;
                }
            }

            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status('pending-payment', __( 'Awaiting Ratapay ipn', 'woocommerce' ));

            // Reduce stock levels
            wc_reduce_stock_levels($order);

            // Remove cart
            if($empty_cart){
                $woocommerce->cart->empty_cart();
            }

            // Add payment link encoded in base64
            $payment_link = base64_encode(get_option( 'ratapay_app_uri' ) . '/pay/' . $resBody['key']);
            update_post_meta($order_id, 'ratapay_payment_link', $payment_link );
            // Return thankyou redirect
            $redirSetting = get_option('ratapay_checkout_redirect');
            if (empty($redirSetting) || $redirSetting == 'ratapay') {
                $redirUrl = base64_decode($payment_link);
            } else {
                $redirUrl = $this->get_return_url( $order );
            }
            return array(
                'result' => 'success',
                'redirect' => $redirUrl
            );
        }

        public function postTransaction($postData)
        {
            $time = ratapay_generate_isotime();
            $url = 'POST:/transaction/direct';

            foreach ($postData as $key => $value) {
                if (empty($value)) {
                    unset($postData[$key]);
                }
            }
            $result = wp_remote_post(get_option( 'ratapay_api_uri' ).'/transaction/direct',array(
                'body'=>$postData,
                'timeout' => 300,
                'headers' => array(
                    'Authorization' => 'Bearer ' . get_option('ratapay_token'),
                    'X-RATAPAY-SIGN' => ratapay_generate_sign($url, get_option('ratapay_token'), get_option('ratapay_api_secret'), $time, $postData),
                    'X-RATAPAY-TS' => $time,
                    'X-RATAPAY-KEY' => get_option('ratapay_api_key')
                ),
            ));

            if(is_wp_error($result)){
                wc_add_notice( __('Payment error:', 'woothemes') . 'Ratapay Connection Error', 'error' );
                return [
                    'success' => 0
                ];
            }

            $resBody = wp_remote_retrieve_body($result);
            // error_log(print_r($resBody, true));
            $resBody = json_decode($resBody, true);
            return $resBody;
        }
    }
}

// register ratapay gateway to woocommerce
add_action( 'plugins_loaded', 'init_ratapay_gateway' );
function add_ratapay_gateway( $methods ) {
    $methods[] = 'WC_Ratapay_Gateway'; 
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_ratapay_gateway' );

add_action( 'woocommerce_thankyou', 'ratapay_payment_iframe', 15, 1 );
function ratapay_payment_iframe( $order_id ) {
    $order = new WC_Order( $order_id );
    if ( 'pending' == $order->status) {
        $payment_link = get_post_meta($order_id, 'ratapay_payment_link', true);
        if(!empty($payment_link)){
            $uri = $uri = base64_decode($payment_link);
            echo "<a href='".esc_url($uri)."' class='button'>". __('Lanjut ke Pembayaran')."</a>";
        }
    } else {
        echo "<h6><strong>Status Pesanan Saat Ini : " . esc_html(ucfirst($order->status)) . "</strong></h6>";
    }
}

add_action( 'woocommerce_view_order', 'ratapay_add_payment_iframe_to_order_view', 15, 1 );
function ratapay_add_payment_iframe_to_order_view($order_id){
    $order = new WC_Order( $order_id );
    if ( 'pending' == $order->status) {
        $payment_link = get_post_meta($order_id, 'ratapay_payment_link', true);
        if(!empty($payment_link)){
            $uri = $uri = base64_decode($payment_link);
            echo "<a href='".esc_url($uri)."' class='button'>". __('Lanjut ke Pembayaran')."</a>";
        }
    } else {
        echo "<h6><strong>Status Pesanan Saat Ini : " . esc_html(ucfirst($order->status)) . "</strong></h6>";
    }
}