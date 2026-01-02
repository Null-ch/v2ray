<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\UserDTO;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

class UserRepository extends BaseRepository
{
    public function __construct(User $model, LoggerInterface $logger)
    {
        parent::__construct($model, $logger);
    }

    public function findByTelegramId(int $telegramId): ?Model
    {
        return $this->model
            ->newQuery()
            ->where('tg_id', $telegramId)
            ->first();
    }

    public function create(UserDTO $userDTO): ?Model
    {
        try {
            return DB::transaction(function () use ($userDTO) {
                return $this->createInstance($userDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('User create transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return null;
        }
    }

    public function update(UserDTO $userDTO): bool
    {
        try {
            return DB::transaction(function () use ($userDTO) {
                return $this->updateInstance($userDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('User update transaction error: ' . $exception->getMessage());
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
            $this->logger->error('User delete transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return false;
        }
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->newQuery()
            ->with(['balance', 'referrer'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Model
    {
        return $this->model
            ->newQuery()
            ->with(['balance', 'referrer'])
            ->where('id', $id)
            ->first();
    }
}
