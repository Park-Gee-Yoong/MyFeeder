<?php

namespace ParkGeeYoong\MyFeeder;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ParkGeeYoong\MyFeeder\Exceptions\NeoFeederException;

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
     * Ambil token dari NeoFeeder
     *
     * @return array
     * @throws NeoFeederException
     */
    public function getToken(): array
    {
        $payload = [
            'act' => 'GetToken',
            'username' => $this->username,
            'password' => $this->password,
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->url, $payload);
        } catch (\Throwable $e) {
            Log::error('NeoFeeder getToken error: ' . $e->getMessage(), ['exception' => $e]);
            throw new NeoFeederException('terputus dari neofeeder');
        }

        if (! $response->successful()) {
            Log::error('NeoFeeder getToken non-success', ['status' => $response->status(), 'body' => $response->body()]);
            throw new NeoFeederException('terputus dari neofeeder');
        }

        $json = $response->json();
        if (! is_array($json)) {
            Log::error('NeoFeeder getToken invalid json', ['body' => $response->body()]);
            throw new NeoFeederException('terputus dari neofeeder');
        }

        return $json;
    }

    /**
     * Memanggil Web Service NeoFeeder (runWS)
     
     * @param string $act
     * @param string $filter
     * @param mixed $limit
     * @param mixed $offset
     * @param string $order
     * @return array
     * @throws NeoFeederException
     */
    public function runWS(string $act, string $filter = '', $limit = '', $offset = '', string $order = ''): array
    {
        $tokenResp = $this->getToken();
        $token = $tokenResp['data']['token'] ?? null;

        if (empty($token)) {
            Log::error('NeoFeeder token missing', ['response' => $tokenResp]);
            throw new NeoFeederException('terputus dari neofeeder');
        }

        $payload = [
            'act' => $act,
            'token' => $token,
            'filter' => $filter,
            'limit' => $limit,
            'offset' => $offset,
            'order' => $order,
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->url, $payload);
        } catch (\Throwable $e) {
            Log::error('NeoFeeder runWS error: '.$e->getMessage(), ['exception' => $e, 'payload' => $payload]);
            throw new NeoFeederException('terputus dari neofeeder');
        }

        if (! $response->successful()) {
            Log::error('NeoFeeder runWS non-success', ['status' => $response->status(), 'body' => $response->body(), 'payload' => $payload]);
            throw new NeoFeederException('terputus dari neofeeder');
        }

        $json = $response->json();
        if (! is_array($json)) {
            Log::error('NeoFeeder runWS invalid json', ['body' => $response->body()]);
            throw new NeoFeederException('terputus dari neofeeder');
        }

        return $json;
    }

    /**
     * Helper detWs seperti sebelumnya
     */
    public function detWs(string $fitur, string $field, string $id): array
    {
        $newId = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $filter = "{$field} = '{$newId}'";
        return $this->runWS($fitur, $filter, '', '', '');
    }
}
