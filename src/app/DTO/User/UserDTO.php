<?php

namespace App\DTO\User;

use App\DTO\BaseItemDTO;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;

final class UserDTO extends BaseItemDTO
{
    public function __construct(
        #[MapInputName('tg_id')]
        #[MapOutputName('tg_id')]
        public int $telegramId,

        #[MapInputName('tg_tag')]
        #[MapOutputName('tg_tag')]
        public ?string $tgTag = null,

        public ?string $name = null,
        public string $uuid
    ) {
    }
}
