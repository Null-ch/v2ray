<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cockpit;

use App\DTO\SettingDTO;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CockpitSettingController extends Controller
{
    public function __construct(private readonly SettingService $settingService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $settings = $this->settingService->getAllPaginated(20);

        return view('cockpit.setting.index', compact('settings'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('cockpit.setting.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        $dto = new SettingDTO(
            key: $validated['key'],
            value: $validated['value'],
            type: $validated['type'],
            group: $validated['group'] ?? null,
            description: $validated['description'] ?? null,
            is_system: (bool) ($validated['is_system'] ?? false),
        );

        $setting = $this->settingService->upsert($dto);

        return redirect()
            ->route('cockpit.setting.show', $setting)
            ->with('success', 'Настройка успешно создана.');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): View
    {
        /** @var Setting|null $setting */
        $setting = Setting::find($id);

        if ($setting === null) {
            abort(404, 'Настройка не найдена.');
        }

        return view('cockpit.setting.show', compact('setting'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        /** @var Setting|null $setting */
        $setting = Setting::find($id);

        if ($setting === null) {
            abort(404, 'Настройка не найдена.');
        }

        return view('cockpit.setting.edit', compact('setting'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        /** @var Setting|null $setting */
        $setting = Setting::find($id);

        if ($setting === null) {
            abort(404, 'Настройка не найдена.');
        }

        $validated = $this->validateRequest($request, $setting->id);

        $dto = new SettingDTO(
            id: $setting->id,
            key: $validated['key'],
            value: $validated['value'],
            type: $validated['type'],
            group: $validated['group'] ?? null,
            description: $validated['description'] ?? null,
            is_system: (bool) ($validated['is_system'] ?? false),
        );

        $this->settingService->upsert($dto);

        return redirect()
            ->route('cockpit.setting.show', $setting)
            ->with('success', 'Настройка успешно обновлена.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        /** @var Setting|null $setting */
        $setting = Setting::find($id);

        if ($setting === null) {
            abort(404, 'Настройка не найдена.');
        }

        if ($setting->is_system) {
            return redirect()
                ->route('cockpit.setting.index')
                ->withErrors(['error' => 'Системные настройки нельзя удалить.']);
        }

        $this->settingService->delete($id);

        return redirect()
            ->route('cockpit.setting.index')
            ->with('success', 'Настройка успешно удалена.');
    }

    /**
     * Валидация входных данных.
     */
    private function validateRequest(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:settings,key';

        if ($ignoreId !== null) {
            $uniqueRule .= ',' . $ignoreId;
        }

        return $request->validate([
            'key' => ['required', 'string', 'max:255', $uniqueRule],
            'value' => ['nullable'],
            'type' => ['required', 'string', 'max:50'],
            'group' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_system' => ['nullable', 'boolean'],
        ]);
    }
}


