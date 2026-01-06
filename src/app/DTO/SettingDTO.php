<?php

declare(strict_types=1);

namespace App\DTO;

final class SettingDTO extends BaseItemDTO
{
    public function __construct(
        public ?int $id = null,
        public string $key,
        public mixed $value = null,
        public string $type = 'string',
        public ?string $group = null,
        public ?string $description = null,
        public bool $is_system = false,
    ) {
    }
}


