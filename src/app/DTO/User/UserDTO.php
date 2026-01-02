<?php

namespace App\DTO\User;

use App\DTO\BaseItemDTO;

final class UserDTO extends BaseItemDTO
{
    public function __construct(
        public int $telegramId,
        public ?string $tgTag = null,
        public ?string $name = null,
        public string $uuid
    ) {
    }
}
