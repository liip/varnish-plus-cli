<?php

declare(strict_types=1);

namespace App;

use App\Model\VclFile;
use Symfony\Component\Console\Logger\ConsoleLogger;

final class VclDeployer
{
    public function __construct(
        private VclClient $client
    ) {
    }

    public function deploy(ConsoleLogger $logger, string $vcl, string $vclName, string $vclGroup): int
    {
        $vclFile = $this->client->getVclFile($vclGroup, $vclName);
        if (VclFile::NOT_EXISTING === $vclFile->getId()) {
            $logger->info('Creating new VCL for {name}', ['name' => $vclName]);
        }

        $newVCL = $this->readFile($vcl);
        if (!$newVCL) {
            throw new \InvalidArgumentException(sprintf('Filename "%s" is not readable.', $vcl));
        }
        if ($newVCL === $vclFile->getSource()) {
            $logger->info('VCL did not change, will not update');

            return 0;
        }
        $logger->info('Updating VCL id {id}', ['id' => $vclFile->getId()]);

        $rollbackInfo = $this->client->buildRollbackInfo($vclFile);

        $vclFile = $this->client->updateVCL($vclFile->withSource($newVCL));
        $logger->info('Pushed new VCL');

        $logger->info(
            'Deploying VCL {id} to group {groupID}',
            ['id' => $vclFile->getId(), 'groupID' => $vclFile->getGroupId()]
        );
        try {
            $this->client->deploy($vclFile);
        } catch (DeployFailedException $e) {
            $logger->error('Deployment failed: '.$e->getMessage());
            $this->client->rollback($rollbackInfo);
            $logger->info('Rolled VCL {vclID} back', ['vclID' => $vclFile->getId()]);

            return 1;
        }

        $logger->info('Successfully deployed VCL {id} to group {groupID}', ['id' => $vclFile->getId(), 'groupID' => $vclFile->getGroupId()]);

        return 0;
    }

    private function readFile(string $fileName): string
    {
        $content = @file_get_contents($fileName);
        if (false === $content) {
            throw new \RuntimeException("Could not read file {$fileName}");
        }

        return $content;
    }
}
