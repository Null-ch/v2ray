<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cockpit;

use App\DTO\PricingDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cockpit\PricingStoreRequest;
use App\Http\Requests\Cockpit\PricingUpdateRequest;
use App\Models\Pricing;
use App\Services\CockpitPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class CockpitPricingController extends Controller
{
    public function __construct(private CockpitPricingService $cockpitPricingService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $pricings = $this->cockpitPricingService->getAllPaginated(15);

        return view('cockpit.pricing.index', compact('pricings'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('cockpit.pricing.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PricingStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // duration приходит в днях из формы админки, конвертируем в миллисекунды
        $validated['duration'] = (int) $validated['duration'] * 24 * 60 * 60 * 1000;

        $pricingDTO = PricingDTO::from($validated);
        $pricing = $this->cockpitPricingService->create($pricingDTO);

        if (is_null($pricing)) {
            return redirect()
                ->route('cockpit.pricing.create')
                ->withErrors(['error' => 'Не удалось создать тариф.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.pricing.index')
            ->with('success', 'Тариф успешно создан.');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): View
    {
        $pricing = $this->cockpitPricingService->findById($id);

        if (is_null($pricing)) {
            abort(404, 'Тариф не найден.');
        }

        return view('cockpit.pricing.show', compact('pricing'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $pricing = $this->cockpitPricingService->findById($id);

        if (is_null($pricing)) {
            abort(404, 'Тариф не найден.');
        }

        return view('cockpit.pricing.edit', compact('pricing'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PricingUpdateRequest $request, int $id): RedirectResponse
    {
        $pricing = $this->cockpitPricingService->findById($id);

        if (is_null($pricing)) {
            abort(404, 'Тариф не найден.');
        }

        $validated = $request->validated();

        // duration приходит в днях из формы админки, конвертируем в миллисекунды
        $validated['duration'] = (int) $validated['duration'] * 24 * 60 * 60 * 1000;
        $validated['id'] = $id;

        $pricingDTO = PricingDTO::from($validated);
        $updated = $this->cockpitPricingService->update($pricingDTO);

        if (!$updated) {
            return redirect()
                ->route('cockpit.pricing.edit', $id)
                ->withErrors(['error' => 'Не удалось обновить тариф.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.pricing.index')
            ->with('success', 'Тариф успешно обновлен.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $deleted = $this->cockpitPricingService->delete($id);

        if (!$deleted) {
            return redirect()
                ->route('cockpit.pricing.index')
                ->withErrors(['error' => 'Не удалось удалить тариф.']);
        }

        return redirect()
            ->route('cockpit.pricing.index')
            ->with('success', 'Тариф успешно удален.');
    }
}

