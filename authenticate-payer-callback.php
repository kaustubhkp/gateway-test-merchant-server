<?php

include '_bootstrap.php';

if (intercept('POST')) {
    $orderId = $_GET['order'] ?? null;
    $transactionId = $_GET['transaction'] ?? null;

    try {
        // Step 1: Retrieve transaction
        $transactionUrl = $gatewayUrl . "/order/{$orderId}/transaction/{$transactionId}";
        $transactionResponse = doRequest($transactionUrl, 'GET', null, $headers);

        // Step 2: Decode the JSON string (response is a JSON string)
        $transactionData = [];
        $transactionData = json_decode($transactionResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from gateway");
        }

        // Step 3: Extract necessary fields
        $result = $transactionData['result'];
        $transactionStatus = $transactionData['transaction']['authenticationStatus'] ?? 'UNKNOWN';
        $amount = $transactionData['transaction']['amount'] ?? '';
        $currency = $transactionData['transaction']['currency'] ?? '';
        $authCode = $transactionData['transaction']['id'] ?? '';

        // Step 4: Build param list
        $params = [
            'result' => $result,
            'txnStatus' => $transactionStatus,
            'amount' => $amount,
            'currency' => $currency,
            'authCode' => $authCode,
            'orderId' => $orderId,
            'transactionId' => $transactionId
        ];

        // $encodedParams = urlencode(http_build_query($params));
        $encodedParams = urlencode($transactionResponse);

    // Step 6: Redirect
        $redirectUrl = "gatewaysdk://3dsecure?paymentResult=" . $encodedParams;
        // $redirectUrl = "gatewaysdk://3dsecure?" . http_build_query($params);
        doRedirect($redirectUrl);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>