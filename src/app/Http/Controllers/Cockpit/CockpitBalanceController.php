<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cockpit;

use App\DTO\BalanceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cockpit\BalanceStoreRequest;
use App\Http\Requests\Cockpit\BalanceUpdateRequest;
use App\Models\Balance;
use App\Services\CockpitBalanceService;
use App\Services\CockpitUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class CockpitBalanceController extends Controller
{
    public function __construct(
        private CockpitBalanceService $cockpitBalanceService,
        private CockpitUserService $cockpitUserService
    ) {
    }

    public function index(): View
    {
        $balances = $this->cockpitBalanceService->getAllPaginated(15);
        return view('cockpit.balance.index', compact('balances'));
    }

    public function create(): View
    {
        $users = $this->cockpitUserService->getAllPaginated(1000)->items();
        return view('cockpit.balance.create', compact('users'));
    }

    public function store(BalanceStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $balanceDTO = BalanceDTO::from($validated);
        $balance = $this->cockpitBalanceService->create($balanceDTO);

        if (is_null($balance)) {
            return redirect()
                ->route('cockpit.balance.create')
                ->withErrors(['error' => 'Не удалось создать баланс.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.balance.index')
            ->with('success', 'Баланс успешно создан.');
    }

    public function show(int $id): View
    {
        $balance = $this->cockpitBalanceService->findById($id);

        if (is_null($balance)) {
            abort(404, 'Баланс не найден.');
        }

        return view('cockpit.balance.show', compact('balance'));
    }

    public function edit(int $id): View
    {
        $balance = $this->cockpitBalanceService->findById($id);

        if (is_null($balance)) {
            abort(404, 'Баланс не найден.');
        }

        $users = $this->cockpitUserService->getAllPaginated(1000)->items();
        return view('cockpit.balance.edit', compact('balance', 'users'));
    }

    public function update(BalanceUpdateRequest $request, int $id): RedirectResponse
    {
        $balance = $this->cockpitBalanceService->findById($id);

        if (is_null($balance)) {
            abort(404, 'Баланс не найден.');
        }

        $validated = $request->validated();
        $validated['id'] = $id;
        $balanceDTO = BalanceDTO::from($validated);
        $updated = $this->cockpitBalanceService->update($balanceDTO);

        if (!$updated) {
            return redirect()
                ->route('cockpit.balance.edit', $id)
                ->withErrors(['error' => 'Не удалось обновить баланс.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.balance.index')
            ->with('success', 'Баланс успешно обновлен.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $deleted = $this->cockpitBalanceService->delete($id);

        if (!$deleted) {
            return redirect()
                ->route('cockpit.balance.index')
                ->withErrors(['error' => 'Не удалось удалить баланс.']);
        }

        return redirect()
            ->route('cockpit.balance.index')
            ->with('success', 'Баланс успешно удален.');
    }
}

