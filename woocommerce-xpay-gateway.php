<?php


/**
 * Plugin Name: WooCommerce XPAY Gateway
 * Plugin URI: https://xpay.app/wooCommerce-xpay
 * Description: this is WooCommerce based plugin to use XPAY online payment gateway 
 * Author: XPAY
 * Author URI: https://xpay.app/
 * Version: 1.0
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

require( 'utils.php' );

/**
 * Add the gateway to WC Available Gateways
 * 
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + xpay gateway
 */
function wc_xpay_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Xpay';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_xpay_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_xpay_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=xpay_gateway' ) . '">' . __( 'Configure', 'wc-gateway-xpay' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_xpay_gateway_plugin_links' );


/**
 * Xpay Payment Gateway
 *
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Xpay
 * @extends		WC_Payment_Gateway
 * @package		WooCommerce/Classes/Payment
 * @author 		Xpay
 */
add_action( 'plugins_loaded', 'wc_xpay_gateway_init', 11);
function xpay_custom_validate_billing_phone() {
	if (isset($_POST['billing_phone'])) {
		$is_correct = preg_match("/^01[0-9]{9}$/", $_POST['billing_phone']);
		if (!$is_correct) {
			wc_add_notice(__('The Phone field should start with 01 and be 11 digits long, e.g., 012987654321', 'wc-gateway-xpay'), 'error');
		}
	}
}
add_action('woocommerce_checkout_process', 'xpay_custom_validate_billing_phone');


function wc_xpay_gateway_init() {

	class WC_Gateway_Xpay extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            $this->id = 'xpay_gateway';
            $this->xpay_plugin_url = plugin_dir_url(__FILE__);
            $this->icon = apply_filters('woocommerce_offline_icon', '');
            $this->has_fields = true;
            $this->method_title = __('Xpay', 'wc-gateway-xpay');
            $this->method_description = __('Xpay gateway allow online payment', 'wc-gateway-xpay');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions', $this->description);

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Custom validation for Billing Phone checkout field

            add_filter('the_title', 'woo_personalize_order_received_title', 10, 2);
            if (!function_exists("woo_personalize_order_received_title")) {
                function woo_personalize_order_received_title($title, $id) {
                    if (is_order_received_page() && get_the_ID() === $id) {
                        global $wp;

                        // Get the order
                        $order_id = apply_filters('woocommerce_thankyou_order_id', absint($wp->query_vars['order-received']));
                        $order_key = apply_filters('woocommerce_thankyou_order_key', empty($_GET['key']) ? '' : wc_clean($_GET['key']));

                        if ($order_id > 0) {
                            $order = wc_get_order($order_id);
                            if ($order->get_order_key() != $order_key) {
                                $order = false;
                            }
                        }

                        if (isset($order)) {
                            // $title = '<p id="xpay_order_message">' . __('Thank you - your order is now pending payment. You should see Xpay popup now to make payment.', 'wc-gateway-xpay') . '</p>';
                        }
                    }
                    return $title;
                }
            }

            add_filter('woocommerce_thankyou_order_received_text', 'woo_change_order_received_text', 10, 2);
            if (!function_exists("woo_change_order_received_text")) {
                function woo_change_order_received_text($str, $order) {
                    $name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
                    $email = $order->get_billing_email();
                    $mobile = $order->get_billing_phone(); 
                    global $woocommerce;
                    $wc_settings = new WC_Gateway_Xpay;
                    $payment_method = isset($_REQUEST["xpay_payment"]) ? $_REQUEST["xpay_payment"] : '';

                    $installment_period = isset($_REQUEST["xpay_installment_period"]) ? $_REQUEST["xpay_installment_period"] : '';           
                    // Check if the xpay_payment parameter exists
                    if ($payment_method) {
                        $api_key = $wc_settings->get_option("payment_api_key");
                        $debug = $wc_settings->get_option("debug");
                        $community_id = $wc_settings->get_option("community_id");
                        $order_amount = $order->get_total();
                        $url = $wc_settings->get_option("iframe_base_url") . "/api/v1/payments/prepare-amount/";
                        $payload = json_encode(array(
                            "community_id" => $community_id,
                            "amount" => $order_amount,
                            "selected_payment_method" => $payment_method
                        ));
                        $resp = httpPost($url, $payload, $api_key, $debug);
                        $resp = json_decode($resp, TRUE);
                        $amount = $resp["data"]["total_amount"];

                        if($payment_method == "card"){
                            $payload = json_encode(array (
                                "billing_data" => array (
                                    "name" => $name,
                                    "email" => $email,
                                    "phone_number" => $mobile,
                                ),
                                "community_id" => $community_id,
                                "variable_amount_id" => $wc_settings->get_option("variable_amount_id"),
                                "currency" => $wc_settings->get_option("currency"),
                                "pay_using"=> "card",
                                "amount"=> $amount, 
                            ));
                            $billing_first_name = $order->get_billing_first_name();
                            $url = $wc_settings->get_option("iframe_base_url") . "/api/v1/payments/pay/variable-amount";
                            
                            $resp = httpPost($url , $payload, $api_key, $debug);
                            $resp = json_decode($resp, TRUE);
                            generate_payment_modal($resp["data"]["iframe_url"], $resp["data"]["transaction_uuid"], $order->id, $community_id);
                            add_post_meta($order->id, "xpay_transaction_id", $resp["data"]["transaction_uuid"]);
                            return "<p id='xpay_message'> Your order is waiting XPAY payment you must see xpay popup now or <a data-toggle='modal' data-target='#xpay_modal'> click here </a></p>";
                        }
                        else if($payment_method == "kiosk"){
                            $payload = json_encode(array (
                                "billing_data" => array (
                                    "name" => $name,
                                    "email" => $email,
                                    "phone_number" => $mobile,
                                ),
                                "community_id" => $community_id,
                                "variable_amount_id" => $wc_settings->get_option("variable_amount_id"),
                                "pay_using"=> "kiosk",
                                "amount"=> $amount, 
                            ));
                            $billing_first_name = $order->get_billing_first_name();
                            $url = $wc_settings->get_option("iframe_base_url") . "/api/v1/payments/pay/variable-amount";
                            $resp = httpPost($url , $payload, $api_key. $debug);
                            $resp = json_decode($resp, TRUE);
                            add_post_meta($order->id, "xpay_transaction_id", $resp["data"]["transaction_uuid"]);
                            return "<p id='xpay_message'>".$resp["data"]["message"]."</p>";
                            
                        }
                        else if($payment_method == "fawry"){
                            $payload = json_encode(array (
                                "billing_data" => array (
                                    "name" => $name,
                                    "email" => $email,
                                    "phone_number" => $mobile,
                                ),
                                "community_id" => $community_id,
                                "variable_amount_id" => $wc_settings->get_option("variable_amount_id"),
                                "currency" => $wc_settings->get_option("currency"),
                                "pay_using"=> "fawry",
                                "amount"=> $amount, 
                            ));
                            $billing_first_name = $order->get_billing_first_name();
                            $url = $wc_settings->get_option("iframe_base_url") . "/api/v1/payments/pay/variable-amount";
                            
                            $resp = httpPost($url , $payload, $api_key, $debug);
                            $resp = json_decode($resp, TRUE);
                            generate_payment_modal($resp["data"]["iframe_url"], $resp["data"]["transaction_uuid"], $order->id, $community_id);
                            add_post_meta($order->id, "xpay_transaction_id", $resp["data"]["transaction_uuid"]);
                            return "<p id='xpay_message'> Your order is waiting XPAY payment you must see xpay popup now or <a data-toggle='modal' data-target='#xpay_modal'> click here </a></p>";
                        }
                        else if($payment_method == "valu"){
                            $amount = $order->get_total();
                            $payload = json_encode(array (
                                "billing_data" => array (
                                    "name" => $name,
                                    "email" => $email,
                                    "phone_number" => $mobile,
                                ),
                                "community_id" => $community_id,
                                "variable_amount_id" => $wc_settings->get_option("variable_amount_id"),
                                "currency" => $wc_settings->get_option("currency"),
                                "pay_using"=> "valu",
                                "amount"=> $amount, 
                            ));
                            $billing_first_name = $order->get_billing_first_name();
                            $url = $wc_settings->get_option("iframe_base_url") . "/api/v1/payments/pay/variable-amount";
                            
                            $resp = httpPost($url , $payload, $api_key, $debug);
                            $resp = json_decode($resp, TRUE);
                            generate_payment_modal($resp["data"]["iframe_url"], $resp["data"]["transaction_uuid"], $order->id, $community_id);
                            add_post_meta($order->id, "xpay_transaction_id", $resp["data"]["transaction_uuid"]);
                            return "<p id='xpay_message'> Your order is waiting XPAY payment you must see xpay popup now or <a data-toggle='modal' data-target='#xpay_modal'> click here </a></p>";
                        }
                        else if($payment_method == "wallets"){
                            $amount = $order->get_total();
                            $payload = json_encode(array (
                                "billing_data" => array (
                                    "name" => $name,
                                    "email" => $email,
                                    "phone_number" => $mobile,
                                ),
                                "community_id" => $community_id,
                                "variable_amount_id" => $wc_settings->get_option("variable_amount_id"),
                                "currency" => $wc_settings->get_option("currency"),
                                "pay_using"=> "meeza/digital",
                                "amount"=> $amount, 
                            ));
                            $billing_first_name = $order->get_billing_first_name();
                            $url = $wc_settings->get_option("iframe_base_url") . "/api/v1/payments/pay/variable-amount";
                            
                            $resp = httpPost($url , $payload, $api_key, $debug);
                            $resp = json_decode($resp, TRUE);
                            generate_payment_modal($resp["data"]["iframe_url"], $resp["data"]["transaction_uuid"], $order->id, $community_id);
                            add_post_meta($order->id, "xpay_transaction_id", $resp["data"]["transaction_uuid"]);
                            return "<p id='xpay_message'> Your order is waiting XPAY payment you must see xpay popup now or <a data-toggle='modal' data-target='#xpay_modal'> click here </a></p>";
                        }
                        else if ($payment_method == "installment") {
                            $installment_fees = null;
                            foreach ($resp["data"]["installment_fees"] as $fee) {
                                if ((int)$fee["period_duration"] === (int)$installment_period) {
                                    $installment_fees = $fee["installment_fees"] + $fee["const_fees"];
                                    break;
                                }
                            }
                            
                            $payload = json_encode(array (
                                "billing_data" => array (
                                    "name" => $name,
                                    "email" => $email,
                                    "phone_number" => $mobile,
                                ),
                                "community_id" => $community_id,
                                "variable_amount_id" => $wc_settings->get_option("variable_amount_id"),
                                "currency" => $wc_settings->get_option("currency"),
                                "pay_using"=> "card",
                                "amount"=> $order_amount + $installment_fees , 
                                "installment_period" => $installment_period
                            ));
                            $billing_first_name = $order->get_billing_first_name();
                            $url = $wc_settings->get_option("iframe_base_url") . "/api/v1/payments/pay/variable-amount";
                            
                            $resp = httpPost($url , $payload, $api_key, $debug);
                            $resp = json_decode($resp, TRUE);

                            generate_payment_modal($resp["data"]["iframe_url"], $resp["data"]["transaction_uuid"], $order->id, $community_id);
                            add_post_meta($order->id, "xpay_transaction_id", $resp["data"]["transaction_uuid"]);
                            return "<p id='xpay_message'> Your order is waiting XPAY payment you must see xpay popup now or <a data-toggle='modal' data-target='#xpay_modal'> click here </a></p>";
                        }
                    }
                    return $str;
                }
            }

            // Add JavaScript to remove the xpay_payment parameter from the URL to avoid reopening the card info after refresh
            add_action('wp_footer', 'remove_xpay_payment_param');
            if (!function_exists("remove_xpay_payment_param")) {
                function remove_xpay_payment_param() {
                    if (isset($_GET['xpay_payment'])) {
                        ?>
                        <script type="text/javascript">
                            if (window.history.replaceState) {
                                var url = new URL(window.location);
                                url.searchParams.delete('xpay_payment');
                                window.history.replaceState(null, null, url);
                            }
                        </script>
                        <?php
                    }
                    // remove installment period parameter
                    if (isset($_GET['xpay_installment_period'])) {
                        ?>
                        <script type="text/javascript">
                            if (window.history.replaceState) {
                                var url = new URL(window.location);
                                url.searchParams.delete('xpay_installment_period');
                                window.history.replaceState(null, null, url);
                            }
                        </script>
                        <?php
                    }
                }
            }
        }

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {
            $this->form_fields = apply_filters('wc_xpay_form_fields', array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'wc-gateway-xpay'),
                    'type' => 'checkbox',
                    'label' => __('Enable Xpay Payment', 'wc-gateway-xpay'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'wc-gateway-xpay'),
                    'type' => 'text',
                    'description' => __('This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-xpay'),
                    'default' => __('Xpay Payment', 'wc-gateway-xpay'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'wc-gateway-xpay'),
                    'type' => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'wc­gateway-xpay'),
                    'default' => __('Please remit payment to Store Name upon pickup or delivery.', 'wc-gateway-xpay'),
                    'desc_tip' => true,
                ),
                'instructions' => array(
                    'title' => __('Instructions', 'wc-gateway-xpay'),
                    'type' => 'textarea',
                    'description' => __('Instructions that will be added to the thank you page and emails.', 'wc-gateway-xpay'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'community_id' => array(
                    'title' => __('Community ID', 'wc-gateway-xpay'),
                    'type' => 'text',
                    'description' => __('This is the ID of your community you get form Xpay', 'wc-gateway-xpay'),
                    'desc_tip' => true,
                    'required' => true,
                ),
                'currency' => array(
                    'title' => __('currency', 'wc-gateway-xpay'),
                    'type' => 'select',
                    'required' => true,
                    'options' => array(
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'EGP' => 'EGP',
                        'SAR' => 'SAR',
                    ),
                    'default' => 'EGP'
                ),
                'variable_amount_id' => array(
                    'title' => __('Variable Amount Template ID', 'wc-gateway-xpay'),
                    'type' => 'text',
                    'description' => __('This is the ID of your variable amount object you created on Xpay', 'wc-gateway-xpay'),
                    'default' => __('', 'wc-gateway-xpay'),
                    'desc_tip' => true,
                ),
                'payment_api_key' => array(
                    'title' => __('XPAY payment API key', 'wc-gateway-xpay'),
                    'type' => 'text',
                    'description' => __('This is the API key you get from Xpay', 'wc-gateway-xpay'),
                    'default' => __('', 'wc-gateway-xpay'),
                    'desc_tip' => true,
                ),
                'iframe_base_url' => array(
                    'title' => __('Environment', 'wc-gateway-xpay'),
                    'type' => 'select',
                    'required' => true,
                    'options' => array(
                        'http://127.0.0.1:8000' => __('Local'),
                        'https://new-dev.xpay.app' => __('Development'),
                        'http://staging.xpay.app' => __('Staging'),
                        'https://communities.xpay.app' => __('Production'),
                    ),
                    'default' => 'https://staging.xpay.app'
                ),
                'callback_url' => array(
                    'title' => __('Callback URL :<h4 style="width: max-content;color:blue">' . $this->xpay_plugin_url . 'update_order.php <h4>', 'wc-gateway-xpay'),
                    'type' => 'text',
                    'description' => __('This is callback url that you will add in your api payment on xpay dashboard', 'wc-gateway-xpay'),
                    'default' => __($this->xpay_plugin_url . 'update_order.php', 'wc-gateway-xpay'),
                    'custom_attributes' => array('hidden' => true)
                ),
                'debug' => array(
                    'title' => __('Debug', 'wc-gateway-xpay'),
                    'type' => 'checkbox',
                    'label' => __('Enable debug alert messages', 'wc-gateway-xpay'),
                    'default' => 'no'
                ),
            ));
        }
        public function payment_fields() {
            do_action('woocommerce_xpay_form_start', $this->id);

            // Fetch available payment methods from API
            $community_id = $this->settings['community_id'];
            $api_url =$this->settings['iframe_base_url'] . "/api/communities/preferences/?community_id=" . $community_id;
            $response = wp_remote_get($api_url);
            $payment_methods = [];
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (isset($data['data']['payment_methods'])) {
                    $payment_methods = $data['data']['payment_methods'];
                }
            }

            $method_labels = [
                'CARD' => __('Card', 'wc-gateway-xpay'),
                'FAWRY' => __('Fawry', 'wc-gateway-xpay'),
                'VALU' => __('valU', 'wc-gateway-xpay'),
                'MEEZA/DIGITAL' => __('Wallets', 'wc-gateway-xpay'),
                'Installment' => __('Installment', 'wc-gateway-xpay'),
            ];

            if ($data['data']['supports_installments']) {
                $payment_methods['installment'] = 'Installment';
            }

            echo '<div class="form-row form-row-first">
                    <label for="xpay_payment_method">' . __('Payment Method', 'wc-gateway-xpay') . ' <span class="required">*</span></label>
                    <div class="xpay-payment-methods" style="text-align: left; direction: ltr;">';

            foreach ($payment_methods as $method) {
                if (isset($method_labels[$method])  ) {
                    echo '<label class="xpay-method" style="display: flex; align-items: center;">
                            <input type="radio" name="xpay_payment_method" value="' . strtolower($method) . '" style="margin-right: 5px;" ' . ($method === 'CARD' ? 'checked' : '') . '>
                            ' . $method_labels[$method] . '
                        </label>';
                }
            }

            echo '</div></div>';

            echo '<div id="installment_options" style="display: grid; width: 100%; margin-top: 10px;">
                    <label>' . __('Installment Plans', 'wc-gateway-xpay') . ' <span class="required">*</span></label>
                    <div id="installment_card_container" style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;"></div>
                </div>';

            echo '<input type="hidden" name="xpay_selected_installment_plan" id="xpay_selected_installment_plan" value="">';

            ?>
            <script>
                jQuery(document).ready(function($) {
                    $('#installment_options').hide();

                    $('input[name="xpay_payment_method"]').change(function() {
                        if ($(this).val() === 'installment') { 
                            $.ajax({
                                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                                method: 'POST',
                                data: {
                                    action: 'fetch_installment_plans',
                                    amount: '<?php echo WC()->cart->total; ?>',
                                    url: '<?php echo $this->settings['iframe_base_url'] . "/api/v1/payments/prepare-amount/"; ?>',
                                    api_key: '<?php echo $this->settings['payment_api_key']; ?>',
                                    selected_payment_method: 'installment',
                                    community_id: '<?php echo $this->settings['community_id']; ?>',
                                },
                                success: function(response) {
                                    $('#installment_options').show();
                                    const data = JSON.parse(JSON.parse(response));  
                                    if (data && data.data && data.data.installment_fees) {
                                        const cartAmount = parseFloat('<?php echo WC()->cart->total; ?>'); 
                                        const installmentPlans = data.data.installment_fees;
                                        $('#installment_card_container').empty();

                                        installmentPlans.forEach(function(plan) {
                                            const totalAmount = cartAmount + parseFloat(plan.installment_fees + plan.const_fees);
                                            const monthlyPayment = parseFloat((totalAmount / plan.period_duration).toFixed(2));

                                            const card = `
                                                <div class="installment-card" data-duration="${plan.period_duration}" data-total-amount="${totalAmount.toFixed(2)}"
                                                    style="border: 2px solid #ddd; padding: 15px; border-radius: 8px; width: 200px; text-align: center; cursor: pointer; background: #f9f9f9; flex: 1">
                                                    <strong>${plan.period_duration} <?php echo __('Months', 'wc-gateway-xpay'); ?></strong>
                                                    <p><?php echo __('Total Interest:', 'wc-gateway-xpay'); ?> ${plan.interest_percentage}</p>
                                                    <p><?php echo __('Monthly Payment:', 'wc-gateway-xpay'); ?>  ${monthlyPayment} <?php echo __('EGP', 'wc-gateway-xpay'); ?></p>
                                                </div>
                                            `;
                                            $('#installment_card_container').append(card);
                                        });
                                    } else {
                                        alert('<?php echo __('Failed to fetch installment plans. Please try again.', 'wc-gateway-xpay'); ?>');
                                    }
                                },
                                error: function(error) {
                                    alert('<?php echo __('Failed to load installment plans. Please try again.', 'wc-gateway-xpay'); ?>');
                                }
                            });
                        } else {
                            $('#installment_options').hide();
                        }
                    });

                    $('#installment_card_container').on('click', '.installment-card', function() {
                        $('.installment-card').css({
                            "border": "2px solid #ddd",
                            "background-color": "#fff",
                            "box-shadow": "none",
                            "transform": "scale(1)",
                            "opacity": "1"
                        }); 

                        $(this).css({
                            "border": "2px solid #007cba",
                            "background-color": "#e3f2fd",
                            "box-shadow": "0px 4px 10px rgba(0, 124, 186, 0.3)",
                            "transform": "scale(1.05)",
                            "opacity": "1"
                        }); 
                        const selectedDuration = $(this).data('duration');
                        $('#xpay_selected_installment_plan').val(selectedDuration);
                    });
                });
            </script>
            <?php
            do_action('woocommerce_xpay_form_end', $this->id);
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions));
            }
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false) {
            if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method && $order->has_status('on-hold')) {
                echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
            }
        }

        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            // Mark as on-hold (we're awaiting the payment)
            $order->update_status('pending', __('Awaiting payment', 'wc-gateway-xpay'));

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Return thank you redirect
            $redirect_url = $this->get_return_url($order) . "&xpay_payment=" . $_REQUEST["xpay_payment_method"];

            if (
                isset($_REQUEST["xpay_selected_installment_plan"]) && !empty($_REQUEST["xpay_selected_installment_plan"] )
            ) {
                $redirect_url .= "&xpay_installment_period=" . $_REQUEST["xpay_selected_installment_plan"];
            }


            return array(
                'result' => 'success',
                'redirect' => $redirect_url,
            );
        }
    }
}

