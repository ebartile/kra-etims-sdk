<?php

namespace KraEtimsSdk\Services;

use RuntimeException;

class AuthOClient
{
    private string $cacheFile;

    public function __construct(private array $config)
    {
        $this->cacheFile = $config['cache_file']
            ?? sys_get_temp_dir() . '/kra_etims_token.json';
    }

    /**
     * Get cached or fresh access token
     */
    public function token(bool $force = false): string
    {
        if (!$force) {
            $cached = $this->readCache();
            if ($cached && time() < $cached['expires_at']) {
                return $cached['access_token'];
            }
        }

        $tokenData = $this->fetchToken();

        if (empty($tokenData['access_token'])) {
            throw new RuntimeException('Invalid token response from KRA');
        }

        $expiresIn = (int)($tokenData['expires_in'] ?? 3600);

        $this->writeCache([
            'access_token' => $tokenData['access_token'],
            'expires_at'   => time() + $expiresIn - 60, // buffer
        ]);

        return $tokenData['access_token'];
    }

    public function forgetToken(): void
    {
        @unlink($this->cacheFile);
    }

    /**
     * Fetch token using KRA Authorization API
     * Spec-compliant implementation
     */
    protected function fetchToken(): array
    {
        $env  = $this->config['env'];
        $auth = $this->config['auth'][$env] ?? null;

        if (!$auth) {
            throw new RuntimeException("Auth config missing for env [$env]");
        }

        if (empty($auth['consumer_key']) || empty($auth['consumer_secret'])) {
            throw new RuntimeException('Consumer key/secret not configured');
        }

        if (($this->config['env'] ?? null) === 'sbx') {
            $token_url = 'https://sbx.kra.go.ke/v1/token/generate';
        } else {
            $token_url = 'https://api.kra.go.ke/v1/token/generate';
        }

        $url = rtrim($token_url, '/')
            . '?grant_type=client_credentials';

        $headers = [
            'Authorization: Basic ' . base64_encode(
                $auth['consumer_key'] . ':' . $auth['consumer_secret']
            ),
            'Accept: application/json',
        ];

        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => implode("\r\n", $headers),
                'timeout' => $this->config['http']['timeout'] ?? 15,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new RuntimeException(
                'Token request failed: ' . ($error['message'] ?? 'unknown error')
            );
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Failed to decode token response');
        }

        // KRA error format
        if (isset($decoded['errorCode'])) {
            throw new RuntimeException(
                $decoded['errorMessage'] ?? 'Authentication failed'
            );
        }

        return $decoded;
    }

    private function readCache(): ?array
    {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        return json_decode(file_get_contents($this->cacheFile), true);
    }

    private function writeCache(array $data): void
    {
        file_put_contents($this->cacheFile, json_encode($data));
    }
}
