<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Arr;
use App\Services\XuiService;
use Illuminate\Http\Request;
use App\Http\Resources\FailResource;
use App\Http\Resources\SuccessResource;
use Illuminate\Http\Resources\Json\JsonResource;

final class XuiController
{
    public function __construct(private readonly XuiService $xuiService) {}

    private function getTag(Request $request): string
    {
        $tag = $request->input('tag') ?? $request->route('tag');

        if (empty($tag)) {
            throw new \RuntimeException('Tag parameter is required');
        }

        return $tag;
    }

    private function getInboundId(Request $request): int
    {
        $inboundId = $request->input('inboundId') ?? $request->route('inboundId');

        if (empty($inboundId)) {
            throw new \RuntimeException('inboundId parameter is required');
        }

        return (int) $inboundId;
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

    /**
     * Получает инбаунд по ID
     *
     * @param Request $request
     * @param mixed $inboundId
     * @return JsonResource
     */
    public function getInbound(Request $request, mixed $inboundId): JsonResource
    {
        try {
            $tag = $this->getTag($request);
            $inboundId = $this->getInboundId($request);
            $result = $this->xuiService->getInbound($tag, $inboundId);

            if ($result['ok']) {
                return SuccessResource::make($result);
            }

            return FailResource::make(['message' => $result['message'] ?? 'Failed to get inbound']);
        } catch (\Throwable $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }

    /**
     * Добавляет клиента в инбаунд
     *
     * @param Request $request
     * @param mixed $inboundId
     * @return JsonResource
     */
    public function addClient(Request $request, mixed $inboundId): JsonResource
    {
        try {
            $tag = $this->getTag($request);
            $inboundId = $this->getInboundId($request);

            $clientData = $request->validate([
                'id'         => 'required|uuid',
                'flow'       => 'nullable|string',
                'limitIp'    => 'nullable|integer',
                'totalGB'    => 'nullable|integer',
                'expiryTime' => 'nullable|integer',
                'enable'     => 'nullable|boolean',
                'tgId'       => 'nullable|string',
                'subId'      => 'nullable|string',
                'comment'    => 'nullable|string',
                'reset'      => 'nullable|integer',
            ]);

            $result = $this->xuiService->addClient($tag, $inboundId, $clientData);

            if ($result['ok']) {
                return SuccessResource::make($result);
            }

            return FailResource::make(['message' => $result['message'] ?? 'Failed to add client']);
        } catch (\Throwable $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }

    /**
     * Обновляет клиента в инбаунде
     *
     * @param Request $request
     * @param mixed $inboundId
     * @return JsonResource
     */
    public function updateClient(Request $request, mixed $inboundId): JsonResource
    {
        try {
            $tag = $this->getTag($request);
            $inboundId = $this->getInboundId($request);
            $clientData = $request->validate([
                'id'         => 'required|uuid',
                'flow'       => 'nullable|string',
                'limitIp'    => 'nullable|integer',
                'totalGB'    => 'nullable|integer',
                'expiryTime' => 'nullable|integer',
                'enable'     => 'nullable|boolean',
                'tgId'       => 'nullable|string',
                'subId'      => 'nullable|string',
                'comment'    => 'nullable|string',
                'reset'      => 'nullable|integer',
            ]);

            $uuid = Arr::get($clientData, 'id');
            $result = $this->xuiService->updateClient($tag, $inboundId, $uuid, $clientData);

            if ($result['ok']) {
                return SuccessResource::make($result);
            }

            return FailResource::make(['message' => $result['message'] ?? 'Failed to update client']);
        } catch (\Throwable $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }

    /**
     * Получает трафик клиента по ID
     *
     * @param Request $request
     * @param mixed $inboundId
     * @return JsonResource
     */
    public function getClientTrafficByUserId(Request $request, mixed $inboundId): JsonResource
    {
        try {
            $tag = $this->getTag($request);
            $inboundId = $this->getInboundId($request);
            $validated = $request->validate([
                'userId' => 'required|string',
            ]);

            $result = $this->xuiService->getClientTrafficByUserId($tag, $inboundId, $validated['userId']);

            if ($result['ok']) {
                return SuccessResource::make($result);
            }

            return FailResource::make(['message' => $result['message'] ?? 'Failed to get client traffic']);
        } catch (\Throwable $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }

    /**
     * Получает трафик клиента по ID
     *
     * @param Request $request
     * @return JsonResource
     */
    public function getClientTrafficByUserUuid(Request $request): JsonResource
    {
        try {
            $tag = $this->getTag($request);
            $validated = $request->validate([
                'id' => 'required|string',
            ]);

            $result = $this->xuiService->getClientTrafficByUserUuid($tag, $validated['id']);

            if ($result['ok']) {
                return SuccessResource::make($result);
            }

            return FailResource::make(['message' => $result['message'] ?? 'Failed to get client traffic']);
        } catch (\Throwable $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }

    public function getConfigs(string $tag, string $uuid)
    {
        $configs = $this->xuiService->getConfigs($tag, $uuid);
        $configText = implode("\n", $configs);

        return response($configText, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'inline; filename="ПИВО VPN.txt"');
    }

    public function getSubtLink(Request $request)
    {
        $tag = $request->query('tag');
        $uuid = $request->query('uuid');
  
        return  $this->xuiService->getSubLink($tag, $uuid);
    }

    public function getConfigImportLink(Request $request)
    {
        $tag = $request->query('tag');
        $uuid = $request->query('uuid');
        $configUrl = $this->xuiService->getSubLink($tag, $uuid);
        $v2raytunUrl = 'v2raytun://import/' . $configUrl;

        return view('v2raytun-redirect', ['link' => $v2raytunUrl]);
    }
}
