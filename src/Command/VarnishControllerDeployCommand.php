<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\VclFile;
use App\VacClient;
use App\VarnishControllerClient;
use Pnz\JsonException\Json;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class VarnishControllerDeployCommand extends Command
{
    protected static $defaultName = 'varnish:controller:deploy';
    private VarnishControllerClient $client;
    private ConsoleLogger $logger;

    protected function configure(): void
    {
        $this
            ->setDescription('Deploy compiled VCL')
            ->addArgument('vcl', InputArgument::REQUIRED, 'VCL file to deploy')
            ->addOption('uri', 'u', InputOption::VALUE_REQUIRED, 'VAC uri to deploy to [required]')
            ->addOption('username', '', InputOption::VALUE_REQUIRED, 'VAC username [required]')
            ->addOption('password', '', InputOption::VALUE_REQUIRED, 'VAC password [required]')
            ->addOption('vcl-name', '', InputOption::VALUE_REQUIRED, 'VCL name [required]')
            ->addOption('vcl-group', '', InputOption::VALUE_REQUIRED, 'VCL group [required]')
            ->addOption('verify-tls', '', InputOption::VALUE_REQUIRED, 'Specifies TLS verification, true|false|/path/to/certificate. See http://docs.guzzlephp.org/en/stable/request-options.html#verify for possible options', 'true')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger = new ConsoleLogger($output, [
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        ]);

        $vclInput = $this->getArgumentString($input, 'vcl');
        $uri = $this->requireStringOption($input, 'uri');
        $username = $this->requireStringOption($input, 'username');
        $password = $this->requireStringOption($input, 'password');
        $vclName = $this->requireStringOption($input, 'vcl-name');
        $vclGroup = $this->requireStringOption($input, 'vcl-group');
        $verifyTLS = $this->convertToBoolean($this->requireStringOption($input, 'verify-tls'));

        $this->client = new VarnishControllerClient($uri, $username, $password, $verifyTLS);
        $rollbackID = '';

        $groupID = $this->client->getGroupID($vclGroup);

        $vclFile = $this->initVclFile();

        $newVclSource = $this->readFile($vclInput);

        if (!$newVclSource) {
            throw new \InvalidArgumentException(sprintf('Filename "%s" is not readable.', $vclInput));
        }

        if ($newVclSource === $vclFile->getSource()) {
            $this->logger->log('info', 'VCL did not change, will not update');
        } else {
            //todo rollback
            //$rollbackID = $this->client->getHead($vclFile->getId());
            //$this->>logger->log('info', 'Using VCL {id} which head is {rollbackID}', ['id' => $vclFile->getId(), 'rollbackID' => $rollbackID]);

            $vclCommit = $this->client->updateVCL($vclFile->getId(), $newVclSource);
            $this->logger->log('info', 'Pushed new VCL with the commit ID {id}', ['id' => $vclCommit]);
        }


        if (!$groupID) {
            throw new InvalidOptionException($vclGroup.' needs to be a valid group name');
        }

        $this->logger->log(
            'info', 'Deploying VCL {id} to group {groupID}', ['id' => $vclFile->getId(), 'groupID' => $groupID]);

        try {
            $this->client->deploy($groupID, $vclFile->getId());
            $this->logger->log('info', 'Successfully deployed VCL {id} to group {groupID}', ['id' => $vclFile->getId(), 'groupID' => $groupID]);

            return 0;
        } catch (\ErrorException $errorException) {
            $this->logger->log('error', 'Deployment failed');

            //todo rollback
            //$id = $this->client->rollback($vclFile->getId(), $rollbackID);
            //$this->>logger->log('info', 'Rolled VCL {vclID} back to {id}', ['vclID' => $vclFile->getId(), 'id' => $id]);

            return 1;
        }
    }

    private function initVclFile(string $vclName, int $groupId): VclFile
    {
        $vclFile = VclFile::fromArray(
            $this->client->getVclIdAndSource($vclName)
        );

        if (!$vclFile->getId()) {
            $vclFile = VclFile::fromArray(
                $this->client->createEmptyVCL($vclName,$groupId)
            );
            $this->logger->log('info', 'Created default VCL for {name} as ID {id}', ['name' => $vclName, 'id' => $vclFile->getId()]);
        }
        return $vclFile;
    }

    private function getArgumentString(InputInterface $input, string $name): string
    {
        $argument = $input->getArgument($name);
        if (\is_array($argument)) {
            throw new InvalidArgumentException($name.' needs to be a single value');
        }

        return (string) $argument;
    }

    private function requireStringOption(InputInterface $input, string $name): string
    {
        $option = $input->getOption($name);
        if (!$option) {
            throw new InvalidOptionException($name.' is required');
        }
        if (!\is_string($option)) {
            throw new InvalidOptionException($name.' must be of type string but is '.\gettype($option));
        }

        return $option;
    }

    private function convertToBoolean(string $value): bool
    {
        switch ($value) {
            case 'true':
                return true;
            case 'false':
                return false;
            default:
                return (bool) $value;
        }
    }

    private function readFile(string $fileName): ?string
    {
        $content = @file_get_contents($fileName);
        if (false === $content) {
            return null;
        }

        return $content;
    }
}
