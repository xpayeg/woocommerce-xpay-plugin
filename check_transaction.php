<?php

define( 'WP_USE_THEMES', false ); // Don't load theme support functionality
require( '../../../wp-load.php' );

require( 'utils.php' );


$uuid = $_REQUEST["trn_uuid"];
$community_id  = $_REQUEST["community_id"];
$order_id  = $_REQUEST["order_id"];
$wc_settings = new WC_Gateway_Xpay;
$debug = $wc_settings->get_option("debug");
$url = $wc_settings->get_option("iframe_base_url"). "/api/v1/communities/".$community_id."/transactions/".$uuid."/";
$resp = httpGet($url, $wc_settings->get_option("payment_api_key"), $debug);
$resp = json_decode($resp, TRUE);
$order = wc_get_order( $order_id );
if ($resp["data"]["status"] == "SUCCESSFUL"){
    $order->update_status( 'on-hold', __( 'Awaiting approval', 'wc-gateway-xpay' ) );
}
echo ($resp["data"]["status"]);

