<?php

namespace KraEtimsSdk\Services;

use KraEtimsSdk\Exceptions\ApiException;
use KraEtimsSdk\Exceptions\AuthenticationException;

// ----------------- OSCU ENDPOINTS -----------------
abstract class BaseOClient
{
    private static array $endpoints = [
        // INITIALIZATION
        'selectInitOsdcInfo'       => '/selectInitOsdcInfo',

        // CODE LIST
        'selectCodeList'           => '/selectCodeList',

        // CUSTOMER
        'selectCustomer'            => '/selectCustomer', 

        // NOTICE
        'selectNoticeList'          => '/selectNoticeList',  

        // ITEM
        'selectItemClsList'         => '/selectItemClsList',
        'selectItemList'            => '/selectItemList', 
        'saveItem'              => '/saveItem',          
        'saveItemComposition'      => '/saveItemComposition',

        // BRANCH / CUSTOMER
        'selectBhfList'             => '/selectBhfList',    
        'saveBhfCustomer'           => '/saveBhfCustomer', 
        'saveBhfUser'           => '/saveBhfUser',    
        'saveBhfInsurance'      => '/saveBhfInsurance', 

        // IMPORTED ITEMS
        'selectImportItemList'      => '/selectImportItemList',
        'updateImportItem'      => '/updateImportItem', 

        // SALES / PURCHASES
        'saveTrnsSalesOsdc'       => '/saveTrnsSalesOsdc',     
        'selectTrnsPurchaseSalesList'     => '/selectTrnsPurchaseSalesList', 
        'insertTrnsPurchase'      => '/insertTrnsPurchase',    

        // STOCK
        'selectStockMoveList'             => '/selectStockMoveList', 
        'insertStockIO'           => '/insertStockIO',    
        'saveStockMaster'       => '/saveStockMaster',   
    ];

    public function __construct(
        protected array $config,
        protected AuthOClient $auth
    ) {}

    protected function baseUrl(): string
    {
        if (($this->config['env'] ?? null) === 'sbx') {
            $url = 'https://etims-api-sbx.kra.go.ke/etims-api';
        } else {
            $url = 'https://etims-api.kra.go.ke/etims-api';
        }

        return rtrim(trim($url), '/');
    }

    protected function timeout(): int
    {
        return $this->config['http']['timeout'] ?? 30;
    }

    protected function endpoint(string $key): string
    {
        if (str_starts_with($key, '/')) {
            throw new ApiException(
                "Endpoint key expected, path given [$key]. Pass endpoint keys only.",
                500
            );
        }

        if (!isset(self::$endpoints[$key])) {
            throw new ApiException("Endpoint [$key] not configured", 500);
        }

        return self::$endpoints[$key];
    }

    protected function get(string $endpointKey, array $query = []): array
    {
        return $this->send('GET', $endpointKey, $query);
    }

    protected function post(string $endpointKey, array $body = []): array
    {
        return $this->send('POST', $endpointKey, $body);
    }

    protected function send(string $method, string $endpointKey, array $data): array
    {
        $endpoint = $this->endpoint($endpointKey);
        $response = $this->request($method, $endpoint, $data);

        // Token expired → refresh once
        if ($this->isTokenExpired($response)) {
            $this->auth->forgetToken();
            $this->auth->token(true);
            $response = $this->request($method, $endpoint, $data);
        }

        return $this->unwrap($response);
    }

    protected function request(string $method, string $endpoint, array $data): array
    {
        $url = $this->baseUrl() . $endpoint;

        if ($method === 'GET' && $data) {
            $url .= '?' . http_build_query($data);
        }

        // CRITICAL: Pass endpoint to buildHeaders for conditional header logic
        $headers = $this->buildHeaders($endpoint);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => $this->timeout(),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $method !== 'GET' ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            CURLOPT_SSL_VERIFYPEER => true, // Security hardening
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new ApiException("CURL error [$errno]: $error", 500);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body'   => $body,
            'json'   => json_decode($body, true) ?: [],
        ];
    }

    /**
     * Build headers with endpoint-specific logic
     * @param string $endpoint Full endpoint path (e.g., '/selectInitOsdcInfo')
     */
    protected function buildHeaders(string $endpoint): array
    {
        $env = $this->config['env'];

        // 🚨 INITIALIZATION EXCEPTION: Only auth headers
        if (str_ends_with($endpoint, '/selectInitOsdcInfo')) {
            return [
                'Authorization: Bearer ' . $this->auth->token(),
                'Content-Type: application/json',
                'Accept: application/json',
            ];
        }

        // ✅ ALL OTHER ENDPOINTS: Full business headers
        return [
            'Authorization: Bearer ' . $this->auth->token(),
            'Content-Type: application/json',
            'Accept: application/json',
            'tin: '    . ($this->config['oscu']['tin'] ?? ''),
            'bhfId: '  . ($this->config['oscu']['bhf_id'] ?? ''),
            'cmcKey: ' . ($this->config['oscu']['cmc_key'] ?? '')
        ];
    }

    protected function isTokenExpired(array $response): bool
    {
        if ($response['status'] === 401) return true;
        
        $fault = $response['json']['fault']['faultstring'] ?? '';
        return stripos($fault, 'access token expired') !== false 
            || stripos($fault, 'invalid token') !== false;
    }

    protected function unwrap(array $response): array
    {
        $json   = $response['json'] ?? [];
        $body   = $response['body'] ?? null;
        $status = $response['status'] ?? 0;

        $resultCd  = $json['resultCd']  ?? null;
        $resultMsg = $json['resultMsg'] ?? 'Unknown API response';

        // ---------------------------------
        // HTTP-level handling
        // ---------------------------------
        if ($status === 401) {
            throw new AuthenticationException(
                'Unauthorized: Invalid or expired token',
                401
            );
        }

        if ($status < 200 || $status >= 300) {
            $message = $json['fault']['faultstring']
                ?? (is_string($body) ? $body : 'HTTP error');

            throw new ApiException(trim($message), $status);
        }

        // ---------------------------------
        // Business-level handling
        // ---------------------------------
        if ($resultCd === null) {
            return $json; // Some endpoints may not return resultCd
        }

        switch ($resultCd) {
            case '000':
            case '001':
                return $json; // ✅ Success

            default:
                // Client errors (891–899)
                if ($resultCd >= '891' && $resultCd <= '899') {
                    throw new ApiException(
                        "Client Error ({$resultCd}): {$resultMsg}",
                        400,
                        $resultCd,
                        $json
                    );
                }

                // Server errors (900+)
                if ($resultCd >= '900') {
                    throw new ApiException(
                        "Server Error ({$resultCd}): {$resultMsg}",
                        500,
                        $resultCd,
                        $json
                    );
                }

                // Fallback business error
                throw new ApiException(
                    "Business Error ({$resultCd}): {$resultMsg}",
                    400,
                    $resultCd,
                    $json
                );
        }
    }
}
