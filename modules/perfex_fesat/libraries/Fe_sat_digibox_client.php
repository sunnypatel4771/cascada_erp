<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Fe_sat_digibox_client
{
    protected $baseUrl;
    protected $username;
    protected $password;
    protected $apiKey;
    protected $sandbox;
    protected $apiCallLog = []; // Track all API calls for debugging

    public function __construct()
    {
        $CI = &get_instance();
        $CI->load->library('encryption');

        $url = (string) get_option('perfex_fesat_base_url');
        $parsed = parse_url($url);
        if (isset($parsed['scheme']) && isset($parsed['host'])) {
            $this->baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
        } else {
            $this->baseUrl = rtrim($url, '/');
        }

        // HARDCODED CREDENTIALS FOR TESTING - TODO: Move to config or database
        $this->username = 'demo2';
        $this->password = '123456789';
        $this->apiKey = '';

        // Original code - uncomment when credentials system is working
        // $username = get_option('perfex_fesat_username');
        // $password = get_option('perfex_fesat_password');
        // $apiKey   = get_option('perfex_fesat_api_key');
        // $this->username = $username ?: '';
        // $this->password = $password ?: '';
        // $this->apiKey   = $apiKey ?: '';

        $this->sandbox = get_option('perfex_fesat_sandbox_mode') === '1';
    }

    public function timbrar(string $xml, array $meta = []): array
    {
        if ($this->baseUrl === '') {
            return [
                'success' => false,
                'message' => _l('fe_sat_error_missing_base_url'),
                'endpoint' => '',
            ];
        }

        // Step 1: Authenticate and get token
        $authResponse = $this->authenticate();
        if (!$authResponse['success']) {
            return [
                'success' => false,
                'message' => 'Authentication failed: ' . $authResponse['message'],
                'endpoint' => $this->baseUrl,
                'http_code' => $authResponse['http_code'] ?? 0,
                'body_excerpt' => $authResponse['body'] ?? '',
                'request_excerpt' => '',
            ];
        }

        $token = $authResponse['token'];

        // Step 2: Call timbrado v5 endpoint
        // DigiBox v5 returns: [0] => XML (string), [1] => PDF (base64 string)
        $endpoint = $this->baseUrl . '/apisellado/timbradoxml/v5';

        // NOTE: $this->baseUrl is now used dynamically.
        // Ensure perfex_fesat_base_url setting is correct (e.g. https://testtimbrado.digibox.com.mx or https://timbrado.digibox.com.mx)

        $response = $this->requestTimbrado('POST', $endpoint, $xml, $token);
        $body = $response['body'];
        $httpCode = $response['http_code'];

        // Log timbrado API call (for both success and error)
        $this->logApiCall([
            'type' => 'Timbrado v5',
            'method' => 'POST',
            'url' => $endpoint,
            'headers' => ['token: ' . substr($token, 0, 50) . '...', 'Content-Type: text/xml'],
            'request_body' => substr($xml, 0, 1000) . '... (XML truncated)',
            'response_code' => $httpCode,
            'response_body' => substr($body, 0, 1000) . '... (Response truncated)',
            'error' => null,
        ]);

        // Handle errors
        if ($httpCode >= 500) {
            $decoded = json_decode($body, true);
            $errorMessage = $decoded['ExceptionMessage'] ?? $decoded['Message'] ?? $body;
            return [
                'success' => false,
                'message' => 'DigiBox Error: ' . $errorMessage,
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'body_excerpt' => substr($body, 0, 65535),
                'request_excerpt' => substr($xml, 0, 500) . '...',
                'api_log' => $this->getApiCallLog(),
            ];
        }

        // Parse successful response
        // DigiBox v5 returns a JSON array: [0] => XML string, [1] => PDF base64
        $decoded = json_decode($body, true);

        if (!is_array($decoded) || count($decoded) < 2) {
            return [
                'success' => false,
                'message' => 'Invalid response format from DigiBox',
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'body_excerpt' => substr($body, 0, 65535),
                'request_excerpt' => substr($xml, 0, 500) . '...',
                'api_log' => $this->getApiCallLog(),
            ];
        }

        $stampedXml = $decoded[0] ?? '';
        $pdfBase64 = $decoded[1] ?? '';

        // Extract UUID from stamped XML
        $uuid = $this->extractUuidFromXml($stampedXml);

        return [
            'success' => true,
            'uuid' => $uuid,
            'xml' => $stampedXml,
            'pdf' => $pdfBase64,
            'message' => _l('fe_sat_timbrado_success'),
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'body_excerpt' => substr($body, 0, 65535),
            'request_excerpt' => substr($xml, 0, 500) . '...',
            'api_log' => $this->getApiCallLog(),
        ];
    }

    /**
     * Authenticate with DigiBox and get token
     */
    protected function authenticate(): array
    {
        if ($this->baseUrl === '') {
            return [
                'success' => false,
                'message' => 'Missing base URL',
                'http_code' => 0,
            ];
        }

        $endpoint = $this->baseUrl . '/api/autenticacion/autenticarbasico';
        $response = $this->request('POST', $endpoint, []);
        $httpCode = $response['http_code'];
        $body = $response['body'];

        // Log authentication API call
        $this->logApiCall([
            'type' => 'Authentication',
            'method' => 'POST',
            'url' => $endpoint,
            'headers' => ['usuario: ' . $this->username, 'password: ******', 'Accept: application/json'],
            'request_body' => 'N/A (empty POST)',
            'response_code' => $httpCode,
            'response_body' => substr($body, 0, 500) . (strlen($body) > 500 ? '... (Response truncated)' : ''),
            'error' => null,
        ]);

        if ($httpCode >= 200 && $httpCode < 300) {
            // Try to decode as JSON first
            $decoded = json_decode($body, true);

            if (is_array($decoded)) {
                // JSON response with token field
                $token = $decoded['token'] ?? $decoded['access_token'] ?? null;
                if ($token) {
                    return [
                        'success' => true,
                        'token' => $token,
                        'http_code' => $httpCode,
                    ];
                }
            } else {
                // Plain string token (DigiBox returns token directly)
                $token = trim($body);
                // Remove surrounding quotes if present
                $token = trim($token, '"\'');

                if (!empty($token) && strlen($token) > 20) {
                    return [
                        'success' => true,
                        'token' => $token,
                        'http_code' => $httpCode,
                    ];
                }
            }
        }

        // Authentication failed
        $decoded = json_decode($body, true);
        $errorMessage = $decoded['ExceptionMessage'] ?? $decoded['Message'] ?? $body;

        return [
            'success' => false,
            'message' => $errorMessage,
            'http_code' => $httpCode,
            'body' => $body,
        ];
    }

    public function ping(): array
    {
        if ($this->baseUrl === '') {
            return [
                'success' => false,
                'message' => _l('fe_sat_error_missing_base_url'),
            ];
        }

        // Debug: Check if credentials are set
        $debugInfo = '';
        if (empty($this->username) || empty($this->password)) {
            $debugInfo = ' [DEBUG: Missing credentials - Username: ' . (empty($this->username) ? 'empty' : 'set') . ', Password: ' . (empty($this->password) ? 'empty' : 'set') . ']';
        }

        // For DigiBox, we test the authentication endpoint with POST
        $endpoint = $this->baseUrl . '/api/autenticacion/autenticarbasico';
        $payload = [];

        // DigiBox authentication endpoint expects POST with credentials
        $response = $this->request('POST', $endpoint, $payload);
        $success = $response['http_code'] >= 200 && $response['http_code'] < 300;

        $httpCode = $response['http_code'] ?? 0;

        if (!$success) {
            $errorDetails = $response['body'] ?? 'No response';

            if ($httpCode === 0) {
                $message = _l('fe_sat_ping_failed') . ': ' . _l('fe_sat_connection_refused') . ' - ' . $errorDetails . $debugInfo;
            } else {
                // Extract just the ExceptionMessage if available
                $decoded = json_decode($errorDetails, true);
                if (isset($decoded['ExceptionMessage'])) {
                    $errorDetails = $decoded['ExceptionMessage'];
                }
                $message = _l('fe_sat_ping_failed') . ' (HTTP ' . $httpCode . '): ' . $errorDetails . $debugInfo;
            }
        } else {
            $decoded = json_decode($response['body'], true);
            if (is_array($decoded) && (isset($decoded['token']) || isset($decoded['access_token']))) {
                $message = _l('fe_sat_ping_success') . ' - Token: ' . substr($decoded['token'] ?? $decoded['access_token'], 0, 20) . '...';
            } else {
                // DigiBox returns plain string token
                $token = trim($response['body']);
                if (!empty($token) && strlen($token) > 20) {
                    $message = _l('fe_sat_ping_success') . ' - Token: ' . substr($token, 0, 20) . '...';
                } else {
                    $message = _l('fe_sat_ping_success') . ' (HTTP ' . $httpCode . ')';
                }
            }
        }

        return [
            'success' => $success,
            'message' => $message,
            'http_code' => $response['http_code'],
        ];
    }

    protected function request(string $method, string $url, array $payload = []): array
    {
        $ch = curl_init();
        $headers = [
            'Accept: application/json',
        ];

        if ($method === 'POST' || $method === 'PUT') {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        // DigiBox uses custom headers for authentication, not Basic Auth
        if ($this->username) {
            $headers[] = 'usuario: ' . $this->username;
        }
        if ($this->password) {
            $headers[] = 'password: ' . $this->password;
        }

        // Alternative: Basic Auth (commented out since DigiBox uses custom headers)
        // $authHeader = $this->buildAuthHeader();
        // if ($authHeader) {
        //     $headers[] = $authHeader;
        // }

        // SSL verification - disable for local development if needed
        $sslVerify = !empty(get_option('perfex_fesat_ssl_verify_disabled')) ? false : true;

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $error) {
            return [
                'body' => $error,
                'http_code' => $httpCode ?: 0,
            ];
        }

        return [
            'body' => $body,
            'http_code' => $httpCode,
        ];
    }

    /**
     * Make timbrado request with raw XML body and Token header
     */
    protected function requestTimbrado(string $method, string $url, string $xmlBody, string $token): array
    {
        $ch = curl_init();

        // Ensure token has no extra whitespace
        $token = trim($token);

        $headers = [
            'token: ' . $token,  // Changed to lowercase to match API docs
            'Content-Type: text/xml',
        ];

        // SSL verification - disable for local development if needed
        $sslVerify = !empty(get_option('perfex_fesat_ssl_verify_disabled')) ? false : true;

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60, // Timbrado may take longer
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $xmlBody, // Raw XML, not JSON
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $error) {
            return [
                'body' => $error,
                'http_code' => $httpCode ?: 0,
            ];
        }

        return [
            'body' => $body,
            'http_code' => $httpCode,
        ];
    }

    /**
     * Extract UUID from stamped XML
     */
    protected function extractUuidFromXml(string $xml): string
    {
        // Try to extract UUID from tfd:TimbreFiscalDigital attribute
        if (preg_match('/UUID="([^"]+)"/', $xml, $matches)) {
            return $matches[1];
        }

        // Fallback: try to parse as SimpleXML
        try {
            $xmlObj = @simplexml_load_string($xml);
            if ($xmlObj) {
                $namespaces = $xmlObj->getNamespaces(true);
                $complemento = $xmlObj->children()->Complemento ?? null;
                if ($complemento) {
                    foreach ($namespaces as $prefix => $ns) {
                        if (strpos($ns, 'TimbreFiscalDigital') !== false) {
                            $tfd = $complemento->children($ns);
                            if (isset($tfd->TimbreFiscalDigital)) {
                                $uuid = (string) $tfd->TimbreFiscalDigital->attributes()->UUID;
                                if ($uuid) {
                                    return $uuid;
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Continue to fallback
        }

        // Fallback: generate a unique ID if we can't extract UUID
        return uniqid('digibox_', true);
    }

    protected function buildAuthHeader(): ?string
    {
        if ($this->apiKey) {
            return 'Authorization: Bearer ' . $this->apiKey;
        }

        if ($this->username && $this->password) {
            return 'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password);
        }

        return null;
    }

    protected function decodeMaybe(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $decoded = base64_decode($value, true);
        return $decoded !== false ? $decoded : $value;
    }

    protected function logApiCall(array $callData): void
    {
        $this->apiCallLog[] = array_merge($callData, ['timestamp' => date('Y-m-d H:i:s')]);
    }

    public function getApiCallLog(): array
    {
        return $this->apiCallLog;
    }


    /**
     * Cancel CFDI invoice using DigiBox CancelarCSDV2 API (With CSD files)
     * This method requires sending CSD certificate files in the request
     *
     * @param string $uuid UUID of the invoice to cancel
     * @param string $rfcEmisor RFC of the issuer
     * @param string $motivo Cancellation reason (01, 02, 03, or 04)
     * @param string $folioSustitucion Replacement UUID (required only for motivo 01)
     * @param string $csdCerBase64 CSD certificate .cer file in base64
     * @param string $csdKeyBase64 CSD key .key file in base64
     * @param string $csdPassword CSD password in plain text
     * @return array Response with success status and message
     */
    public function cancelar(
        string $uuid,
        string $rfcEmisor,
        string $motivo,
        string $folioSustitucion = '',
        string $csdCerBase64 = '',
        string $csdKeyBase64 = '',
        string $csdPassword = ''
    ): array {
        // Validate motivo
        if (!in_array($motivo, ['01', '02', '03', '04'])) {
            return [
                'success' => false,
                'message' => 'Invalid motivo. Must be 01, 02, 03, or 04.',
                'api_log' => $this->getApiCallLog(),
            ];
        }

        // Validate folioSustitucion for motivo 01
        if ($motivo === '01' && empty($folioSustitucion)) {
            return [
                'success' => false,
                'message' => 'folioSustitucion is required when motivo is 01.',
                'api_log' => $this->getApiCallLog(),
            ];
        }

        // Step 1: Authenticate and get token
        $authResponse = $this->authenticate();
        if (!$authResponse['success']) {
            return [
                'success' => false,
                'message' => 'Authentication failed: ' . $authResponse['message'],
                'http_code' => $authResponse['http_code'] ?? 0,
                'api_log' => $this->getApiCallLog(),
            ];
        }

        $token = $authResponse['token'];

        // Step 2: Prepare cancellation endpoint
        $endpoint = $this->baseUrl . '/api/cancelacioncfdi/cancelarcsdv2';
        // Endpoint is now dynamic based on base URL setting

        // Step 3: Make cancellation request
        $response = $this->requestCancelacion(
            $endpoint,
            $token,
            $uuid,
            $rfcEmisor,
            $motivo,
            $folioSustitucion,
            $csdCerBase64,
            $csdKeyBase64,
            $csdPassword
        );

        $body = $response['body'];
        $httpCode = $response['http_code'];

        // Log cancellation API call (INCLUDE csdcer and csdkey for debugging)
        $this->logApiCall([
            'type' => 'Cancellation CSD v2',
            'method' => 'POST',
            'url' => $endpoint,
            'headers' => [
                'token: ' . substr($token, 0, 50) . '... (' . strlen($token) . ' chars)',
                'csdcer: ' . substr($csdCerBase64, 0, 50) . '... (' . strlen($csdCerBase64) . ' chars)',
                'csdkey: ' . substr($csdKeyBase64, 0, 50) . '... (' . strlen($csdKeyBase64) . ' chars)',
                'password: ' . str_repeat('*', strlen($csdPassword)),
                'rfcemisor: ' . $rfcEmisor,
                'uuids: ' . $uuid,
                'motivo: ' . $motivo,
                'foliosustitucion: ' . ($folioSustitucion ?: 'N/A'),
            ],
            'request_body' => 'Headers only (no body)',
            'response_code' => $httpCode,
            'response_body' => substr($body, 0, 2000) . (strlen($body) > 2000 ? '... (truncated)' : ''),
            'error' => null,
        ]);

        // Handle errors
        if ($httpCode >= 400) {
            $decoded = json_decode($body, true);
            $errorMessage = $decoded['ExceptionMessage'] ?? $decoded['Message'] ?? $body;

            return [
                'success' => false,
                'message' => 'DigiBox Cancellation Error: ' . $errorMessage,
                'http_code' => $httpCode,
                'endpoint' => $endpoint,
                'body_excerpt' => $body,
                'api_log' => $this->getApiCallLog(),
            ];
        }

        // Parse success response
        // Response contains XML with "acuse" nodes for each UUID
        return [
            'success' => true,
            'message' => 'Cancellation request sent successfully',
            'acuse_xml' => $body,
            'http_code' => $httpCode,
            'endpoint' => $endpoint,
            'api_log' => $this->getApiCallLog(),
        ];
    }

    /**
     * Make cancellation request with headers
     */
    protected function requestCancelacion(
        string $url,
        string $token,
        string $uuid,
        string $rfcEmisor,
        string $motivo,
        string $folioSustitucion,
        string $csdCerBase64,
        string $csdKeyBase64,
        string $csdPassword
    ): array {
        $ch = curl_init();

        // Ensure base64 strings have no whitespace/newlines (critical for HTTP headers)
        $csdCerBase64 = preg_replace('/\s+/', '', $csdCerBase64);
        $csdKeyBase64 = preg_replace('/\s+/', '', $csdKeyBase64);

        // URL-encode base64 strings as per DigiBox expert recommendation
        // JavaScript encodeURIComponent equivalent in PHP: rawurlencode()
        // This converts: + → %2B, / → %2F, = → %3D
        $csdCerBase64 = rawurlencode($csdCerBase64);
        $csdKeyBase64 = rawurlencode($csdKeyBase64);

        // Prepare headers according to DigiBox API documentation
        // CRITICAL: Each header must be SEPARATE (not concatenated)
        $headers = [
            'token: ' . trim($token),
            'csdcer: ' . $csdCerBase64,        // SEPARATE HEADER for certificate
            'csdkey: ' . $csdKeyBase64,        // SEPARATE HEADER for key
            'password: ' . trim($csdPassword),
            'rfcemisor: ' . strtoupper(trim($rfcEmisor)),
            'uuids: ' . trim($uuid),
            'motivo: ' . trim($motivo),
        ];

        // Add folioSustitucion only if provided (for motivo 01)
        if (!empty($folioSustitucion)) {
            $headers[] = 'foliosustitucion: ' . $folioSustitucion;
        }

        // SSL verification
        $sslVerify = !empty(get_option('perfex_fesat_ssl_verify_disabled')) ? false : true;

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => '', // Explicitly set empty body for POST
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($body === false || $error) {
            return [
                'body' => $error,
                'http_code' => $httpCode ?: 0,
            ];
        }

        return [
            'body' => $body,
            'http_code' => $httpCode,
        ];
    }

}
