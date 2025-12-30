<?php

declare(strict_types=1);

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DtoItemAttribute
{
    public function __construct(
        public string $fieldName
    ) {
    }
}
