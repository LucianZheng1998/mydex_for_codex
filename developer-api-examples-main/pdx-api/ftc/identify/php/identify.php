<?php
// Constants
define('MYDEX_PDS_PATH', 'https://sbx-api.mydex.org');
define('CONNECTION_NID', '45678');
define('CONNECTION_TOKEN', 'abcdefghijklmnopqrstuvwxyz123456789');

define('OAUTH_TOKEN_ENDPOINT', 'https://sbx-op.mydexid.org/oauth2/token');
define('OAUTH_CLIENT_ID', 'abcd1234-abcd-1234-abcd-123456abcdef');
define('OAUTH_CLIENT_SECRET', 'CHANGEME');
define('OAUTH_SCOPE', 'mydex:pdx');

function get_oauth2_access_token() {
    // Build POST data
    $postData = http_build_query([
        'grant_type' => 'client_credentials',
        'scope' => OAUTH_SCOPE,
    ]);

    // Encode credentials
    $clientIdEnc = rawurlencode(OAUTH_CLIENT_ID);
    $clientSecretEnc = rawurlencode(OAUTH_CLIENT_SECRET);
    $basicAuth = base64_encode("$clientIdEnc:$clientSecretEnc");

    // Set headers
    $headers = [
        "Authorization: Basic $basicAuth",
        "Content-Type: application/x-www-form-urlencoded",
    ];

    // Initialize cURL
    $ch = curl_init(OAUTH_TOKEN_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("OAuth token request failed with status $httpCode: $response");
    }

    $json = json_decode($response, true);
    return $json['access_token'] ?? null;
}

function identify() {
    // Build request data
    $requestData = [
        'connection_nid' => CONNECTION_NID,
        'connection_token_hash' => hash('sha512', CONNECTION_TOKEN),
        'return_to' => 'https://example.com',
        // If you need to link the Member's MydexID and PDS to your own member or data object,
        // send 'linking_token' as a parameter which represents a unique attribute
        // of your own member object in your backend. This will be sent back
        // in the callback to your API along with the MydexID
    ];

    $oauthAccessToken = get_oauth2_access_token();

    $headers = [
        "Authorization: Bearer $oauthAccessToken",
        "Content-Type: application/json",
    ];

    $ch = curl_init(MYDEX_PDS_PATH . '/identify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("Identify request failed with status $httpCode: $response");
    }

    $json = json_decode($response, true);
    print_r($json);
}

// Run the script
identify();
