<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\UserTagDTO;
use App\Models\UserTag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

class UserTagRepository extends BaseRepository
{
    public function __construct(UserTag $model, LoggerInterface $logger)
    {
        parent::__construct($model, $logger);
    }

    public function create(UserTagDTO $userTagDTO): ?Model
    {
        try {
            return DB::transaction(function () use ($userTagDTO) {
                return $this->createInstance($userTagDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('UserTag create transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return null;
        }
    }

    public function update(UserTagDTO $userTagDTO): bool
    {
        try {
            return DB::transaction(function () use ($userTagDTO) {
                return $this->updateInstance($userTagDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('UserTag update transaction error: ' . $exception->getMessage());
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
            $this->logger->error('UserTag delete transaction error: ' . $exception->getMessage());
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

    public function findByUserId(int $userId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->get();
    }

    public function findByUserIdAndTag(int $userId, string $tag): ?Model
    {
        return $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->where('tag', $tag)
            ->first();
    }
}

