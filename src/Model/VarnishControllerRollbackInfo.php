<?php

declare(strict_types=1);

namespace App\Model;

final class VarnishControllerRollbackInfo implements RollbackInfo
{
    public function __construct(
        private readonly string $fileId,
    ) {
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }
}
