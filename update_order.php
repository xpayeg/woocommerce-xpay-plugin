<?php 

define( 'WP_USE_THEMES', false ); // Don't load theme support functionality
require( '../../../wp-load.php' );


$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, TRUE); //convert JSON into array

$transaction_id = $data["transaction_id"];
$posts = $wpdb->get_results("SELECT * FROM $wpdb->postmeta
WHERE meta_key = 'xpay_transaction_id' AND  meta_value = '$transaction_id' LIMIT 1", ARRAY_A);
$order = wc_get_order( $posts[0]["post_id"] );

$order->update_status( 'on-hold', __( 'Awaiting approval', 'wc-gateway-xpay' ) );
echo "status updated successfully";

