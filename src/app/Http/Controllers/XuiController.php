<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FailResource;
use App\Http\Resources\SuccessResource;
use App\Services\XuiService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class XuiController
{
    public function __construct(private readonly XuiService $xuiService)
    {
    }

    private function getTag(Request $request): string
    {
        $tag = $request->input('tag') ?? $request->route('tag');
        
        if (empty($tag)) {
            throw new \RuntimeException('Tag parameter is required');
        }

        return $tag;
    }

    public function serverStatus(Request $request): JsonResource
    {
        try {
            $tag = $this->getTag($request);
            $status = $this->xuiService->getServerStatus($tag);
            
            if ($status['ok']) {
                return SuccessResource::make($status['data']);
            }
            
            return FailResource::make(['message' => $status['message'] ?? 'Failed to get server status']);
        } catch (\Throwable $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }

    public function inbounds(Request $request): JsonResource
    {
        try {
            $tag = $this->getTag($request);
            $inbounds = $this->xuiService->getInbounds($tag);
            
            if ($inbounds['ok']) {
                return SuccessResource::make($inbounds['data']);
            }
            
            return FailResource::make(['message' => $inbounds['message'] ?? 'Failed to get inbounds']);
        } catch (\Throwable $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }

    public function outbounds(Request $request): JsonResource
    {
        try {
            $tag = $this->getTag($request);
            $outbounds = $this->xuiService->getOutbounds($tag);
            
            if ($outbounds['ok']) {
                return SuccessResource::make($outbounds['data']);
            }
            
            return FailResource::make(['message' => $outbounds['message'] ?? 'Failed to get outbounds']);
        } catch (\Throwable $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }

    public function panelSettings(Request $request): JsonResource
    {
        try {
            $tag = $this->getTag($request);
            $settings = $this->xuiService->getPanelSettings($tag);
            
            if ($settings['ok']) {
                return SuccessResource::make($settings['data']);
            }
            
            return FailResource::make(['message' => $settings['message'] ?? 'Failed to get panel settings']);
        } catch (\Throwable $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }
}

