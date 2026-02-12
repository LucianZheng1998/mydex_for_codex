<?php
/**
 * Convert Python script to PHP (curl)
 * Requires: PHP 7+ with cURL enabled
 */

declare(strict_types=1);

// Constants
const MYDEX_PDS_PATH      = "https://sbx-api.mydex.org";
const MEMBER_UID          = "1234";
const MEMBER_KEY          = "ABCDEFGHIJKLMNOP123456789";
const CONNECTION_ID       = "1234-45678";

const OAUTH_TOKEN_ENDPOINT = "https://sbx-op.mydexid.org/oauth2/token";
const OAUTH_CLIENT_ID      = "abcd1234-abcd-1234-abcd-123456abcdef";
const OAUTH_CLIENT_SECRET  = "CHANGEME";
const OAUTH_SCOPE          = "mydex:pdx post:/api/pds/add-measurements";

/**
 * Basic HTTP request via cURL.
 *
 * @param string $method  HTTP method
 * @param string $url     Full URL
 * @param array  $headers Assoc array of headers (name => value)
 * @param array  $query   Assoc array of query params
 * @param string|null $body Raw request body (already encoded)
 * @return array [status, body, response_headers]
 * @throws RuntimeException on HTTP errors or transport errors
 */
function http_request(string $method, string $url, array $headers = [], array $query = [], ?string $body = null): array
{
    if (!empty($query)) {
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $url .= (strpos($url, '?') === false ? '?' : '&') . $queryString;
    }

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException("Failed to initialize cURL");
    }

    $headerLines = [];
    foreach ($headers as $k => $v) {
        $headerLines[] = $k . ': ' . $v;
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headerLines,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 60,
    ]);

    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }

    $resp = curl_exec($curl);
    if ($resp === false) {
        $err = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException("cURL error: " . $err);
    }

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    $respHeadersRaw = substr($resp, 0, $headerSize);
    $respBody = substr($resp, $headerSize);

    if ($status < 200 || $status >= 300) {
        // Include body in error to help debugging
        throw new RuntimeException("HTTP $status: $respBody");
    }

    return [$status, $respBody, $respHeadersRaw];
}

/**
 * Get an OAuth2 access token (Client Credentials).
 *
 * Mirrors the Python behavior: URL-encodes client id/secret before base64.
 * @return string access_token
 */
function get_oauth2_access_token(): string
{
    // URL-encode client id/secret, then base64 "<id>:<secret>"
    $clientIdEnc = rawurlencode(OAUTH_CLIENT_ID);
    $clientSecretEnc = rawurlencode(OAUTH_CLIENT_SECRET);
    $basicAuth = base64_encode($clientIdEnc . ':' . $clientSecretEnc);

    $headers = [
        'Content-Type'  => 'application/x-www-form-urlencoded',
        'Authorization' => 'Basic ' . $basicAuth,
    ];

    $postData = http_build_query([
        'grant_type' => 'client_credentials',
        'scope'      => OAUTH_SCOPE,
    ], '', '&', PHP_QUERY_RFC3986);

    [, $body, ] = http_request('POST', OAUTH_TOKEN_ENDPOINT, $headers, [], $postData);

    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['access_token'])) {
        throw new RuntimeException("OAuth response missing access_token: " . $body);
    }

    return $json['access_token'];
}

/**
 * Add measurement data.
 */
function add_measurements(): void
{
    $requestData = [[
        "source_device_type"        => "Mobile",
        "source_name"               => "Apple Health Kit",
        "measurement_type"          => "Weight",
        "measurement_timestamp_start" => 1648633780,
        "measurement_timestamp_end"   => 1648633780,
        "measurement_value"         => 175.8,
    ]];

    $queryParams = [
        'uid'   => MEMBER_UID,
        'con_id'=> CONNECTION_ID,
    ];

    $oauthAccessToken = get_oauth2_access_token();

    $url = rtrim(MYDEX_PDS_PATH, '/') . '/api/pds/add-measurements';
    $headers = [
        'Authorization'    => 'Bearer ' . $oauthAccessToken,
        'Content-Type'     => 'application/json',
        'Connection-Token' => MEMBER_KEY,
    ];

    $body = json_encode($requestData, JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        throw new RuntimeException("Failed to JSON-encode request data");
    }

    [, $respBody, ] = http_request('POST', $url, $headers, $queryParams, $body);

    // Pretty-print JSON if possible
    $decoded = json_decode($respBody, true);
    if ($decoded !== null) {
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo $respBody . PHP_EOL;
    }
}

try {
    add_measurements();
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

