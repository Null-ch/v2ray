<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\SubscriptionDTO;
use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

class SubscriptionRepository extends BaseRepository
{
    public function __construct(Subscription $model, LoggerInterface $logger)
    {
        parent::__construct($model, $logger);
    }

    public function create(SubscriptionDTO $subscriptionDTO): ?Model
    {
        try {
            return DB::transaction(function () use ($subscriptionDTO) {
                return $this->createInstance($subscriptionDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Subscription create transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return null;
        }
    }

    public function update(SubscriptionDTO $subscriptionDTO): bool
    {
        try {
            return DB::transaction(function () use ($subscriptionDTO) {
                return $this->updateInstance($subscriptionDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Subscription update transaction error: ' . $exception->getMessage());
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
            $this->logger->error('Subscription delete transaction error: ' . $exception->getMessage());
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

    public function findByUserIdAndXui(int $userId, int $xuiId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->where('xui_id', $xuiId)
            ->get();
    }

    public function deleteExpiredByUserAndXui(int $userId, int $xuiId): int
    {
        return $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->where('xui_id', $xuiId)
            ->where('expires_at', '<', now())
            ->delete();
    }

    public function upsertSubscription(
        int $userId,
        int $xuiId,
        string $uuid,
        ?string $expiresAt
    ): ?Subscription {
        try {
            /** @var Subscription|null $subscription */
            $subscription = $this->model
                ->newQuery()
                ->where('user_id', $userId)
                ->where('xui_id', $xuiId)
                ->where('uuid', $uuid)
                ->first();

            if (!$subscription) {
                $subscription = new Subscription();
                $subscription->user_id = $userId;
                $subscription->xui_id = $xuiId;
                $subscription->uuid = $uuid;
            }

            if ($expiresAt !== null) {
                $subscription->expires_at = $expiresAt;
            }

            $subscription->save();

            return $subscription;
        } catch (\Throwable $exception) {
            $this->logger->error('Subscription upsert error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return null;
        }
    }
}


