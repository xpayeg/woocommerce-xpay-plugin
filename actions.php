<?php

// Register AJAX handlers for logged-in and guest users
add_action('wp_ajax_validate_xpay_promo_code', 'handle_validate_xpay_promo_code');
add_action('wp_ajax_nopriv_validate_xpay_promo_code', 'handle_validate_xpay_promo_code');
function handle_validate_xpay_promo_code() {
    // Verify the security nonce to ensure the request is legitimate
    check_ajax_referer('validate-promo-code', 'security');

    // Get the gateway settings
    $gateway = new WC_Gateway_Xpay();
    $api_key = $gateway->get_option('payment_api_key');
    $debug = $gateway->get_option("debug");

    // Sanitize and retrieve the promo code, community ID, and API URL from the AJAX request
    $name = sanitize_text_field($_POST['name']);
    $community_id = isset($_POST['community_id']) ? sanitize_text_field($_POST['community_id']) : null; 
    $amount = isset($_POST['amount']) ? sanitize_text_field($_POST['amount']) : null; 
    $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : null;
    $payment_for = isset($_POST['payment_for']) ? sanitize_text_field($_POST['payment_for']) : null;
    $phone_number = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : null;
    $variable_amount_id = isset($_POST['variable_amount_id']) ? sanitize_text_field($_POST['variable_amount_id']) : null;
    $api_url = sanitize_url($_POST['url']);

  // Check if any required parameters are missing
    $required_params = array(
        'name',
        'community_id',
        'amount',
        'currency',
        'payment_for',
        'phone_number',
        'variable_amount_id',
        'api_url'
    );
    foreach ($required_params as $param) {
        if (empty($$param)) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
            return;
        }
    }

    // Prepare the API request payload
    $request_body = json_encode(array(
        'name' => $name,
        'community_id' => $community_id,
        'amount' => $amount,
        'currency' => $currency,
        'payment_for' => $payment_for,
        'phone_number' => $phone_number,
        'variable_amount_id' => $variable_amount_id
    ));

    // Make the API request to validate the promo code
    $response = httpPost($api_url, $request_body, $api_key, $debug);
    $body = json_decode($response, true);
    
    // Handle error response
    if (!isset($body['status']['code']) || $body['status']['code'] !== 200) {
        $error_message = 'Invalid promo code';
        if (isset($body['status']['errors']) && is_array($body['status']['errors'])) {
            foreach ($body['status']['errors'] as $error) {
                if (isset($error['name'])) {
                    $error_message = $error['name'];
                    break;
                }
            }
        }
        wp_send_json_error(array('message' => $error_message));
        return;
    }

    // Check if response has data
    if (isset($body['data'])) {
        wp_send_json_success($body['data']);
    } else {
        wp_send_json_error(array('message' => 'Invalid response format'));
    }    
}

// Update the action registration to match the function name
add_action('wp_ajax_store_promocode_details', 'handle_store_promocode_details');
add_action('wp_ajax_nopriv_store_promocode_details', 'handle_store_promocode_details');
function handle_store_promocode_details() {
    check_ajax_referer('validate-promo-code', 'security');
    
    // Get the promocode_id and discount_amount from the AJAX request
    $promocode_id = sanitize_text_field($_POST['promocode_id']);
    $discount_amount = sanitize_text_field($_POST['discount_amount']);
    
    // Store both values in session
    WC()->session->set('promocode_id', $promocode_id);
    WC()->session->set('discount_amount', $discount_amount);
    
    // Send a success response with promocode details
    wp_send_json_success(array(
        'promocode_id' => $promocode_id,
        'discount_amount' => $discount_amount
    ));
}

add_action('wp_ajax_clear_promocode_details', 'handle_clear_promocode_details');
add_action('wp_ajax_nopriv_clear_promocode_details', 'handle_clear_promocode_details');
function handle_clear_promocode_details() {
    check_ajax_referer('validate-promo-code', 'security');
    
    // Clear promo code data from session
    WC()->session->__unset('promocode_id');
    WC()->session->__unset('discount_amount');
    
    wp_send_json_success(array(
        'message' => 'Promo code cleared successfully'
    ));
}

add_action('wp_ajax_xpay_get_payment_methods_fees', 'xpay_get_payment_methods_fees');
add_action('wp_ajax_nopriv_xpay_get_payment_methods_fees', 'xpay_get_payment_methods_fees');
function xpay_get_payment_methods_fees() {
    // Make payment method check optional
    $selected_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';

    // Ensure WooCommerce is available
    if (!function_exists('WC')) {
        wp_send_json_error(array('message' => 'WooCommerce not available.'));
    }

    // Retrieve plugin settings
    $xpay_gateway = new WC_Gateway_Xpay();
    $api_key = $xpay_gateway->get_option("payment_api_key");
    $community_id = $xpay_gateway->get_option("community_id");
    $currency = get_option('woocommerce_currency');
    $order_amount = WC()->cart->total;

    // Prepare XPAY API request
    $url = $xpay_gateway->get_option("iframe_base_url") . "/api/v1/payments/prepare-amount/";
    $payload = array(
        "community_id" => $community_id,
        "amount" => $order_amount,
        "currency" => $currency
    );
    
    // Add selected_payment_method only if it has a value
    if (!empty($selected_method)) {
        $payload["selected_payment_method"] = $selected_method;
    }
    
    $payload = json_encode($payload);
   
    // Call XPAY API
    $response = httpPost($url, $payload, $api_key, false);
    $resp = json_decode($response, TRUE);

    // Return the API response directly
    if (isset($resp["data"])) {
        wp_send_json_success($resp["data"]);
    } else {
        wp_send_json_error(array('message' => 'Failed to retrieve prepare amount data from Backend.'));
    }
}

?>
