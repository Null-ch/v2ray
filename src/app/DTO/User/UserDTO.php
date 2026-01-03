<?php

declare(strict_types=1);

namespace App\DTO\User;

use App\DTO\BaseItemDTO;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;

final class UserDTO extends BaseItemDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,

        #[MapInputName('tg_id')]
        #[MapOutputName('tg_id')]
        public ?int $telegramId = null,

        #[MapInputName('tg_tag')]
        #[MapOutputName('tg_tag')]
        public ?string $tgTag = null,

        public ?string $uuid = null,
        public ?int $referrer_id = null,
        public ?string $referral_code = null,
    ) {
    }
}
