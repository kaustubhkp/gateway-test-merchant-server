<?php

/*
 * Copyright (c) 2016 Mastercard
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * http://www.apache.org/licenses/LICENSE-2.0
 */

include '_bootstrap.php';

if (intercept('POST')) {
    error_log("=== proxyCall invoked ===");

    $orderId = $_GET['order'] ?? null;
    $transactionId = $_GET['transaction'] ?? null;

    if (!$orderId || !$transactionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order or transaction ID']);
        exit;
    }

    $response = doRequest(
        $gatewayUrl . "/order/$orderId/transaction/$transactionId",
        'GET',
        null,
        $headers
    );

    header('Content-Type: application/json');

    // log the response
    error_log("=== proxyCall response ===");
    error_log($response);

    // build mobile redirect with full response payload as acsResult
    doRedirect("gatewaysdk://3dsecure?acsResult=" . urlencode($response));

}

// Only show HTML if NOT redirected
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css"
          integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M"
          crossorigin="anonymous">
    <style>
        body { padding: 2rem; }
    </style>
</head>
<body>
    <h1>3DSecure - Transaction Result</h1>
    <p>This script receives <strong>order</strong> and <strong>transaction</strong> as query params, directly calls Mastercard, <br/>
    and redirects to your app with the 3DS status result.</p>
</body>
</html>