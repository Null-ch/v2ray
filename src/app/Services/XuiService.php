<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Xui;
use Illuminate\Support\Arr;
use App\Clients\XuiApiClient;
use App\Repositories\XuiRepository;

final class XuiService
{
    /** @var array<string, XuiApiClient> */
    private array $clientCache = [];

    /** @var array<string, Xui> */
    private array $modelCache = [];

    public function __construct(private readonly XuiRepository $xuiRepository) {}

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

    /**
     * Создаёт и аутентифицирует клиент API
     *
     * @param Xui $xuiModel
     * @return XuiApiClient
     * @throws \RuntimeException
     */
    private function createXuiApiClient(Xui $xuiModel): XuiApiClient
    {
        $client = new XuiApiClient($xuiModel);

        $loginResult = $client->login(
            $xuiModel->username,
            $xuiModel->password
        );

        if (!$loginResult['ok']) {
            throw new \RuntimeException(
                'Failed to login to XUI panel: ' .
                    ($loginResult['response']['msg'] ?? 'Unknown error')
            );
        }

        return $client;
    }

    /**
     * Получает клиент API по тегу (с кешированием)
     *
     * @param string $tag
     * @return XuiApiClient
     */
    public function getXuiApiClient(string $tag): XuiApiClient
    {
        if (isset($this->clientCache[$tag])) {
            return $this->clientCache[$tag];
        }

        $xuiModel = $this->getXuiModel($tag);
        $client = $this->createXuiApiClient($xuiModel);
        $this->clientCache[$tag] = $client;

        return $client;
    }


    /**
     * Получает статус сервера
     *
     * @param string $tag
     * @return array
     */
    public function getServerStatus(string $tag): array
    {
        $client = $this->getXuiApiClient($tag);
        return $client->getServerStatus();
    }

    /**
     * Получает список всех инбаундов
     *
     * @param string $tag
     * @return array
     */
    public function getInbounds(string $tag): array
    {
        $client = $this->getXuiApiClient($tag);
        return $client->getInboundsList();
    }

    /**
     * Получает инбаунд по ID
     *
     * @param string $tag
     * @param int $inboundId
     * @return array
     */
    public function getInbound(string $tag, int $inboundId): array
    {
        $client = $this->getXuiApiClient($tag);
        return $client->getInbound($inboundId);
    }

    /**
     * Добавляет клиента в инбаунд
     *
     * @param string $tag
     * @param int $inboundId
     * @param array $clientData Данные клиента (email, uuid, password и т.д.)
     * @return array
     */
    public function addClient(string $tag, int $inboundId, array $clientData): array
    {
        $client = $this->getXuiApiClient($tag);
        return $client->addClient($inboundId, $clientData);
    }

    /**
     * Обновляет клиента в инбаунде
     *
     * @param string $tag
     * @param int $inboundId
     * @param string $uuid uuid клиента
     * @param array $clientData Новые данные клиента
     * @return array
     */
    public function updateClient(string $tag, int $inboundId, string $uuid, array $clientData): array
    {
        $client = $this->getXuiApiClient($tag);
        return $client->updateClient($inboundId, $uuid, $clientData);
    }

    /**
     * Получает трафик клиента по ID
     *
     * @param string $tag
     * @param int $inboundId
     * @param string $userId userId клиента
     * @return array
     */
    public function getClientTrafficByUserId(string $tag, int $inboundId, string $userId): array
    {
        $client = $this->getXuiApiClient($tag);
        return $client->getClientTrafficByUserId($inboundId, $userId);
    }

    /**
     * Получает трафик клиента по ID
     *
     * @param string $tag
     * @param string $uuid uuid клиента
     * @return array
     */
    public function getClientTrafficByUserUuid(string $tag, string $uuid): array
    {
        $client = $this->getXuiApiClient($tag);
        return $client->getClientTrafficByUserUuid($uuid);
    }

    public function collectInbounds(array $clients, string $tag = 'NL'): array
    {
        $inbounds = [];

        foreach (Arr::get($clients, 'data', []) as $client) {
            // Получаем inboundId клиента
            $inboundId = Arr::get($client, 'inboundId');
            if (!$inboundId) {
                continue; // пропускаем если нет inboundId
            }

            // Берем inbound через getInbound
            $inbound = $this->getInbound($tag, $inboundId);
            if (!Arr::get($inbound, 'ok')) {
                continue; // пропускаем если getInbound вернул ошибку
            }

            // Берем фактические данные inbound
            $inboundData = Arr::get($inbound, 'data', []);
            if (empty($inboundData)) {
                continue;
            }

            // Собираем в массив
            $inbounds[] = [
                $inboundData,
            ];
        }

        return $inbounds;
    }

