<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\XuiTag;

final class UserTagDTO extends BaseItemDTO
{
    public function __construct(
        public ?int $id = null,
        public ?int $user_id = null,
        public ?XuiTag $tag = null,
    ) {
    }
}

