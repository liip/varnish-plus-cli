<?php

declare(strict_types=1);

namespace App\Model;

final class VacRollbackInfo implements RollbackInfo
{
    public function __construct(
        private readonly string $vclId,
        private readonly string $head,
    ) {
    }

    public function getId(): string
    {
        return $this->vclId;
    }

    public function getHead(): string
    {
        return $this->head;
    }
}
