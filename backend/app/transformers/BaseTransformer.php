<?php

namespace App\Transformers;

abstract class BaseTransformer
{
    abstract public function transform(array $data): array;

    public function transformCollection(array $collection): array
    {
        return array_map([$this, 'transform'], $collection);
    }
}
