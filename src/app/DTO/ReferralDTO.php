<?php

declare(strict_types=1);

namespace App\DTO;

final class ReferralDTO extends BaseItemDTO
{
    public function __construct(
        public ?int $id = null,
        public ?int $user_id = null,
        public ?int $referred_user_id = null,
    ) {
    }
}

