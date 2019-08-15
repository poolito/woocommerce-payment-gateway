<?php

if (!defined('ABSPATH'))
    exit;

function Load_Poolito_Gateway()
{
    if (class_exists('WC_Payment_Gateway') && !class_exists('WC_Poolito') && !function_exists('Woocommerce_Add_Poolito_Gateway')) {
        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_Poolito_Gateway');
        add_filter('woocommerce_currencies', 'add_Poolito_IR_currency');
        add_filter('woocommerce_currency_symbol', 'add_Poolito_IR_currency_symbol', 10, 2);

        function Woocommerce_Add_Poolito_Gateway($methods)
        {
            $methods[] = 'WC_Poolito';
            return $methods;
        }

        function add_Poolito_IR_currency($currencies)
        {
            $currencies['IRR'] = __('ریال', 'woocommerce');
            $currencies['IRT'] = __('تومان', 'woocommerce');
            $currencies['IRHR'] = __('هزار ریال', 'woocommerce');
            $currencies['IRHT'] = __('هزار تومان', 'woocommerce');

            return $currencies;
        }

        function add_Poolito_IR_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'IRR':
                    $currency_symbol = 'ریال';
                    break;
                case 'IRT':
                    $currency_symbol = 'تومان';
                    break;
                case 'IRHR':
                    $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT':
                    $currency_symbol = 'هزار تومان';
                    break;
            }
            return $currency_symbol;
        }

        class WC_Poolito extends WC_Payment_Gateway
        {
            public function __construct()
            {
                $this->id = 'WC_Poolito';
                $this->method_title = __('پرداخت امن پولیتو', 'woocommerce');
                $this->method_description = __('تنظیمات درگاه پرداخت پولیتو برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
                $this->icon = apply_filters('WC_Poolito_logo', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/images/logo.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->poolitoapikey = $this->settings['poolitoapikey'];
				        $this->poolitowalletid = $this->settings['poolitowalletid'];

                $this->success_massage = $this->settings['success_massage'];
                $this->failed_massage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                else
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_Poolito_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_Poolito_Gateway'));
            }

            public function admin_options()
            {
                parent::admin_options();
            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters('WC_Poolito_Config', array(
                        'base_confing' => array(
                            'title' => __('تنظیمات پایه ای', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی درگاه پولیتو', 'woocommerce'),
                            'description' => __('برای فعالسازی درگاه پرداخت پولیتو باید چک باکس را تیک بزنید', 'woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                            'default' => __('پرداخت امن پولیتو', 'woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', 'woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                            'default' => __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه پولیتو', 'woocommerce')
                        ),
                        'account_confing' => array(
                            'title' => __('تنظیمات حساب پولیتو', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'poolitoapikey' => array(
                            'title' => __('api key', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('این فیلد مقدار api key می باشد که از پولیتو دریافت کرده اید. در صورتیکه این مقدار را در دست ندارید با پشتیبانی پولیتو تماس حاصل فرمایید.', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'poolitowalletid' => array(
                            'title' => __('کد کیف پول', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('این فیلد مقدار wallet Id می باشد که از پولیتو دریافت کرده اید. درصورتیکه این مقدار را در دست ندارید با پشتیبانی پولیتو تماس حاصل فرمایید.', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true,
                        ),
                        'payment_confing' => array(
                            'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title' => __('پیام پرداخت موفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) پولیتو استفاده نمایید .', 'woocommerce'),
                            'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت پولیتو ارسال میگردد .', 'woocommerce'),
                            'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
                        ),
                    )
                );
            }

            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }

			      public function init_ipg($params, $apikey)
            {
                try {
                    add_action('init', 'add_auth_user_header');
                    function add_auth_user_header() {
                        header( 'x-api-key: ' . $apikey);
                    }

                    $args = array(
                        'body' => json_encode($params),
                        'timeout' => '45',
                        'redirection' => '5',
                        'httpsversion' => '1.0',
                        'blocking' => true,
	                      'headers' => array(
                            'x-api-key' => $apikey,
		                        'Content-Type'  => 'application/json',
		                        'Accept' => 'application/json'
		                    ),
                        'cookies' => array()
                    );

                    $response = wp_remote_post('https://api.poolito.ir/PaymentGatewaySecure/IPGInit', $args);

                    if (is_wp_error($response)) {
                        return false;
                    } else {
                        return json_decode(wp_remote_retrieve_body($response), true);
                    }
                } catch (Exception $ex) {
                    return false;
                }
            }

            public function verify_ipg($params, $apikey)
            {
                  add_action('init', 'add_auth_user_header');
                  function add_auth_user_header() {
                      header( 'x-api-key: ' . $apikey);
                  }

                  $args = array(
                      'body' => json_encode($params),
                      'timeout' => '45',
                      'redirection' => '5',
                      'httpsversion' => '1.0',
                      'blocking' => true,
  	                  'headers' => array(
                          'x-api-key' => $apikey,
  		                    'Content-Type'  => 'application/json',
  		                    'Accept' => 'application/json'
  		                ),
                      'cookies' => array()
                  );

                  $response = wp_remote_post('https://api.poolito.ir/PaymentGatewaySecure/IPGVerify', $args);

                  return $response;
            }

            public function Send_to_Poolito_Gateway($request_id)
            {
                global $woocommerce;
                $woocommerce->session->request_id_poolito = $request_id;
                $order = new WC_Order($request_id);
                $currency = $order->get_order_currency();
                $currency = apply_filters('WC_Poolito_Currency', $currency, $request_id);
                $form = '<form action="" method="POST" class="poolito-checkout-form" id="poolito-checkout-form">
						            <input type="submit" name="poolito_submit" class="button alt" id="poolito-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						            <a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					              </form><br/>';
                $form = apply_filters('WC_Poolito_Form', $form, $request_id, $woocommerce);

                do_action('WC_Poolito_Gateway_Before_Form', $request_id, $woocommerce);
                echo $form;
                do_action('WC_Poolito_Gateway_After_Form', $request_id, $woocommerce);

                $Amount = intval($order->order_total);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') ||
                      strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') ||
                      strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') ||
                      strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') ||
                      strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران'))
                    $Amount = $Amount * 10;
                else if (strtolower($currency) == strtolower('IRHT'))
                    $Amount = $Amount * 10000;
                else if (strtolower($currency) == strtolower('IRHR'))
                    $Amount = $Amount * 1000;
                else if (strtolower($currency) == strtolower('IRR'))
                    $Amount = $Amount * 1;

                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_Poolito_gateway', $Amount, $currency);

                $PoolitoApiKey = $this->poolitoapikey;
				        $PoolitoWalletId = $this->poolitowalletid;
                $CallbackUrl = add_query_arg('wc_order', $request_id, WC()->api_request_url('WC_Poolito'));
                $CallbackUrl = WC()->api_request_url('WC_Poolito');

                $products = array();
                $order_items = $order->get_items();
                foreach ((array)$order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }
                $products = implode(' - ', $products);

                $Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name . ' | محصولات : ' . $products;
                $Mobile = get_post_meta($request_id, '_billing_phone', true) ? get_post_meta($request_id, '_billing_phone', true) : '-';
                $Email = $order->billing_email;
                $Paymenter = $order->billing_first_name . ' ' . $order->billing_last_name;
                $ResNumber = intval($order->get_order_number());

                //Hooks for iranian developer
                $Description = apply_filters('WC_Poolito_Description', $Description, $request_id);
                $Mobile = apply_filters('WC_Poolito_Mobile', $Mobile, $request_id);
                $Email = apply_filters('WC_Poolito_Email', $Email, $request_id);
                $Paymenter = apply_filters('WC_Poolito_Paymenter', $Paymenter, $request_id);
                $ResNumber = apply_filters('WC_Poolito_ResNumber', $ResNumber, $request_id);
                do_action('WC_Poolito_Gateway_Payment', $request_id, $Description, $Mobile);
                $Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
                $Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';

                $data = array('walletId' => $PoolitoWalletId, 'amount' => $Amount, 'redirectUrl' => $CallbackUrl, 'requestId' => $request_id);
                $result = $this->init_ipg($data, $PoolitoApiKey);

                if ($result == null) {
                    $Message = 'تراکنش ناموفق بود.';
                } else {
                    if ($result['description'] == null) {
                        $woocommerce->session->trans_id_poolito = $result['transId'];
                        wp_redirect($result['paymentLink']);
                        exit;
                    } else {
                        $Message = ' تراکنش ناموفق بود- کد خطا : ' . $result['description'];
                        $Fault = '';
                    }
                }

                if (!empty($Message) && $Message) {
                    $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
                    $Note = apply_filters('WC_Poolito_Send_to_Gateway_Failed_Note', $Note, $request_id, $Fault);
                    $order->add_order_note($Note);
                    $Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
                    $Notice = apply_filters('WC_Poolito_Send_to_Gateway_Failed_Notice', $Notice, $request_id, $Fault);

                    if ($Notice)
                        wc_add_notice($Notice, 'error');

                    do_action('WC_Poolito_Send_to_Gateway_Failed', $request_id, $Fault);
                }
            }

            public function Return_from_Poolito_Gateway()
            {
                global $woocommerce;
                $trans_id = $woocommerce->session->trans_id_poolito;

                if (isset($_GET['requestId']))
                    $order_id = $_GET['requestId'];

                if ($order_id) {
                    $order = new WC_Order($order_id);
                    $currency = $order->get_order_currency();
                    $currency = apply_filters('WC_Poolito_Currency', $currency, $order_id);

                    if ($order->status != 'completed') {
                        $PoolitoApiKey = $this->poolitoapikey;
                        $PoolitoWalletId = $this->poolitowalletid;

                        if ($_GET['status'] == "1") {
                            $Amount = intval($order->order_total);
                            $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                            if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') ||
                                  strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') ||
                                  strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') ||
                                  strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') ||
                                  strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران'))
                                $Amount = $Amount * 10;
                            else if (strtolower($currency) == strtolower('IRHT'))
                                $Amount = $Amount * 10000;
                            else if (strtolower($currency) == strtolower('IRHR'))
                                $Amount = $Amount * 1000;
                            else if (strtolower($currency) == strtolower('IRR'))
                                $Amount = $Amount * 1;

                            $data = array('walletId' => $PoolitoWalletId, 'requestId' => $order_id, 'transId' => $trans_id);
                            $result = $this->verify_ipg($data, $PoolitoApiKey);

                            if(wp_remote_retrieve_response_code( $result ) == '200' ) {
                                $Status = 'completed';
                                $Fault = '';
                                $Message = '';
                            } else {
                                $errorRespnse = json_decode(wp_remote_retrieve_body($result));
                                if ($errorRespnse['description'] == null) {
                                    $Status = 'failed';
                                    $Message = '.تراکنش انجام نشد';
                                    $Fault = '';
                                } else {
                                    $Status = 'failed';
                                    $Message = ' تراکنش ناموفق بود- کد خطا : ' . $errorRespnse['description'];
                                    $Fault = $errorRespnse['description'];
                                }
                            }
                        } else {
                            $Status = 'failed';
                            $Fault = '';
                            $Message = '.تراکنش انجام نشد';
                        }

                        if ($Status == 'completed' && isset($trans_id) && $trans_id != 0) {
                            update_post_meta($order_id, '_transaction_id', $trans_id);

                            $order->payment_complete($trans_id);
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $trans_id);
                            $Note = apply_filters('WC_Poolito_Return_from_Gateway_Success_Note', $Note, $order_id, $trans_id);
                            if ($Note)
                                $order->add_order_note($Note, 1);

                            $Notice = wpautop(wptexturize($this->success_massage));
                            $Notice = str_replace("{transaction_id}", $trans_id, $Notice);
                            $Notice = apply_filters('WC_Poolito_Return_from_Gateway_Success_Notice', $Notice, $order_id, $trans_id);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_Poolito_Return_from_Gateway_Success', $order_id, $trans_id);
                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        } else {
                            $tr_id = ($trans_id && $trans_id != 0) ? ('<br/>توکن : ' . $trans_id) : '';
                            $Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $tr_id);
                            $Note = apply_filters('WC_Poolito_Return_from_Gateway_Failed_Note', $Note, $order_id, $trans_id, $Fault);
                            if ($Note)
                                $order->add_order_note($Note, 1);

                            $Notice = wpautop(wptexturize($this->failed_massage));
                            $Notice = str_replace("{transaction_id}", $trans_id, $Notice);
                            $Notice = str_replace("{fault}", $Message, $Notice);
                            $Notice = apply_filters('WC_Poolito_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $trans_id, $Fault);
                            if ($Notice)
                                wc_add_notice($Notice, 'error');

                            do_action('WC_Poolito_Return_from_Gateway_Failed', $order_id, $trans_id, $Fault);
                            wp_redirect($woocommerce->cart->get_checkout_url());
                            exit;
                        }
                    } else {
                        $trans_id = get_post_meta($order_id, '_transaction_id', true);
                        $Notice = wpautop(wptexturize($this->success_massage));
                        $Notice = str_replace("{transaction_id}", $trans_id, $Notice);
                        $Notice = apply_filters('WC_Poolito_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $trans_id);

                        if ($Notice)
                            wc_add_notice($Notice, 'success');

                        do_action('WC_Poolito_Return_from_Gateway_ReSuccess', $order_id, $trans_id);
                        wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                        exit;
                    }
                } else {
                    $Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
                    $Notice = wpautop(wptexturize($this->failed_massage));
                    $Notice = str_replace("{fault}", $Fault, $Notice);
                    $Notice = apply_filters('WC_Poolito_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);

                    if ($Notice)
                        wc_add_notice($Notice, 'error');

                    do_action('WC_Poolito_Return_from_Gateway_No_Order_ID', $order_id, $trans_id, $Fault);
                    wp_redirect($woocommerce->cart->get_checkout_url());
                    exit;
                }
            }
        }
    }
}

add_action('plugins_loaded', 'Load_Poolito_Gateway', 0);
