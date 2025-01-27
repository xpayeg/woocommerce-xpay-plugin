<?php



function httpPost($url, $data, $api_key, $debug = 'no')
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
	curl_setopt( $curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 
												'User-Agent:XPay',
												'x-api-key: '.$api_key,
											)
				);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    curl_close($curl);
	if($debug == 'yes') {
		jsprint($response);
	}
    return $response;
}

function httpGet($url, $api_key, $debug = 'no')
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
    if($debug == 'yes') {
		jsprint($response);
	}
    return $response;
}

function jsprint($output, $is_alert=true, $with_script_tags = true) {
    if($is_alert) {
		$js_code = 'alert(' . json_encode($output, JSON_HEX_TAG) . ');';
	}
	else {
	$js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) . ');';
	}
    if ($with_script_tags) {
        $js_code = '<script>' . $js_code . '</script>';
    }
    echo $js_code;
}


add_action('wp_ajax_fetch_installment_plans', 'fetch_installment_plans');
add_action('wp_ajax_nopriv_fetch_installment_plans', 'fetch_installment_plans');

function fetch_installment_plans() {
     $amount = isset($_POST['amount']) ? sanitize_text_field($_POST['amount']) : null; 
    $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : null; 

    
    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : null; 
    $selected_payment_method = "installment";
    $community_id = isset($_POST['community_id']) ? sanitize_text_field($_POST['community_id']) : null; 
    $debug = true;
    $payload = json_encode(array(
        'amount' => $amount,
        'selected_payment_method' => $selected_payment_method,
        'community_id' => $community_id,
    ));
    $resp = httpPost($url, $payload, $api_key, FALSE);
    $decoded_resp = json_decode($resp, TRUE);
    
    echo json_encode($resp);
   

    wp_die();
}

?>
