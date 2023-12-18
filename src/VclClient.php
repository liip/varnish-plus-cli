<?php

declare(strict_types=1);

namespace App;

use App\Model\RollbackInfo;
use App\Model\VclFile;

/**
 * Generic client for Varnish management.
 */
interface VclClient
{
    public function getVclFile(string $groupName, string $fileName): VclFile;

    public function buildRollbackInfo(VclFile $vclFile): RollbackInfo;

    public function updateVCL(VclFile $vclFile): VclFile;

    /**
     * @throws DeployFailedException
     */
    public function deploy(VclFile $vclFile): void;

    public function rollback(RollbackInfo $rollbackInfo): void;
}
