<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\UserDTO;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class CockpitUserService
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->getAllPaginated($perPage);
    }

    public function findById(int $id): ?User
    {
        /** @var User|null */
        return $this->userRepository->findById($id);
    }

    public function create(UserDTO $userDTO): ?User
    {
        /** @var User|null */
        return $this->userRepository->create($userDTO);
    }

    public function update(UserDTO $userDTO): bool
    {
        return $this->userRepository->update($userDTO);
    }

    public function delete(int $id): bool
    {
        return $this->userRepository->delete($id);
    }
}

