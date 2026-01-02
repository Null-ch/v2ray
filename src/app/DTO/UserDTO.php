<?php

declare(strict_types=1);

namespace App\DTO;

final class UserDTO extends BaseItemDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $tg_tag = null,
        public ?string $phone_number = null,
        public ?int $tg_id = null,
        public ?string $uuid = null,
        public ?int $referrer_id = null,
    ) {
    }
}