if (!function_exists("generate_payment_modal")) {
    function generate_payment_modal($iframe_url, $trn_uuid, $order_id, $community_id) {
        // jQuery code start below
        ?>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

        <script>
            xpay_plugin_url = '<?php echo plugin_dir_url(__FILE__) ?>'
            XPay_JQ = jQuery.noConflict(true);
            XPay_JQ(function (XPay_JQ) {
                XPay_JQ('#xpay_modal').modal({
                    backdrop: 'static',
                    keyboard: false,
                });

                XPay_JQ('#xpay_modal').on('shown.bs.modal', function () {
                    XPay_JQ('#xpay_modal').css("z-index", 900);
                    XPay_JQ(".modal-backdrop:not(#xpay_modal)").hide();
                });

                XPay_JQ('#xpay_modal').on('hidden.bs.modal', function () {
                    trn_uuid = XPay_JQ("#xpay_trn_uuid").val()
                    check_trn_endpoint_url = xpay_plugin_url + 'check_transaction.php';
                    XPay_JQ.get(check_trn_endpoint_url,
                        {
                            trn_uuid: trn_uuid,
                            community_id: '<?php echo $community_id ?>',
                            order_id: '<?php echo $order_id ?>'
                        },
                        function (data) {
                            if (data == "SUCCESSFUL") {
                                XPay_JQ("#xpay_message").text("Thank you - your order payment done Successfully");
                                XPay_JQ('#xpay_modal').modal('hide'); // Close the modal
                            }
                        });
                })
            });
        </script>
        <!-- Modal -->
        <div class="modal fade" id="xpay_modal" role="dialog" style="visibility:visible;"> 
            <div class="modal-dialog">
                <!-- Modal content-->
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Xpay Payment</h4>
                        <p style="color:red">Don't close the popup until you finish payment</p>
                    </div>
                    <div class="modal-body">
                        <iframe src="<?php echo esc_url($iframe_url) ?>" style="border:none; width:100% ;height:450px "></iframe>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="trn_uuid" id="xpay_trn_uuid" value="<?php esc_html_e($trn_uuid, 'wc-gateway-xpay'); ?>">
                        <button type="button" class="btn" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

?>