    public function getConfigs(string $tag, string $uuid)
    {
        $model = $this->getXuiModel($tag);
        $clientData = $this->getClientTrafficByUserUuid($tag, $uuid);
        $inbounds = $this->collectInbounds($clientData, $tag);
        $configs = $this->generateAllConfigs($model, $inbounds, $clientData);

        return $configs;
    }

    public function generateAllConfigs(Xui $xuiModel, array $inboundsWrapper, array $clientsWrapper): array
    {
        $links = [];
        $clients = Arr::get($clientsWrapper, 'data', []);

        foreach ($inboundsWrapper as $inboundArray) {
            $inbound = Arr::get($inboundArray, '0', []);
            if (!$inbound) continue;

            $configName = Arr::get($inbound, 'remark');
            $streamSettingsRaw = Arr::get($inbound, 'streamSettings', '{}');
            $streamSettingsRaw = trim($streamSettingsRaw, "\" \n\r\t");
            $stream = json_decode($streamSettingsRaw, true);
            if (!$stream) continue;

            $security = Arr::get($stream, 'security', 'none');
            $network = Arr::get($stream, 'network', 'tcp');
            $port = Arr::get($inbound, 'port', $xuiModel->port ?? 443);

            $clientsForInbound = array_filter($clients, fn($c) => Arr::get($c, 'inboundId') === Arr::get($inbound, 'id'));

            foreach ($clientsForInbound as $client) {
                $uuid = Arr::get($client, 'uuid', '');
                $expiry = Arr::get($client, 'expiryTime', 0);

                // Если есть expiryTime и оно меньше текущего времени, пропускаем
                if ($expiry && $expiry < now()->timestamp * 1000) {
                    continue;
                }

                if ($expiry && $expiry > 0) {
                    $remaining = $expiry - (now()->timestamp * 1000);
                    $days = floor($remaining / 86400000);
                    $configName .= " - $days дн."; // Пишем сколько дней осталось
                }

                $params = [
                    'type' => $network,
                    'encryption' => 'none',
                    'security' => $security,
                ];

                if ($security === 'reality') {
                    $reality = Arr::get($stream, 'realitySettings.settings', []);
                    $params = array_merge($params, [
                        'pbk' => Arr::get($reality, 'publicKey', ''),
                        'fp' => Arr::get($reality, 'fingerprint', 'chrome'),
                        'sni' => Arr::get($stream, 'realitySettings.serverNames.0', 'example.com'),
                        'sid' => Arr::get($stream, 'realitySettings.shortIds.0', ''),
                        'spx' => urlencode(Arr::get($reality, 'spiderX', '/')),
                    ]);
                }

                if ($network === 'ws') {
                    $wsSettings = Arr::get($stream, 'wsSettings', []);
                    $params['path'] = Arr::get($wsSettings, 'path', '/');
                    $params['host'] = Arr::get($wsSettings, 'headers.Host', parse_url($xuiModel->host, PHP_URL_HOST));
                }

                if ($network === 'h2') {
                    $h2Settings = Arr::get($stream, 'httpSettings', []);
                    $params['path'] = Arr::get($h2Settings, 'path', '/');
                    $params['host'] = Arr::get($h2Settings, 'host', parse_url($xuiModel->host, PHP_URL_HOST));
                }

                if ($network === 'grpc') {
                    $grpcSettings = Arr::get($stream, 'grpcSettings', []);
                    $params['serviceName'] = Arr::get($grpcSettings, 'serviceName', '');
                    $params['alpn'] = 'h2';
                }

                $query = http_build_query($params);

                $links[] = sprintf(
                    'vless://%s@%s:%d?%s#%s',
                    $uuid,
                    parse_url($xuiModel->host, PHP_URL_HOST) ?? $xuiModel->host,
                    $port,
                    $query,
                    $configName
                );
            }
        }

        return $links;
    }

    public function getSubLink(string $tag, string $uuid): string
    {
        $xuiModel = $this->getXuiModel($tag);
        $host = $xuiModel->host;
        if (str_starts_with($host, 'https://')) {
            $host = str_replace('https://', 'http://', $host);
        }

        return "{$host}:2096/sub/{$uuid}";
    }
}
