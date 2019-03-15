<?php

/**
 *  This function is used to query Dinahosting's API in order to extract creation and renewal prices. The API key can be obtained throught Dinahosting's support, once you've created a user account. Therefore, you have
 *      to request an API key and fill such information in this function
 *
 * @param $action Options: "create" or "renew"
 * @param string $tld Default value is 'eus' but can be updated to the TLD of your interest
 * @param string $username Default value is empty. You can either fill it with your username or provide such information when calling the function
 * @param string $password Default value is empty. You can either fill it with your password or provide such information when calling the function
 * @param string $currency Default value is 'EUR'. It can be changed to whichever currency you'd like
 * @param int $period Marks the duration of the create/renewal for which we want to know the price of. Default value is 1.
 */

// NOTE: Jsonrpc request will receive a Jsonrpc response.
function dinahostingPrice($action, $tld = 'eus', $username = '', $password = '', $currency = 'EUR', $period = 1)
{
    $urlApi = 'https://dinahosting.com/special/api.php';

    $request = array(
        'method' => 'Billing_Price_Domain',
        'params' => array('tld' => $tld,
            'period' => $period,
            'whoisProtection' => false,
            'currency' => $currency,
            'action' => $action,
        ),
    );
    $request = json_encode($request);

    $opts = array('http' => array(
        'method' => 'POST',
        'header' => 'Content-type: application/json' . "\r\n" .
            sprintf("Authorization: Basic %s\r\n", base64_encode($username . ':' . $password)) . "\r\n",
        'content' => $request
    ));

    $streamContext = stream_context_create($opts);

    if ($fp = fopen($urlApi, 'r', false, $streamContext)) {
        $response = '';

        while ($row = fgets($fp)) {
            $response .= trim($row) . "\n";
        }

        fclose($fp);

        return json_decode($response, true);

    } else {
        // error connecting or autentication error
        //stream_context_set_default ( $opts );
        $headers = get_headers($urlApi);
        return "Error: " . $headers;
    }
}
