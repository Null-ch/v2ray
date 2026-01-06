<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\XuiTag;

final class SubscriptionDTO extends BaseItemDTO
{
    public function __construct(
        public ?int $id = null,
        public ?int $user_id = null,
        public ?int $xui_id = null,
        public ?string $uuid = null,
        public ?string $expires_at = null,
    ) {
    }
}
