<?php

namespace ParkGeeYoong\MyFeeder;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NeoFeederService
{
    protected string $url;
    protected string $username;
    protected string $password;
    protected int $timeout;

    public function __construct()
    {
        $this->url = (string) config('neofeeder.url', '');
        $this->username = (string) config('neofeeder.username', '');
        $this->password = (string) config('neofeeder.password', '');
        $this->timeout = (int) config('neofeeder.timeout', 15);
    }

    /**
     * Standardized success wrapper
     */
    protected function successResponse(mixed $data): array
    {
        return [
            'error_code' => 0,
            'error_desc' => '',
            'data' => $data,
        ];
    }

    /**
     * Standardized error wrapper
     */
    protected function errorResponse(int $code, string $message): array
    {
        return [
            'error_code' => $code,
            'error_desc' => $message,
            'data' => null,
        ];
    }

    /**
     * Internal: perform POST and return decoded json or throw (internal)
     * Returns array|null (decoded json) or null on failure.
     */
    protected function postJson(array $payload): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->url, $payload);
        } catch (\Throwable $e) {
            Log::error('NeoFeeder HTTP error: ' . $e->getMessage(), ['exception' => $e, 'payload' => $payload]);
            return null;
        }

        if (! $response->successful()) {
            Log::error('NeoFeeder non-success response', ['status' => $response->status(), 'body' => $response->body(), 'payload' => $payload]);
            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            Log::error('NeoFeeder invalid json', ['body' => $response->body(), 'payload' => $payload]);
            return null;
        }

        return $json;
    }

    /**
     * Ambil token dari NeoFeeder — mengembalikan struktur standar.
     */
    public function getToken(): array
    {
        $payload = [
            'act' => 'GetToken',
            'username' => $this->username,
            'password' => $this->password,
        ];

        $json = $this->postJson($payload);

        if ($json === null) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        // Jika struktur JSON mengandung token di ['data']['token']
        $token = $json['data']['token'] ?? null;

        if (empty($token)) {
            // jika response valid tapi token kosong -> anggap data kosong
            return $this->errorResponse(204, 'data kosong dari neofeeder');
        }

        // kembalikan token di dalam struktur standar (data: original data)
        return $this->successResponse($json['data']);
    }

    /**
     * Memanggil Web Service NeoFeeder (runWS) — mengembalikan struktur standar.
     *
     * @param string $act
     * @param string $filter
     * @param mixed $limit
     * @param mixed $offset
     * @param string $order
     * @return array
     */
    public function runWS(string $act, string $filter = '', $limit = '', $offset = '', string $order = ''): array
    {
        // pertama ambil token (struktur standar)
        $tokenResp = $this->getToken();

        if (isset($tokenResp['error_code']) && $tokenResp['error_code'] !== 0) {
            // kembalikan error/token-failed langsung
            return $tokenResp;
        }

        // token tersedia pada tokenResp['data']['token']? (ingat getToken mengembalikan data bagian 'data')
        $token = $tokenResp['data']['token'] ?? null;

        if (empty($token)) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        $payload = [
            'act' => $act,
            'token' => $token,
            'filter' => $filter,
            'limit' => $limit,
            'offset' => $offset,
            'order' => $order,
        ];

        $json = $this->postJson($payload);

        if ($json === null) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        // jika struktur sukses namun data kosong/null
        if (empty($json['data'])) {
            return $this->errorResponse(204, 'data kosong dari neofeeder');
        }

        // sukses -> bungkus data asli (json['data']) jadi standar
        return $this->successResponse($json['data']);
    }

    /**
     * Helper detWs: shortcut untuk filter sederhana — mengembalikan struktur standar.
     */
    public function detWs(string $fitur, string $field, string $id): array
    {
        $newId = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $filter = "{$field} = '{$newId}'";
        return $this->runWS($fitur, $filter, '', '', '');
    }
    public function getProfilPT(): array
    {
        $fitur = 'GetProfilPT';
        $filter = "";
        return $this->runWS($fitur, $filter, '', '', '');
    }
}