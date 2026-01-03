<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\XuiTag;

final class XuiDTO extends BaseItemDTO
{
    public function __construct(
        public ?int $id = null,
        public ?XuiTag $tag = null,
        public ?string $host = null,
        public ?int $port = null,
        public ?string $path = null,
        public ?string $username = null,
        public ?string $password = null,
        public ?bool $ssl = null,
        public ?bool $is_active = null,
        public ?int $inbound_id = null,
    ) {
    }
}

