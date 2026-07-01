<?php

namespace App\Resources;

abstract class BaseResource
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    abstract public function toArray(): array;
}
