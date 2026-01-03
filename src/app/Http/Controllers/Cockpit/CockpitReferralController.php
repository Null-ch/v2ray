<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cockpit;

use App\DTO\ReferralDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cockpit\ReferralStoreRequest;
use App\Http\Requests\Cockpit\ReferralUpdateRequest;
use App\Models\Referral;
use App\Services\CockpitReferralService;
use App\Services\CockpitUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class CockpitReferralController extends Controller
{
    public function __construct(
        private CockpitReferralService $cockpitReferralService,
        private CockpitUserService $cockpitUserService
    ) {
    }

    public function index(): View
    {
        $referrals = $this->cockpitReferralService->getAllPaginated(15);
        return view('cockpit.referral.index', compact('referrals'));
    }

    public function create(): View
    {
        $users = $this->cockpitUserService->getAllPaginated(1000)->items();
        return view('cockpit.referral.create', compact('users'));
    }

    public function store(ReferralStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $referralDTO = ReferralDTO::from($validated);
        $referral = $this->cockpitReferralService->create($referralDTO);

        if (is_null($referral)) {
            return redirect()
                ->route('cockpit.referral.create')
                ->withErrors(['error' => 'Не удалось создать реферальную связь.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.referral.index')
            ->with('success', 'Реферальная связь успешно создана.');
    }

    public function show(int $id): View
    {
        $referral = $this->cockpitReferralService->findById($id);

        if (is_null($referral)) {
            abort(404, 'Реферальная связь не найдена.');
        }

        return view('cockpit.referral.show', compact('referral'));
    }

    public function edit(int $id): View
    {
        $referral = $this->cockpitReferralService->findById($id);

        if (is_null($referral)) {
            abort(404, 'Реферальная связь не найдена.');
        }

        $users = $this->cockpitUserService->getAllPaginated(1000)->items();
        return view('cockpit.referral.edit', compact('referral', 'users'));
    }

    public function update(ReferralUpdateRequest $request, int $id): RedirectResponse
    {
        $referral = $this->cockpitReferralService->findById($id);

        if (is_null($referral)) {
            abort(404, 'Реферальная связь не найдена.');
        }

        $validated = $request->validated();
        $validated['id'] = $id;
        $referralDTO = ReferralDTO::from($validated);
        $updated = $this->cockpitReferralService->update($referralDTO);

        if (!$updated) {
            return redirect()
                ->route('cockpit.referral.edit', $id)
                ->withErrors(['error' => 'Не удалось обновить реферальную связь.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.referral.index')
            ->with('success', 'Реферальная связь успешно обновлена.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $deleted = $this->cockpitReferralService->delete($id);

        if (!$deleted) {
            return redirect()
                ->route('cockpit.referral.index')
                ->withErrors(['error' => 'Не удалось удалить реферальную связь.']);
        }

        return redirect()
            ->route('cockpit.referral.index')
            ->with('success', 'Реферальная связь успешно удалена.');
    }
}

