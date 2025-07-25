<?php
define( 'WP_USE_THEMES', false );
require( '../../../wp-load.php' );

header('Content-Type: application/json');

// Get and decode the JSON input
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

$transaction_id = isset($data["transaction_id"]) ? trim($data["transaction_id"]) : null;
$transaction_status = isset($data["transaction_status"]) ? $data["transaction_status"] : null;

// Handle missing transaction_id
if (!$transaction_id) {
    wp_send_json_error([
        'message' => 'Missing transaction_id in payload',
        'received_payload' => $data
    ]);
}

global $wpdb;

// Use a safe query to find post ID by transaction ID
$posts = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        'xpay_transaction_id',
        $transaction_id
    ),
    ARRAY_A
);

// If transaction ID not found
if (empty($posts)) {
    wp_send_json_error([
        'message' => 'Transaction ID not found in postmeta',
        'transaction_id' => $transaction_id
    ]);
}

$post_id = $posts[0]["post_id"];
$order = wc_get_order($post_id);

// If order not found for the post ID
if (!$order) {
    wp_send_json_error([
        'message' => 'Order not found for given transaction ID',
        'transaction_id' => $transaction_id,
        'post_id' => $post_id
    ]);
}

// Update order status based on transaction result
if ($transaction_status === "SUCCESSFUL") {
    $order->update_status('completed', __('Awaiting approval', 'wc-gateway-xpay'));
    wp_send_json_success([
        'message' => 'Order updated to completed',
        'order_id' => $order->get_id()
    ]);
} elseif ($transaction_status === "FAILED") {
    $order->update_status('failed', __('Transaction failed', 'wc-gateway-xpay'));
    wp_send_json_success([
        'message' => 'Order updated to failed',
        'order_id' => $order->get_id()
    ]);
} else {
    wp_send_json_error([
        'message' => 'Unknown transaction status',
        'transaction_status' => $transaction_status,
        'transaction_id' => $transaction_id,
        'order_id' => $order->get_id()
    ]);
}
