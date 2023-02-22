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
?>