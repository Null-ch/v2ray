<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Xui;
use App\Repositories\XuiRepository;
use Illuminate\Support\Facades\Log;
use XUI\Xray\Inbound\Inbound as InboundClass;
use XUI\Xui as XuiClient;

final class XuiService
{
    /** @var array<string, XuiClient> */
    private array $clientCache = [];

    /** @var array<string, Xui> */
    private array $modelCache = [];

    public function __construct(private readonly XuiRepository $xuiRepository)
    {
    }

    private function getXuiModel(string $tag): Xui
    {
        if (isset($this->modelCache[$tag])) {
            return $this->modelCache[$tag];
        }

        $xui = $this->xuiRepository->findByTag($tag);

        if (!$xui) {
            throw new \RuntimeException("Active XUI configuration with tag '{$tag}' not found");
        }

        $this->modelCache[$tag] = $xui;

        return $xui;
    }

    public function getXuiModelByTag(string $tag): Xui
    {
        return $this->getXuiModel($tag);
    }

    private function createXuiClient(Xui $xuiModel): XuiClient
    {
        // Очищаем host от протокола и порта
        $host = $xuiModel->host;

        if (str_starts_with($host, 'https://')) {
            $host = str_replace('https://', '', $host);
        } elseif (str_starts_with($host, 'http://')) {
            $host = str_replace('http://', '', $host);
        }

        // Убираем порт из host если он там есть
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        // Создаём директорию для cookies в storage (не в vendor)
        $cookieDir = storage_path('app/.xui-cookies');
        if (!is_dir($cookieDir)) {
            mkdir($cookieDir, 0755, true);
        }

        // Используем HTTP (без SSL) для избежания проблем с самоподписанными сертификатами
        $xui = new XuiClient($host, $xuiModel->port, $xuiModel->path, false);

        // Устанавливаем путь к cookie файлу в storage вместо vendor
        $cookieFile = $cookieDir . '/' . md5($host . ':' . $xuiModel->port) . '.cookie';
        if (property_exists($xui, 'cookieFile')) {
            $xui->cookieFile = $cookieFile;
        }

        $loginResult = $xui->login($xuiModel->username, $xuiModel->password);

        Log::info('CLIENT LOGIN RESULT', ['host' => $host, 'port' => $xuiModel->port, 'path' => $xuiModel->path]);

        Log::info('XUI Login result:', [
            'ok' => $loginResult->ok ?? 'not set',
            'response' => json_encode($loginResult->response ?? 'not set'),
            'error' => $loginResult->error ?? 'not set',
        ]);

        if (!($loginResult->ok ?? false) || !($loginResult->response->success ?? false)) {
            throw new \RuntimeException('Failed to login to 3x-ui panel: ' . ($loginResult->response->msg ?? $loginResult->error ?? 'Unknown error'));
        }

        return $xui;
    }

    private function getXuiClient(string $tag): XuiClient
    {
        if (isset($this->clientCache[$tag])) {
            return $this->clientCache[$tag];
        }

        $xuiModel = $this->getXuiModel($tag);
        $client = $this->createXuiClient($xuiModel);
        $this->clientCache[$tag] = $client;

        return $client;
    }

    public function getXui(string $tag): XuiClient
    {
        return $this->getXuiClient($tag);
    }

    public function getServer(string $tag)
    {
        return $this->getXuiClient($tag)->server;
    }

    public function getXray(string $tag)
    {
        return $this->getXuiClient($tag)->xray;
    }

    public function getPanel(string $tag)
    {
        return $this->getXuiClient($tag)->panel;
    }

    public function getServerStatus(string $tag): array
    {
        $xui = $this->getXuiClient($tag);
        $result = $xui->server->status();

        return [
            'ok' => $result->ok ?? false,
            'data' => $result->response->obj ?? null,
            'message' => $result->response->msg ?? null,
        ];
    }

    public function getInbounds(string $tag): array
    {
        $xui = $this->getXuiClient($tag);
        $result = $xui->xray->inbound->list();

        return [
            'ok' => $result->ok ?? false,
            'data' => $result->response->obj ?? null,
            'message' => $result->response->msg ?? null,
        ];
    }

    public function getOutbounds(string $tag): array
    {
        $xui = $this->getXuiClient($tag);
        $result = $xui->xray->outbound->list();

        return [
            'ok' => $result->ok ?? false,
            'data' => $result->response->obj ?? null,
            'message' => $result->response->msg ?? null,
        ];
    }

    public function getPanelSettings(string $tag): array
    {
        $xui = $this->getXuiClient($tag);
        $result = $xui->panel->settings();

        return [
            'ok' => $result->ok ?? false,
            'data' => $result->response->obj ?? null,
            'message' => $result->response->msg ?? null,
        ];
    }

