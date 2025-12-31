<?php

declare(strict_types=1);

namespace App\DTO;

use App\Attributes\DtoItemAttribute;

final class TestDTO extends BaseItemDTO
{
    public function __construct(
        #[DtoItemAttribute('title')]
        public string $title
    ) {
    }
}
