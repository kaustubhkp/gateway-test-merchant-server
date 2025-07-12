<?php
/*
 * Unified 3DS 2.x Authentication Flow (PHP equivalent of Kotlin steps)
 * Steps:
 * 1. Initiate Authentication
 * 2. Build 3DS2 Transaction (conceptual - no-op in PHP)
 * 3. Authenticate Payer
 * 4. Return response
 */

header('Content-Type: application/json');
include '_bootstrap.php';

try {
    // Step 0: Validate required query parameters
    $orderId = requiredQueryParam('orderId');
    $transactionId = requiredQueryParam('transactionId');
    $apiBasePath = "/order/{$orderId}/transaction/{$transactionId}";

    // Step 1: Read and validate JSON input for INITIATE_AUTHENTICATION
    $rawInput = file_get_contents('php://input');
    $initPayload = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON in request body']);
        exit;
    }

    if (
        !isset($initPayload['apiOperation']) ||
        strtoupper($initPayload['apiOperation']) !== 'INITIATE_AUTHENTICATION'
    ) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or missing apiOperation: expected INITIATE_AUTHENTICATION']);
        exit;
    }

    // === 1. Initiate Authentication ===
    error_log("Step 1: Initiate Authentication");

    $initiateResponse = proxyCall($apiBasePath, $initPayload, 'PUT');

    error_log("DEBUG: Full initiateResponse :: " . json_encode($initiateResponse));

    // Attempt fallback to root if 'gatewayResponse' is not found
    $iaData = $initiateResponse['gatewayResponse'] ?? $initiateResponse;

    error_log("DEBUG: gatewayResponse used as iaData :: " . json_encode($iaData));

    if (!$iaData) {
        echo json_encode([
            'step' => 'INITIATE_AUTHENTICATION',
            'message' => 'No authentication data returned, proceeding without 3DS',
            'initiateResult' => $initiateResponse
        ]);
        exit;
    }

    // Extract session ID from response (fallback to request)
    $sessionId = $iaData['session']['id'] ?? ($initPayload['session']['id'] ?? null);
    if (!$sessionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing session ID']);
        exit;
    }

    // Extract 3DSecureId from query param or request body
    $threeDSecureId = $_GET['3DSecureId'] ?? ($initPayload['3DSecureId'] ?? null);
    if (!$threeDSecureId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing 3DSecureId']);
        exit;
    }

    // === 2. Build 3DS Transaction ===
    error_log("Step 2: Build 3DS2 Transaction (noop)");

    // === 3. Authenticate Payer ===
    error_log("Step 3: Authenticate Payer");

    $authPayload = [
        "apiOperation" => "AUTHENTICATE_PAYER",
        'authentication' => [
            'redirectResponseUrl' => 'https://francophone-leaf-52430-c8565a556f27.herokuapp.com/?orderId='
                . $orderId . '&transactionId=' . $transactionId . '&3DSecureId=' . $threeDSecureId
        ],
        "device" => [
            "browser" => "MOZILLA",
            "ipAddress" => "127.0.0.1",
            "browserDetails" => [
                "3DSecureChallengeWindowSize" => "FULL_SCREEN",
                "acceptHeaders" => "application/json",
                "colorDepth" => 24,
                "javaEnabled" => true,
                "language" => "en-US",
                "screenHeight" => 640,
                "screenWidth" => 480,
                "timeZone" => 273
            ]
        ],
        "session" => [
            "id" => $sessionId
        ],
        "order" => [
            "amount" => 1.0,
            "currency" => "SAR"
        ]
    ];

    $authenticateResponse = proxyCall($apiBasePath, $authPayload, 'POST');
    $apData = $authenticateResponse['gatewayResponse'] ?? null;

    if (!$apData) {
        echo json_encode([
            'step' => 'AUTHENTICATE_PAYER',
            'message' => 'Frictionless flow detected, no challenge required',
            'initiateResult' => $initiateResponse,
            'authenticateResult' => $authenticateResponse
        ]);
        exit;
    }

    // === 4. Return full flow data ===
    echo json_encode([
        'step' => 'CHALLENGE_OR_COMPLETION',
        'initiateResult' => $initiateResponse,
        'authenticateResult' => $authenticateResponse
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("EXCEPTION: " . $e->getMessage());
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
