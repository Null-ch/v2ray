<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cockpit;

use App\Models\Xui;
use Illuminate\View\View;
use App\Services\XuiService;
use App\Clients\XuiApiClient;
use Illuminate\Http\JsonResponse;
use App\Services\CockpitXuiService;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

final class CockpitServerMonitorController extends Controller
{
    public function __construct(
        private readonly CockpitXuiService $cockpitXuiService,
        private readonly XuiService $xuiService,
    ) {
    }

    /**
     * Display server monitoring page.
     */
    public function index(): View
    {
        $xuis = \App\Models\Xui::all();
        return view('cockpit.server-monitor.index', compact('xuis'));
    }

    /**
     * Display monitoring for specific server.
     */
    public function show(int $id): View
    {
        $xui = $this->cockpitXuiService->findById($id);

        if (is_null($xui)) {
            abort(404, 'XUI сервер не найден.');
        }

        return view('cockpit.server-monitor.show', compact('xui'));
    }

    /**
     * Get server status JSON.
     */
    public function status(int $id): JsonResponse
    {
        $xui = $this->cockpitXuiService->findById($id);

        if (!$xui) {
            return response()->json([
                'ok' => false,
                'error' => 'server_not_found',
                'message' => 'Сервер не найден в базе данных',
            ], 404);
        }

        try {
            
            $client = $this->xuiService->getXuiApiClient($xui->tag->value);
            $statusResult = $client->getServerStatus();

            if (!$statusResult['ok']) {
                return response()->json([
                    'ok' => false,
                    'error' => 'status_fetch_failed',
                    'message' => $statusResult['message'] ?? 'Не удалось получить статус сервера',
                ], 500);
            }

            return response()->json([
                'ok' => true,
                'data' => $statusResult['data'],
            ]);
        } catch (ConnectException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'connection_failed',
                'message' => 'Не удалось подключиться к серверу ' . $xui->host . ':' . $xui->port . '. Проверьте, что сервер запущен и доступен.',
            ], 503);
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'Failed to connect') || str_contains($message, 'Could not connect')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'connection_failed',
                    'message' => 'Сервер недоступен по адресу ' . $xui->host . ':' . $xui->port,
                ], 503);
            }
            
            return response()->json([
                'ok' => false,
                'error' => 'request_failed',
                'message' => 'Ошибка при выполнении запроса: ' . $message,
            ], 500);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            
            // Определяем тип ошибки по сообщению
            if (str_contains($message, 'Failed to connect') || 
                str_contains($message, 'Could not connect') ||
                str_contains($message, 'Connection refused') ||
                str_contains($message, 'cURL error 7')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'connection_failed',
                    'message' => 'Сервер недоступен. Проверьте, что сервер запущен и доступен по адресу ' . $xui->host . ':' . $xui->port,
                ], 503);
            }
            
            return response()->json([
                'ok' => false,
                'error' => 'exception',
                'message' => $message,
            ], 500);
        }
    }
}

