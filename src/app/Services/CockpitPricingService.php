<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\PricingDTO;
use App\Models\Pricing;
use App\Repositories\PricingRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class CockpitPricingService
{
    public function __construct(private PricingRepository $pricingRepository)
    {
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->pricingRepository->getAllPaginated($perPage);
    }

    public function findById(int $id): ?Pricing
    {
        /** @var Pricing|null */
        return $this->pricingRepository->findById($id);
    }

    public function create(PricingDTO $pricingDTO): ?Pricing
    {
        /** @var Pricing|null */
        return $this->pricingRepository->create($pricingDTO);
    }

    public function update(PricingDTO $pricingDTO): bool
    {
        return $this->pricingRepository->update($pricingDTO);
    }

    public function delete(int $id): bool
    {
        return $this->pricingRepository->delete($id);
    }
}

