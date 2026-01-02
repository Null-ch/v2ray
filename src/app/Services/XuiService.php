<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Xui;
use App\Repositories\XuiRepository;
use XUI\Xui as XuiClient;

final class XuiService
{
    public function __construct(private readonly XuiRepository $xuiRepository)
    {
    }

    private function getXuiModel(string $tag): Xui
    {
        $xui = $this->xuiRepository->findByTag($tag);

        if (!$xui) {
            throw new \RuntimeException("Active XUI configuration with tag '{$tag}' not found");
        }

        return $xui;
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

        // Используем SSL из модели, если не указан - определяем автоматически
        $ssl = $xuiModel->ssl ?? (filter_var($host, FILTER_VALIDATE_IP) === false);

        $xui = new XuiClient($host, $xuiModel->port, $xuiModel->path, $ssl);

        $loginResult = $xui->login($xuiModel->username, $xuiModel->password);

        if (!$loginResult['ok'] || !($loginResult['response']['success'] ?? false)) {
            throw new \RuntimeException('Failed to login to 3x-ui panel: ' . ($loginResult['response']['msg'] ?? 'Unknown error'));
        }

        return $xui;
    }

    private function getXuiClient(string $tag): XuiClient
    {
        $xuiModel = $this->getXuiModel($tag);
        return $this->createXuiClient($xuiModel);
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
            'ok' => $result['ok'] ?? false,
            'data' => $result['response']['obj'] ?? null,
            'message' => $result['response']['msg'] ?? null,
        ];
    }

    public function getInbounds(string $tag): array
    {
        $xui = $this->getXuiClient($tag);
        $result = $xui->xray->inbound->list();
        
        return [
            'ok' => $result['ok'] ?? false,
            'data' => $result['response']['obj'] ?? null,
            'message' => $result['response']['msg'] ?? null,
        ];
    }

    public function getOutbounds(string $tag): array
    {
        $xui = $this->getXuiClient($tag);
        $result = $xui->xray->outbound->list();
        
        return [
            'ok' => $result['ok'] ?? false,
            'data' => $result['response']['obj'] ?? null,
            'message' => $result['response']['msg'] ?? null,
        ];
    }

    public function getPanelSettings(string $tag): array
    {
        $xui = $this->getXuiClient($tag);
        $result = $xui->panel->settings();
        
        return [
            'ok' => $result['ok'] ?? false,
            'data' => $result['response']['obj'] ?? null,
            'message' => $result['response']['msg'] ?? null,
        ];
    }
}

