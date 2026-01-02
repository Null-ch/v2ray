<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BalanceDTO;
use App\Models\Balance;
use App\Repositories\BalanceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class CockpitBalanceService
{
    public function __construct(private BalanceRepository $balanceRepository)
    {
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->balanceRepository->getAllPaginated($perPage);
    }

    public function findById(int $id): ?Balance
    {
        /** @var Balance|null */
        return $this->balanceRepository->findById($id);
    }

    public function findByUserId(int $userId): ?Balance
    {
        /** @var Balance|null */
        return $this->balanceRepository->findByUserId($userId);
    }

    public function create(BalanceDTO $balanceDTO): ?Balance
    {
        /** @var Balance|null */
        return $this->balanceRepository->create($balanceDTO);
    }

    public function update(BalanceDTO $balanceDTO): bool
    {
        return $this->balanceRepository->update($balanceDTO);
    }

    public function delete(int $id): bool
    {
        return $this->balanceRepository->delete($id);
    }
}

