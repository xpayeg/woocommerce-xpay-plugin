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

?>
