<?php

/*
 * Copyright (c) 2016 Mastercard
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr'); // ✅ THIS sends logs to Heroku

// pull environment vars
$merchantId = getenv('GATEWAY_MERCHANT_ID');
$password = getenv('GATEWAY_API_PASSWORD');
$region = getenv('GATEWAY_REGION');
$apiVersion = getenv('GATEWAY_API_VERSION');

// merchant id must be TEST
$merchantIdPrefix = substr($merchantId, 0, 4);
// if (strcasecmp($merchantIdPrefix, "test") != 0) {
//     error(500, 'Only TEST merchant IDs should be used with this software');
// }

// get regional url prefix
$prefix = 'mtf';
if (strcasecmp($region, "ASIA_PACIFIC") == 0) {
    $prefix = 'ap';
} else if (strcasecmp($region, "EUROPE") == 0) {
    $prefix = 'eu';
} else if (strcasecmp($region, "NORTH_AMERICA") == 0) {
    $prefix = 'na';
} else if (strcasecmp($region, "INDIA") == 0) {
    $prefix = 'in';
} else if (strcasecmp($region, "CHINA") == 0) {
    $prefix = 'cn';
} else if (strcasecmp($region, "MTF") == 0) {
    $prefix = 'mtf';
} else if (strcasecmp($region, "QA01") == 0) {
    $prefix = 'qa01';
} else if (strcasecmp($region, "QA02") == 0) {
    $prefix = 'qa02';
} else if (strcasecmp($region, "QA03") == 0) {
    $prefix = 'qa03';
} else if (strcasecmp($region, "QA04") == 0) {
    $prefix = 'qa04';
} else if (strcasecmp($region, "QA05") == 0) {
    $prefix = 'qa05';
} else if (strcasecmp($region, "QA06") == 0) {
    $prefix = 'qa06';
} else if (strcasecmp($region, "PEAT") == 0) {
    $prefix = 'perf';
} else {
    error(500, "Invalid region provided. Valid values include ASIA_PACIFIC, EUROPE, NORTH_AMERICA, INDIA, MTF");
}

// validate apiVersion is above minimum
if (intval($apiVersion) < 39) {
    error(500, "API Version must be >= 39");
}

// build api endpoint url
$gatewayUrl = "https://${prefix}.gateway.mastercard.com/api/rest/version/${apiVersion}/merchant/${merchantId}";

// parse query string
$query = array();
parse_str($_SERVER['QUERY_STRING'], $query);

// build auth headers
$headers = array(
    'Content-type: application/json',
    'Authorization: Basic ' . base64_encode("merchant.$merchantId:$password")
);

// construct page url
$pageUrl = "https://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];

function intercept($method) {
    return strcasecmp($_SERVER['REQUEST_METHOD'], $method) == 0;
}

function doRequest($url, $method, $data = null, $headers = null) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    if (!empty($data)) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    if (!empty($headers)) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}

function doRedirect($url) {
    header("Location: " . $url);
    exit;
}

function error($code, $message) {
    http_response_code($code);
    print_r($message);
    exit;
}

function requiredQueryParam($param) {
    global $query;

    if (!array_key_exists($param, $query) || empty($query[$param])) {
        error(400, 'Missing required query param: ' . $param);
    }

    return $query[$param];
}

function getJsonPayload() {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        json_decode($input);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error(400, 'Could not parse json payload');
        }
    }

    return $input;
}

function decodeResponse($response) {
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error(400, 'Could not decode json response from gateway');
    }

    return $decoded;
}

function outputJsonResponse($response) {
    global $apiVersion;

    header('Content-Type: application/json');

    $decoded = decodeResponse($response);

    $wrapped = array(
        'apiVersion' => $apiVersion,
        'gatewayResponse' => $decoded
    );

    print_r(json_encode($decoded));
    exit;
}

function proxyCall($path, $payload = null, $method = null) {
    global $headers, $gatewayUrl;

    error_log("=== proxyCall invoked ===");

    // Determine HTTP method
    $httpMethod = $method ?? $_SERVER['REQUEST_METHOD'];

    // Determine payload
    if ($payload === null) {
        $payload = getJsonPayload();
    }

    // Log the request safely
    error_log("URL Path: " . $path);
    error_log("Gateway URL: " . $gatewayUrl);
    error_log("Method: " . $httpMethod);
    error_log("Payload: " . (is_string($payload) ? $payload : json_encode($payload)));
    error_log("Headers: " . json_encode($headers));

    // Decode payload (if string), or use directly if already array
    $decodedPayload = is_array($payload) ? $payload : json_decode($payload, true);

    $isInitiateAuth = isset($decodedPayload['apiOperation']) &&
                      strtoupper($decodedPayload['apiOperation']) === 'INITIATE_AUTHENTICATION';

    // Ensure payload is a string before sending to doRequest
    $jsonPayload = is_string($payload) ? $payload : json_encode($payload);

    // Perform gateway request
    $response = doRequest($gatewayUrl . $path, $httpMethod, $jsonPayload, $headers);
    error_log("Response: " . $response);

    if ($isInitiateAuth) {
        // do NOT exit, return response for further steps
        return decodeResponse($response);
    }

    // default: output and exit
    outputJsonResponse($response);
}


