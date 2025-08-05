<?php

include '_bootstrap.php';

if (intercept('POST')) {
    $orderId = $_GET['order'] ?? null;
    $transactionId = $_GET['transaction'] ?? null;

    try {
        // Step 1: Retrieve transaction
        $transactionUrl = $gatewayUrl . "/merchant/{$merchantId}/order/{$orderId}/transaction/{$transactionId}";
        $transactionResponse = doRequest($transactionUrl, 'GET', null, $headers);

        // Step 2: Parse NVP transaction data
        $transactionData = [];
        if (!empty($transactionResponse) && strpos($transactionResponse, '=') !== false) {
            parse_str(str_replace("\n", "&", trim($transactionResponse)), $transactionData);
        }

        $transactionStatus = $transactionData['transaction.status'] ?? 'UNKNOWN';
        $amount = $transactionData['transaction.amount'] ?? '';
        $currency = $transactionData['transaction.currency'] ?? '';
        $authCode = $transactionData['transaction.authorizationCode'] ?? '';

        // Step 3: Redirect to mobile app
        $params = [
            'status' => $transactionStatus, // Using transactionStatus instead of summaryStatus
            'txnStatus' => $transactionStatus,
            'amount' => $amount,
            'currency' => $currency,
            'authCode' => $authCode,
            'orderId' => $orderId,
            'transactionId' => $transactionId
        ];

        $redirectUrl = "gatewaysdk://3dsecure?" . http_build_query($params);
        doRedirect($redirectUrl);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>