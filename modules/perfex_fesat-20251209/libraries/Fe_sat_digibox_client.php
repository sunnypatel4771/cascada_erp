<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Fe_sat_digibox_client
{
    protected $baseUrl;
    protected $username;
    protected $password;
    protected $apiKey;
    protected $sandbox;

    public function __construct()
    {
        $this->baseUrl  = rtrim((string) get_option('perfex_fesat_base_url'), '/');

        $username = get_option('perfex_fesat_username');
        $password = get_option('perfex_fesat_password');
        $apiKey   = get_option('perfex_fesat_api_key');

        $this->username = $username ? app_decrypt($username) : '';
        $this->password = $password ? app_decrypt($password) : '';
        $this->apiKey   = $apiKey ? app_decrypt($apiKey) : '';
        $this->sandbox  = get_option('perfex_fesat_sandbox_mode') === '1';
    }

    public function timbrar(string $xml, array $meta = []): array
    {
        if ($this->baseUrl === '') {
            return [
                'success' => false,
                'message' => _l('fe_sat_error_missing_base_url'),
                'endpoint'=> '',
            ];
        }

        $endpoint = $this->baseUrl . '/timbrar';
        $payload  = [
            'xml'   => base64_encode($xml),
            'meta'  => $meta,
            'sandbox' => $this->sandbox,
        ];

        $response = $this->request('POST', $endpoint, $payload);
        $body     = $response['body'];
        $decoded  = json_decode($body, true);

        if (is_array($decoded) && isset($decoded['uuid'])) {
            return [
                'success'        => true,
                'uuid'           => $decoded['uuid'],
                'xml'            => $this->decodeMaybe($decoded['xml'] ?? ''),
                'pdf'            => $decoded['pdf'] ?? null,
                'message'        => $decoded['message'] ?? _l('fe_sat_timbrado_success'),
                'endpoint'       => $endpoint,
                'http_code'      => $response['http_code'],
                'body_excerpt'   => substr($body, 0, 65535),
                'request_excerpt'=> json_encode($payload),
            ];
        }

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            return [
                'success'        => true,
                'uuid'           => $decoded['uuid'] ?? ($decoded['data']['uuid'] ?? uniqid('digibox_', true)),
                'xml'            => $this->decodeMaybe($decoded['xml'] ?? $xml),
                'pdf'            => $decoded['pdf'] ?? null,
                'message'        => $decoded['message'] ?? _l('fe_sat_timbrado_success'),
                'endpoint'       => $endpoint,
                'http_code'      => $response['http_code'],
                'body_excerpt'   => substr($body, 0, 65535),
                'request_excerpt'=> json_encode($payload),
            ];
        }

        $message = $decoded['message'] ?? $body ?? _l('fe_sat_timbrado_failed');
        return [
            'success'        => false,
            'message'        => is_string($message) ? $message : json_encode($message),
            'endpoint'       => $endpoint,
            'http_code'      => $response['http_code'],
            'body_excerpt'   => substr($body, 0, 65535),
            'request_excerpt'=> json_encode($payload),
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

        $endpoint = $this->baseUrl . '/ping';
        $response = $this->request('GET', $endpoint);
        $success  = $response['http_code'] >= 200 && $response['http_code'] < 300;
        $message  = $success ? _l('fe_sat_ping_success') : _l('fe_sat_ping_failed');

        return [
            'success'   => $success,
            'message'   => $message,
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

        $authHeader = $this->buildAuthHeader();
        if ($authHeader) {
            $headers[] = $authHeader;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $body      = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error     = curl_error($ch);
        curl_close($ch);

        if ($body === false || $error) {
            return [
                'body'      => $error,
                'http_code' => $httpCode ?: 0,
            ];
        }

        return [
            'body'      => $body,
            'http_code' => $httpCode,
        ];
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
}
