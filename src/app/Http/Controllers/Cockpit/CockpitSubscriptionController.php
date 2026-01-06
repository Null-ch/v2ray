<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cockpit;

use App\DTO\SubscriptionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cockpit\SubscriptionStoreRequest;
use App\Http\Requests\Cockpit\SubscriptionUpdateRequest;
use App\Models\Subscription;
use App\Models\Xui;
use App\Services\CockpitSubscriptionService;
use App\Services\CockpitUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class CockpitSubscriptionController extends Controller
{
    public function __construct(
        private CockpitSubscriptionService $cockpitSubscriptionService,
        private CockpitUserService $cockpitUserService
    ) {
    }

    public function index(): View
    {
        $subscriptions = $this->cockpitSubscriptionService->getAllPaginated(15);
        return view('cockpit.subscription.index', compact('subscriptions'));
    }

    public function create(): View
    {
        $users = $this->cockpitUserService->getAllPaginated(1000)->items();
        $xuis = Xui::all();

        return view('cockpit.subscription.create', compact('users', 'xuis'));
    }

    public function store(SubscriptionStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $subscriptionDTO = SubscriptionDTO::from($validated);
        $subscription = $this->cockpitSubscriptionService->create($subscriptionDTO);

        if (is_null($subscription)) {
            return redirect()
                ->route('cockpit.subscription.create')
                ->withErrors(['error' => 'Не удалось создать подписку.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.subscription.index')
            ->with('success', 'Подписка успешно создана.');
    }

    public function show(int $id): View
    {
        $subscription = $this->cockpitSubscriptionService->findById($id);

        if (is_null($subscription)) {
            abort(404, 'Подписка не найдена.');
        }

        return view('cockpit.subscription.show', compact('subscription'));
    }

    public function edit(int $id): View
    {
        $subscription = $this->cockpitSubscriptionService->findById($id);

        if (is_null($subscription)) {
            abort(404, 'Подписка не найдена.');
        }

        $users = $this->cockpitUserService->getAllPaginated(1000)->items();
        $xuis = Xui::all();

        return view('cockpit.subscription.edit', compact('subscription', 'users', 'xuis'));
    }

    public function update(SubscriptionUpdateRequest $request, int $id): RedirectResponse
    {
        $subscription = $this->cockpitSubscriptionService->findById($id);

        if (is_null($subscription)) {
            abort(404, 'Подписка не найдена.');
        }

        $validated = $request->validated();
        $validated['id'] = $id;
        $subscriptionDTO = SubscriptionDTO::from($validated);
        $updated = $this->cockpitSubscriptionService->update($subscriptionDTO);

        if (!$updated) {
            return redirect()
                ->route('cockpit.subscription.edit', $id)
                ->withErrors(['error' => 'Не удалось обновить подписку.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.subscription.index')
            ->with('success', 'Подписка успешно обновлена.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $deleted = $this->cockpitSubscriptionService->delete($id);

        if (!$deleted) {
            return redirect()
                ->route('cockpit.subscription.index')
                ->withErrors(['error' => 'Не удалось удалить подписку.']);
        }

        return redirect()
            ->route('cockpit.subscription.index')
            ->with('success', 'Подписка успешно удалена.');
    }
}


