<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cockpit;

use App\DTO\XuiDTO;
use App\Enums\XuiTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cockpit\XuiStoreRequest;
use App\Http\Requests\Cockpit\XuiUpdateRequest;
use App\Models\Xui;
use App\Services\CockpitXuiService;
use App\Services\XuiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class CockpitXuiController extends Controller
{
    public function __construct(
        private CockpitXuiService $cockpitXuiService,
        private XuiService $xuiService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $xuis = $this->cockpitXuiService->getAllPaginated(15);

        return view('cockpit.xui.index', compact('xuis'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $tags = XuiTag::options();
        return view('cockpit.xui.create', compact('tags'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(XuiStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['ssl'] = $request->boolean('ssl', false);
        $validated['is_active'] = $request->boolean('is_active', false);
        $validated['tag'] = XuiTag::from($validated['tag']);

        $xuiDTO = XuiDTO::from($validated);
        $xui = $this->cockpitXuiService->create($xuiDTO);

        if (is_null($xui)) {
            return redirect()
                ->route('cockpit.xui.create')
                ->withErrors(['error' => 'Не удалось создать XUI сервер.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.xui.index')
            ->with('success', 'XUI сервер успешно создан.');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): View
    {
        $xui = $this->cockpitXuiService->findById($id);

        if (is_null($xui)) {
            abort(404, 'XUI сервер не найден.');
        }

        return view('cockpit.xui.show', compact('xui'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $xui = $this->cockpitXuiService->findById($id);

        if (is_null($xui)) {
            abort(404, 'XUI сервер не найден.');
        }

        $tags = XuiTag::options();
        return view('cockpit.xui.edit', compact('xui', 'tags'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(XuiUpdateRequest $request, int $id): RedirectResponse
    {
        $xui = $this->cockpitXuiService->findById($id);

        if (is_null($xui)) {
            abort(404, 'XUI сервер не найден.');
        }

        $validated = $request->validated();
        $validated['id'] = $id;
        $validated['ssl'] = $request->boolean('ssl', false);
        $validated['is_active'] = $request->boolean('is_active', false);
        $validated['tag'] = XuiTag::from($validated['tag']);

        // Если пароль не указан или пустой, не обновляем его (удаляем из массива)
        if (!isset($validated['password']) || empty(trim($validated['password'] ?? ''))) {
            unset($validated['password']);
        }

        $xuiDTO = XuiDTO::from($validated);
        $updated = $this->cockpitXuiService->update($xuiDTO);

        if (!$updated) {
            return redirect()
                ->route('cockpit.xui.edit', $id)
                ->withErrors(['error' => 'Не удалось обновить XUI сервер.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.xui.index')
            ->with('success', 'XUI сервер успешно обновлен.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $deleted = $this->cockpitXuiService->delete($id);

        if (!$deleted) {
            return redirect()
                ->route('cockpit.xui.index')
                ->withErrors(['error' => 'Не удалось удалить XUI сервер.']);
        }

        return redirect()
            ->route('cockpit.xui.index')
            ->with('success', 'XUI сервер успешно удален.');
    }

    /**
     * Check XUI server status.
     */
    public function checkStatus(int $id): JsonResponse
    {
        $xui = $this->cockpitXuiService->findById($id);
        
        if (!$xui) {
            return response()->json(['ok' => false, 'status' => 'not_found'], 404);
        }

        try {
            // Простая проверка доступности через HTTP/HTTPS
            $url = ($xui->ssl ? 'https://' : 'http://') . $xui->host . ':' . $xui->port . $xui->path;
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $isOnline = $response !== false && $httpCode > 0 && $httpCode < 500;
            
            return response()->json([
                'ok' => true,
                'status' => $isOnline ? 'online' : 'offline',
                'http_code' => $httpCode,
                'error' => $error ?: null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}

