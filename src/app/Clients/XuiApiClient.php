<?php

declare(strict_types=1);

namespace App\Clients;

use App\Models\Xui;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\GuzzleException;

final class XuiApiClient
{
    private Client $client;
    private CookieJar $cookieJar;
    private string $baseUrl;
    private bool $isAuthenticated = false;

    public function __construct(Xui $xuiModel)
    {
        // Формируем базовый URL
        $host = $xuiModel->host;

        // Очищаем host от протокола если он там есть
        if (str_starts_with($host, 'https://')) {
            $host = str_replace('https://', '', $host);
        } elseif (str_starts_with($host, 'http://')) {
            $host = str_replace('http://', '', $host);
        }

        // Убираем порт из host если он там есть
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        $protocol = $xuiModel->ssl ? 'https' : 'http';
        $port = $xuiModel->port;
        $path = trim($xuiModel->path, '/');

        $this->baseUrl = sprintf('%s://%s:%d/%s/', $protocol, $host, $port, $path);
        // Создаём CookieJar для хранения сессии
        $this->cookieJar = new CookieJar();

        // Создаём Guzzle клиент
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'cookies' => $this->cookieJar,
            'verify' => false, // Отключаем проверку SSL для самоподписанных сертификатов
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);
    }

    /**
     * Выполняет аутентификацию в XUI панели
     *
     * @param string $username
     * @param string $password
     * @param string $twoFactorCode
     * @return array
     * @throws \RuntimeException
     */
    public function login(string $username, string $password, string $twoFactorCode = ''): array
    {
        try {
            $response = $this->client->post('login/', [
                'form_params' => [
                    'username' => $username,
                    'password' => $password
                ],
            ]);

            $body = $this->parseResponse($response);

            $success = $response->getStatusCode() === 200 && ($body['success'] ?? false);

            if ($success) {
                $this->isAuthenticated = true;
            }

            Log::info('XUI Login result:', [
                'status_code' => $response->getStatusCode(),
                'success' => $success,
                'response' => $body,
            ]);

            return [
                'ok' => $success,
                'status_code' => $response->getStatusCode(),
                'response' => $body,
            ];
        } catch (GuzzleException $e) {
            Log::error('XUI Login error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException('Failed to login to XUI panel: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает список всех инбаундов
     *
     * @return array
     * @throws \RuntimeException
     */
    public function getInboundsList(): array
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->client->get('panel/api/inbounds/list');
            $body = $this->parseResponse($response);

            return [
                'ok' => $response->getStatusCode() === 200,
                'status_code' => $response->getStatusCode(),
                'data' => $body['obj'] ?? null,
                'message' => $body['msg'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to get inbounds list: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает инбаунд по ID
     *
     * @param int $inboundId
     * @return array
     * @throws \RuntimeException
     */
    public function getInbound(int $inboundId): array
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->client->get("panel/api/inbounds/get/{$inboundId}");
            $body = $this->parseResponse($response);

            return [
                'ok' => $response->getStatusCode() === 200 && ($body['success'] ?? false),
                'status_code' => $response->getStatusCode(),
                'data' => $body['obj'] ?? null,
                'message' => $body['msg'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Failed to get inbound {$inboundId}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Добавляет клиента в инбаунд
     *
     * @param int $inboundId
     * @param array $clientData
     * @return array
     * @throws \RuntimeException
     */
    public function addClient(int $inboundId, array $clientData): array
    {
        $this->ensureAuthenticated();

        try {
            $payload = [
                'id' => $inboundId,
                'settings' => json_encode([
                    'clients' => [[
                        'id'         => Arr::get($clientData, 'id'),
                        'email'      => Arr::get($clientData, 'email', ''),
                        'flow'       => Arr::get($clientData, 'flow', ''),
                        'limitIp'    => Arr::get($clientData, 'limitIp', 0),
                        'totalGB'    => Arr::get($clientData, 'totalGB', 0),
                        'expiryTime' => Arr::get($clientData, 'expiryTime', 0),
                        'enable'     => Arr::get($clientData, 'enable', true),
                        'tgId'       => Arr::get($clientData, 'tgId', ''),
                        'subId'      => Arr::get($clientData, 'id', ''),
                        'comment'    => Arr::get($clientData, 'comment', ''),
                        'reset'      => Arr::get($clientData, 'reset', 0),
                    ]]
                ], JSON_UNESCAPED_SLASHES),
            ];

            $response = $this->client->post('panel/api/inbounds/addClient', [
                'json' => $payload,
            ]);

            $body = $this->parseResponse($response);

            return [
                'ok' => $response->getStatusCode() === 200 && ($body['success'] ?? false),
                'status_code' => $response->getStatusCode(),
                'data' => $body['obj'] ?? null,
                'message' => $body['msg'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "Failed to add client to inbound {$inboundId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }


    /**
     * Обновляет клиента в инбаунде
     *
     * @param int $inboundId
     * @param string $uuid
     * @param array $clientData
     * @return array
     * @throws \RuntimeException
     */
    public function updateClient(int $inboundId, string $uuid, array $clientData): array
    {
        $this->ensureAuthenticated();

        try {
            $payload = [
                'id' => $inboundId,
                'settings' => json_encode([
                    'clients' => [[
                        'id'         => Arr::get($clientData, 'id'),
                        'email'      => Arr::get($clientData, 'email', ''),
                        'flow'       => Arr::get($clientData, 'flow', ''),
                        'limitIp'    => Arr::get($clientData, 'limitIp', 0),
                        'totalGB'    => Arr::get($clientData, 'totalGB', 0),
                        'expiryTime' => Arr::get($clientData, 'expiryTime', 0),
                        'enable'     => Arr::get($clientData, 'enable', true),
                        'tgId'       => Arr::get($clientData, 'tgId', ''),
                        'subId'      => Arr::get($clientData, 'id', ''),
                        'comment'    => Arr::get($clientData, 'comment', ''),
                        'reset'      => Arr::get($clientData, 'reset', 0),
                    ]]
                ], JSON_UNESCAPED_SLASHES),
            ];

            $response = $this->client->post("panel/api/inbounds/updateClient/{$uuid}", [
                'json' => $payload
            ]);

            $body = $this->parseResponse($response);

            return [
                'ok' => $response->getStatusCode() === 200 && ($body['success'] ?? false),
                'status_code' => $response->getStatusCode(),
                'data' => $body['obj'] ?? null,
                'message' => $body['msg'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Failed to update client {$uuid} in inbound {$inboundId}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает трафик клиента по ID
     *
     * @param int $inboundId
     * @param string $userId
     * @return array
     * @throws \RuntimeException
     */
    public function getClientTrafficByUserId(int $inboundId, string $userId): array
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->client->get("panel/api/inbounds/getClientTraffics/{$userId}");

            $body = $this->parseResponse($response);

            return [
                'ok' => $response->getStatusCode() === 200,
                'status_code' => $response->getStatusCode(),
                'data' => $body['obj'] ?? null,
                'message' => $body['msg'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Failed to get traffic for client {$userId} in inbound {$inboundId}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает трафик клиента по ID
     *
     * @param string $uuid
     * @return array
     * @throws \RuntimeException
     */
    public function getClientTrafficByUserUuid(string $uuid): array
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->client->get("panel/api/inbounds/getClientTrafficsById/{$uuid}");

            $body = $this->parseResponse($response);

            return [
                'ok' => $response->getStatusCode() === 200,
                'status_code' => $response->getStatusCode(),
                'data' => $body['obj'] ?? null,
                'message' => $body['msg'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Failed to get traffic for client {$uuid}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Обновляет инбаунд
     *
     * @param int $inboundId
     * @param array $inboundData
     * @return array
     * @throws \RuntimeException
     */
    public function updateInbound(int $inboundId, array $inboundData): array
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->client->post("panel/api/inbounds/update/{$inboundId}", [
                'json' => $inboundData,
            ]);

            $body = $this->parseResponse($response);

            return [
                'ok' => $response->getStatusCode() === 200 && ($body['success'] ?? false),
                'status_code' => $response->getStatusCode(),
                'data' => $body['obj'] ?? null,
                'message' => $body['msg'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Failed to update inbound {$inboundId}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает статус сервера
     *
     * @return array
     * @throws \RuntimeException
     */
    public function getServerStatus(): array
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->client->get('panel/api/server/status');
            $body = $this->parseResponse($response);

            return [
                'ok' => $response->getStatusCode() === 200,
                'status_code' => $response->getStatusCode(),
                'data' => $body['obj'] ?? null,
                'message' => $body['msg'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to get server status: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Парсит ответ от API
     *
     * @param ResponseInterface $response
     * @return array
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $content = $response->getBody()->getContents();
        $decoded = json_decode($content, true);

        return $decoded ?? ['raw' => $content];
    }

    /**
     * Проверяет, что клиент аутентифицирован
     *
     * @throws \RuntimeException
     */
    private function ensureAuthenticated(): void
    {
        if (!$this->isAuthenticated) {
            throw new \RuntimeException('Client is not authenticated. Call login() first.');
        }
    }

    /**
     * Получает базовый URL
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
