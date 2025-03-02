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
    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key // Add the API key header
        ),
        'body' => $request_body,
        'timeout' => 30
    ));

    // Check if there was an error with the API request
    if (is_wp_error($response)) {
        error_log('XPAY Promo Code Error: ' . $response->get_error_message());
        wp_send_json_error(array('message' => 'Failed to validate promo code'));
        return;
    }

    // Retrieve the status code and body of the API response
    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    // Log the response for debugging if debug mode is enabled
    if ($gateway->get_option('debug') === 'yes') {
        error_log('XPAY Promo Code Response: ' . print_r([
            'status' => $status_code,
            'body' => $body,
            'headers' => wp_remote_retrieve_headers($response)
        ], true));
    }

    // Handle non-200 response status
    if ($status_code !== 200) {
        $error_message = isset($body['message']) ? $body['message'] : 'Invalid promo code';
        wp_send_json_error(array('message' => $error_message));
        return;
    }

    // Ensure the response body contains the expected data
    if (isset($body['data'])) {
        wp_send_json_success($body['data']);
    } else {
        wp_send_json_error(array('message' => 'Invalid response format'));
    }

    wp_die();
}

add_action('wp_ajax_store_promocode_id', 'store_promocode_id');
add_action('wp_ajax_nopriv_store_promocode_id', 'store_promocode_id');
function store_promocode_id() {
    check_ajax_referer('validate-promo-code', 'security');
    
    // Get the promocode_id and discount_amount from the AJAX request
    $promocode_id = sanitize_text_field($_POST['promocode_id']);
    $discount_amount = sanitize_text_field($_POST['discount_amount']);
    
    // Store both values in session
    WC()->session->set('promocode_id', $promocode_id);
    WC()->session->set('discount_amount', $discount_amount);
    
    // Send a success response
    wp_send_json_success(array('message' => 'Promocode ID and discount amount stored successfully'));
}

add_action('woocommerce_checkout_order_processed', 'store_xpay_promocode_after_payment', 10, 3);
function store_xpay_promocode_after_payment($order_id, $posted_data, $order) {
    $promocode_id = WC()->session->get('promocode_id');
    $discount_amount = WC()->session->get('discount_amount');
    error_log("Debug: Storing promocode_id in order meta: $promocode_id");
    error_log("Debug: Storing discount_amount in order meta: $discount_amount");

    if ($promocode_id && $discount_amount) {
        update_post_meta($order_id, '_xpay_promocode_id', $promocode_id);
        update_post_meta($order_id, '_xpay_discount_amount', $discount_amount);

        // Clear both session variables
        WC()->session->__unset('promocode_id');
        WC()->session->__unset('discount_amount');
    } else {
        error_log("Debug: Missing promocode_id or discount_amount in session.");
    }
}

add_action('wp_ajax_xpay_store_prepared_amount_dynamically', 'xpay_store_prepared_amount_dynamically');
add_action('wp_ajax_nopriv_xpay_store_prepared_amount_dynamically', 'xpay_store_prepared_amount_dynamically');
function xpay_store_prepared_amount_dynamically() {
    // Check if a payment method was sent
    if (!isset($_POST['payment_method'])) {
        wp_send_json_error(array('message' => 'No payment method selected.'));
    }

    $selected_method = sanitize_text_field($_POST['payment_method']);

    // Ensure WooCommerce is available
    if (!function_exists('WC')) {
        wp_send_json_error(array('message' => 'WooCommerce not available.'));
    }

    // Retrieve plugin settings
    $xpay_gateway = new WC_Gateway_Xpay();
    $api_key = $xpay_gateway->get_option("payment_api_key");
    $community_id = $xpay_gateway->get_option("community_id");
    $order_amount = WC()->cart->subtotal;

    // Prepare XPAY API request
    $url = $xpay_gateway->get_option("iframe_base_url") . "/api/v1/payments/prepare-amount/";
    $payload = json_encode(array(
        "community_id" => $community_id,
        "amount" => $order_amount,
        "selected_payment_method" => $selected_method
    ));
    error_log("Debug: Sending request to XPAY API: $url");
    error_log("Debug: Payload: $payload");

    // Call XPAY API
    $response = httpPost($url, $payload, $api_key, false);
    $resp = json_decode($response, TRUE);

    // Debug log the full response
    error_log("Debug: Full XPAY API Response: " . print_r($resp, true));

    // Check if XPAY returned a valid amount
    if (isset($resp["data"])) {
        $selected_method_upper = strtoupper($selected_method);
        error_log("Debug: Selected method: " . $selected_method_upper);

        // Get the fees based on selected payment method
        if (isset($resp["data"][$selected_method_upper])) {
            $method_data = $resp["data"][$selected_method_upper];
            $new_total_amount = $method_data["total_amount"];
            $xpay_fees = $method_data["xpay_fees_amount"];
            $community_fees = $method_data["community_fees_amount"];
            $currency = $method_data["total_amount_currency"];
        } else {
            // Use the root level data since all methods have same fees
            $new_total_amount = $resp["data"]["total_amount"];
            $xpay_fees = $resp["data"]["xpay_fees_amount"];
            $community_fees = $resp["data"]["community_fees_amount"];
            $currency = $resp["data"]["total_amount_currency"];
        }

        wp_send_json_success(array(
            'total_amount' => floatval($new_total_amount),
            'xpay_fees' => floatval($xpay_fees),
            'community_fees' => floatval($community_fees),
            'currency' => $currency
        ));
    } else {
        wp_send_json_error(array('message' => 'Failed to retrieve total amount from XPAY.'));
    }
}


?>
