<?php

declare(strict_types=1);

namespace App\DTO;

final class TestDTO extends BaseItemDTO
{
    public function __construct(
        public string $title,
        public ?int $id = null,
    ) {
    }
}
