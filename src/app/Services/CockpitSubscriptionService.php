<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\SubscriptionDTO;
use App\Models\Subscription;
use App\Repositories\SubscriptionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class CockpitSubscriptionService
{
    public function __construct(private SubscriptionRepository $subscriptionRepository)
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
}


