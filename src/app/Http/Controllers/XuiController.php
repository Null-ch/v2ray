<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FailResource;
use App\Http\Resources\SuccessResource;
use App\Services\XuiService;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Http;

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

    public function testNewXuiLogin(): JsonResource
    {
        try {
            $url = 'https://170.168.91.110:41226/QUV0ABFfTB8pNZniIa/login/';
            $username = 'TJYTZ5ATTl';
            $password = 'ZFkHCJLM2g';

            $response = Http::withOptions([
                'verify' => false,
            ])
            ->timeout(30)
            ->asForm()
            ->post($url, [
                'username' => $username,
                'password' => $password,
                'twoFactorCode' => '',
            ]);

            return SuccessResource::make([
                'http_code' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->json() ?? $response->body(),
                'success' => $response->successful(),
            ]);
        } catch (\Throwable $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }

    public function testNewXuiInbounds(): JsonResource
    {
        try {
            $baseUrl = 'https://170.168.91.110:41226/QUV0ABFfTB8pNZniIa';
            $username = 'TJYTZ5ATTl';
            $password = 'ZFkHCJLM2g';

            $cookieJar = new CookieJar();

            $client = Http::withOptions([
                'verify' => false,
                'cookies' => $cookieJar,
            ])->timeout(30);

            $loginResponse = $client->asForm()->post($baseUrl . '/login/', [
                'username' => $username,
                'password' => $password,
                'twoFactorCode' => '',
            ]);

            if (!$loginResponse->successful()) {
                return FailResource::make([
                    'message' => 'Login failed',
                    'login_response' => $loginResponse->json() ?? $loginResponse->body(),
                ]);
            }

            $inboundsResponse = $client->withHeaders([
                'Accept' => 'application/json',
            ])->get($baseUrl . '/panel/api/inbounds/list');

            return SuccessResource::make([
                'login' => [
                    'http_code' => $loginResponse->status(),
                    'body' => $loginResponse->json() ?? $loginResponse->body(),
                ],
                'inbounds' => [
                    'http_code' => $inboundsResponse->status(),
                    'body' => $inboundsResponse->json() ?? $inboundsResponse->body(),
                    'success' => $inboundsResponse->successful(),
                ],
            ]);
        } catch (\Throwable $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }
}

