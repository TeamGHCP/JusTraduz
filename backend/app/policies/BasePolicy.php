<?php

namespace App\Policies;

abstract class BasePolicy
{
    abstract public function before(array $user, string $ability): ?bool;
}
