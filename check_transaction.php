<?php

define( 'WP_USE_THEMES', false ); // Don't load theme support functionality
require( '../../../wp-load.php' );


$uuid = $_REQUEST["trn_uuid"];
$community_id  = $_REQUEST["community_id"];
$order_id  = $_REQUEST["order_id"];
$wc_settings = new WC_Gateway_Xpay;

$url = $wc_settings->get_option("iframe_base_url"). "/api/v1/communities/".$community_id."/transactions/".$uuid."/";
$resp = httpGet($url, $wc_settings->get_option("payment_api_key"));
$resp = json_decode($resp, TRUE);
$order = wc_get_order( $order_id );
if ($resp["data"]["status"] == "SUCCESSFUL"){
    $order->update_status( 'on-hold', __( 'Awaiting approval', 'wc-gateway-xpay' ) );
}
echo ($resp["data"]["status"]);


function httpGet($url, $api_key)
{
    $headers = array(
        'x-api-key: '.$api_key,
        'Content-Type:application/json'
   );
    $curl = curl_init($url);
	curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);


    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
