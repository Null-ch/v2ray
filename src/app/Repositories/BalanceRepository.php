<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\BalanceDTO;
use App\Models\Balance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

class BalanceRepository extends BaseRepository
{
    public function __construct(Balance $model, LoggerInterface $logger)
    {
        parent::__construct($model, $logger);
    }

    public function create(BalanceDTO $balanceDTO): ?Model
    {
        try {
            return DB::transaction(function () use ($balanceDTO) {
                return $this->createInstance($balanceDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Balance create transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return null;
        }
    }

    public function update(BalanceDTO $balanceDTO): bool
    {
        try {
            return DB::transaction(function () use ($balanceDTO) {
                return $this->updateInstance($balanceDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Balance update transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            return DB::transaction(function () use ($id) {
                return $this->deleteInstance($id);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Balance delete transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return false;
        }
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->newQuery()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Model
    {
        return $this->model
            ->newQuery()
            ->with('user')
            ->where('id', $id)
            ->first();
    }

    public function findByUserId(int $userId): ?Model
    {
        return $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->first();
    }
}

