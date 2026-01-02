<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cockpit;

use App\DTO\User\UserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cockpit\UserStoreRequest;
use App\Http\Requests\Cockpit\UserUpdateRequest;
use App\Models\User;
use App\Services\CockpitUserService;
use Illuminate\Http\RedirectResponse;
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
}

