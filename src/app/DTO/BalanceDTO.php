<?php

declare(strict_types=1);

namespace App\DTO;

final class BalanceDTO extends BaseItemDTO
{
    public function __construct(
        public ?int $id = null,
        public ?int $user_id = null,
        public ?float $balance = null,
    ) {
    }
}

