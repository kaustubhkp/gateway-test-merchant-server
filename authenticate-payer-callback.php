<?php

include '_bootstrap.php';

if (intercept('POST')) {
    $orderId = $_GET['order'] ?? null;
    $transactionId = $_GET['transaction'] ?? null;

    try {
        // Step 1: Retrieve transaction
        $transactionUrl = $gatewayUrl . "/order/{$orderId}/transaction/{$transactionId}";
        $transactionResponse = doRequest($transactionUrl, 'GET', null, $headers);

        // Step 2: Redirect
        $redirectUrl = "gatewaysdk://3dsecure?paymentResult=" . $urlencode($transactionResponse);
        doRedirect($redirectUrl);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>