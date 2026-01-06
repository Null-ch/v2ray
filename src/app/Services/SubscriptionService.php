<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\SubscriptionDTO;
use App\Enums\XuiTag;
use App\Models\Subscription;
use App\Repositories\SubscriptionRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class SubscriptionService
{
    public function __construct(private readonly SubscriptionRepository $subscriptionRepository)
    {
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->subscriptionRepository->getAllPaginated($perPage);
    }

    public function findById(int $id): ?Subscription
    {
        /** @var Subscription|null */
        return $this->subscriptionRepository->findById($id);
    }

    public function findByUserId(int $userId): Collection
    {
        return $this->subscriptionRepository->findByUserId($userId);
    }

    public function findByUserIdAndXui(int $userId, int $xuiId): Collection
    {
        return $this->subscriptionRepository->findByUserIdAndXui($userId, $xuiId);
    }

    public function create(SubscriptionDTO $subscriptionDTO): ?Subscription
    {
        /** @var Subscription|null */
        return $this->subscriptionRepository->create($subscriptionDTO);
    }

    public function update(SubscriptionDTO $subscriptionDTO): bool
    {
        return $this->subscriptionRepository->update($subscriptionDTO);
    }

    public function delete(int $id): bool
    {
        return $this->subscriptionRepository->delete($id);
    }

    public function syncFromClientData(
        int $userId,
        int $xuiId,
        array $clientData
    ): void {
        $uuid = $clientData['id'] ?? $clientData['uuid'] ?? null;
        if (!$uuid) {
            return;
        }

        $expiryTime = $clientData['expiryTime'] ?? null;

        $expiresAt = null;
        if ($expiryTime) {
            // expiryTime приходит в миллисекундах
            $expiresAt = Carbon::createFromTimestampMs((int) $expiryTime)->toDateTimeString();
        }

        // Удаляем просроченные подписки для данного пользователя и тега
        $this->subscriptionRepository->deleteExpiredByUserAndXui($userId, $xuiId);

        // Создаем/обновляем актуальную подписку
        $this->subscriptionRepository->upsertSubscription(
            $userId,
            $xuiId,
            $uuid,
            $expiresAt
        );
    }
}


