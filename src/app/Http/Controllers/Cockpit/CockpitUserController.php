<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cockpit;

use App\DTO\User\UserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cockpit\UserStoreRequest;
use App\Http\Requests\Cockpit\UserUpdateRequest;
use App\Models\User;
use App\Services\CockpitUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CockpitUserController extends Controller
{
    public function __construct(private CockpitUserService $cockpitUserService)
    {
    }

    public function index(): View
    {
        $users = $this->cockpitUserService->getAllPaginated(15);
        return view('cockpit.user.index', compact('users'));
    }

    public function create(): View
    {
        $users = $this->cockpitUserService->getAllPaginated(1000)->items();
        return view('cockpit.user.create', compact('users'));
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userDTO = UserDTO::from($validated);
        $user = $this->cockpitUserService->create($userDTO);

        if (is_null($user)) {
            return redirect()
                ->route('cockpit.user.create')
                ->withErrors(['error' => 'Не удалось создать пользователя.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.user.index')
            ->with('success', 'Пользователь успешно создан.');
    }

    public function show(int $id): View
    {
        $user = $this->cockpitUserService->findById($id);

        if (is_null($user)) {
            abort(404, 'Пользователь не найден.');
        }

        return view('cockpit.user.show', compact('user'));
    }

    public function edit(int $id): View
    {
        $user = $this->cockpitUserService->findById($id);

        if (is_null($user)) {
            abort(404, 'Пользователь не найден.');
        }

        $users = $this->cockpitUserService->getAllPaginated(1000)->items();
        return view('cockpit.user.edit', compact('user', 'users'));
    }

    public function update(UserUpdateRequest $request, int $id): RedirectResponse
    {
        $user = $this->cockpitUserService->findById($id);

        if (is_null($user)) {
            abort(404, 'Пользователь не найден.');
        }

        $validated = $request->validated();
        $validated['id'] = $id;
        $userDTO = UserDTO::from($validated);
        $updated = $this->cockpitUserService->update($userDTO);

        if (!$updated) {
            return redirect()
                ->route('cockpit.user.edit', $id)
                ->withErrors(['error' => 'Не удалось обновить пользователя.'])
                ->withInput();
        }

        return redirect()
            ->route('cockpit.user.index')
            ->with('success', 'Пользователь успешно обновлен.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $deleted = $this->cockpitUserService->delete($id);

        if (!$deleted) {
            return redirect()
                ->route('cockpit.user.index')
                ->withErrors(['error' => 'Не удалось удалить пользователя.']);
        }

        return redirect()
            ->route('cockpit.user.index')
            ->with('success', 'Пользователь успешно удален.');
    }

    /**
     * Adjust user balance.
     */
    public function adjustBalance(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'delta' => 'required|numeric',
        ]);

        $user = $this->cockpitUserService->findById($id);
        if (!$user) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => 'user_not_found'], 404);
            }
            return redirect()->route('cockpit.user.index')->withErrors(['error' => 'Пользователь не найден.']);
        }

        $delta = (float) $validated['delta'];
        $balance = $user->balance;
        
        if (!$balance) {
            $balance = \App\Models\Balance::create([
                'user_id' => $user->id,
                'balance' => 0,
            ]);
        }

        $balance->balance += $delta;
        if ($balance->balance < 0) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => 'insufficient_balance'], 400);
            }
            return redirect()->route('cockpit.user.index')->withErrors(['error' => 'Недостаточно средств на балансе.']);
        }

        $balance->save();

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Баланс изменён.',
                'new_balance' => $balance->balance,
            ]);
        }

        return redirect()
            ->route('cockpit.user.index')
            ->with('success', 'Баланс успешно изменён.');
    }

    /**
     * Partial: users table.
     */
    public function usersTablePartial(): View
    {
        $users = User::with(['balance', 'subscriptions'])->get();
        return view('cockpit.partials.users_table', compact('users'));
    }
}

