<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pricing;
use App\Repositories\PricingRepository;
use Illuminate\Database\Eloquent\Collection;

final readonly class PricingService
{
    public function __construct(private PricingRepository $pricingRepository)
    {
    }

    /**
     * Получить все тарифы
     *
     * @return Collection<int, Pricing>
     */
    public function getAll(): Collection
    {
        return $this->pricingRepository->getAll();
    }
}