    /**
     * Create a client configuration in an inbound
     * 
     * @param string $tag XUI tag
     * @param User $user User model
     * @param int|null $inboundId Inbound ID (if null, uses first inbound)
     * @param int $expiryTime Expiry time in seconds
     * @return array
     * @throws \RuntimeException
     */
    public function createConfig(string $tag, User $user, ?int $inboundId = null, int $expiryTime = 0): array
    {
        $xui = $this->getXuiClient($tag);
        
        // Get inbounds list
        $inboundsResult = $this->getInbounds($tag);
        
        if (!$inboundsResult['ok'] || empty($inboundsResult['data'])) {
            throw new \RuntimeException('Failed to get inbounds: ' . ($inboundsResult['message'] ?? 'Unknown error'));
        }

        $inbounds = $inboundsResult['data'];
        
        // Find inbound by ID or use first one
        $inbound = null;
        if ($inboundId !== null) {
            foreach ($inbounds as $inb) {
                if ($inb->id == $inboundId) {
                    $inbound = $inb;
                    break;
                }
            }
            if (!$inbound) {
                throw new \RuntimeException("Inbound with ID {$inboundId} not found");
            }
        } else {
            $inbound = $inbounds[0] ?? null;
            if (!$inbound) {
                throw new \RuntimeException('No inbounds available');
            }
            $inboundId = $inbound->id;
        }

        // Get full inbound configuration
        $inboundResult = $xui->xray->inbound->get($inboundId);
        
        if (!$inboundResult->ok || !$inboundResult->response) {
            throw new \RuntimeException('Failed to get inbound configuration: ' . ($inboundResult->error ?? 'Unknown error'));
        }

        $inboundConfig = $inboundResult->response;
        
        // Read inbound configuration using Inbound::read()
        $config = InboundClass::read($inboundConfig);
        
        if (!$config) {
            throw new \RuntimeException('Failed to read inbound configuration');
        }

        // Check if client already exists
        $clientEmail = (string) $user->id;
        if ($config->settings->has_client($clientEmail)) {
            throw new \RuntimeException("Client with email {$clientEmail} already exists in this inbound");
        }

        // Add client to settings
        // For vmess/vless: uuid = user->uuid, email = user->id
        // For trojan: email = user->id, password will be generated
        // expiryTime is in seconds, library will multiply by 1000
        if (in_array($config->protocol, ['vmess', 'vless'])) {
            $clientUuid = $user->uuid;
            if (empty($clientUuid)) {
                throw new \RuntimeException('User UUID is required for vmess/vless protocols');
            }
            $config->settings->add_client(
                enable: true,
                uuid: $clientUuid,
                email: $clientEmail,
                expiry_time: $expiryTime
            );
        } elseif ($config->protocol === 'trojan') {
            // Trojan uses password instead of uuid
            $config->settings->add_client(
                enable: true,
                email: $clientEmail,
                password: $user->uuid ?? null, // Use uuid as password if available
                expiry_time: $expiryTime
            );
        } elseif ($config->protocol === 'shadowsocks') {
            // Shadowsocks uses method, password, email in that order
            $config->settings->add_client(
                enable: true,
                method: null, // Use default method
                password: $user->uuid ?? null,
                email: $clientEmail,
                expiry_time: $expiryTime
            );
        } else {
            throw new \RuntimeException("Protocol {$config->protocol} is not supported for client creation");
        }

        // Update inbound with new client
        $updateResult = $xui->xray->inbound->update(
            inbound_id: $inboundId,
            config: $config
        );

        if (!$updateResult->ok || !($updateResult->response->success ?? false)) {
            throw new \RuntimeException('Failed to update inbound: ' . ($updateResult->response->msg ?? $updateResult->error ?? 'Unknown error'));
        }

        return [
            'ok' => true,
            'data' => [
                'inbound_id' => $inboundId,
                'client_email' => $clientEmail,
                'user_id' => $user->id,
            ],
            'message' => 'Client successfully added to inbound',
        ];
    }

    /**
     * Get user configuration from inbound
     * 
     * @param string $tag XUI tag
     * @param int $inboundId Inbound ID
     * @param int $userId User ID (used as client email)
     * @return array
     * @throws \RuntimeException
     */
    public function getUserConfig(string $tag, int $inboundId, int $userId): array
    {
        $xui = $this->getXuiClient($tag);
        
        // Get inbound configuration
        $inboundResult = $xui->xray->inbound->get($inboundId);
        
        if (!$inboundResult->ok || !$inboundResult->response) {
            throw new \RuntimeException('Failed to get inbound configuration: ' . ($inboundResult->error ?? 'Unknown error'));
        }

        $inboundConfig = $inboundResult->response;
        
        // Read inbound configuration
        $config = InboundClass::read($inboundConfig);
        
        if (!$config) {
            throw new \RuntimeException('Failed to read inbound configuration');
        }

        // Get client by email (user ID)
        $clientEmail = (string) $userId;
        $client = $config->settings->get_client($clientEmail);
        
        if (!$client) {
            throw new \RuntimeException("Client with email {$clientEmail} not found in inbound {$inboundId}");
        }

        // Get inbound details for full configuration
        $inboundData = [
            'inbound_id' => $inboundId,
            'protocol' => $config->protocol,
            'listen' => $config->listen,
            'port' => $config->port,
            'client' => $client,
            'stream_settings' => $config->stream_settings ?? null,
            'sniffing' => $config->sniffing ?? null,
        ];

        return [
            'ok' => true,
            'data' => $inboundData,
            'message' => 'Client configuration retrieved successfully',
        ];
    }
}

