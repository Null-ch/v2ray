<?php

declare(strict_types=1);

namespace App\DTO;

final class PricingDTO extends BaseItemDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $title = null,
        public ?int $duration = null,
        public ?float $price = null,
    ) {
    }
}

