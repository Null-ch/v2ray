<?php

declare(strict_types=1);

namespace App\DTO;

use App\Attributes\DtoItemAttribute;
use ReflectionClass;
use Spatie\LaravelData\Data;

abstract class BaseItemDTO extends Data
{
    public function toModelValuesArray(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties();

        $data = [];
        foreach ($properties as $property) {
            foreach ($property->getAttributes(DtoItemAttribute::class) as $attribute) {
                $attrInstance = $attribute->newInstance();
                $value = $this->{$property->name} ?? null;

                if ($value instanceof Data) {
                    $value = $value->id;
                }

                $data[$attrInstance->fieldName] = $value;
            }
        }

        return $data;
    }
}
