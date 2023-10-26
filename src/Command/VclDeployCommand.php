<?php

declare(strict_types=1);

namespace App\Command;

use App\VacClient;
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

class VclDeployCommand extends Command
{
    protected static $defaultName = 'vcl:deploy';

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
        $logger = new ConsoleLogger($output, [
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        ]);

        $vcl = $this->getArgumentString($input, 'vcl');
        $uri = $this->requireStringOption($input, 'uri');
        $username = $this->requireStringOption($input, 'username');
        $password = $this->requireStringOption($input, 'password');
        $vclName = $this->requireStringOption($input, 'vcl-name');
        $vclGroup = $this->requireStringOption($input, 'vcl-group');
        $verifyTLS = $this->convertToBoolean($this->requireStringOption($input, 'verify-tls'));

        $client = new VacClient($uri, $username, $password, $verifyTLS);
        $rollbackID = '';

        [$vclID, $content] = $client->getVclID($vclName);
        if (!$vclID) {
            [$vclID, $content] = $client->createEmptyVCL($vclName);
            $logger->log('info', 'Created default VCL for {name} as ID {id}', ['name' => $vclName, 'id' => $vclID]);
        }

        $newVCL = $this->readFile($vcl);
        if (!$newVCL) {
            throw new \InvalidArgumentException(sprintf('Filename "%s" is not readable.', $vcl));
        }
        if ($newVCL === $content) {
            $logger->log('info', 'VCL did not change, will not update');
        } else {
            $rollbackID = $client->getHead($vclID);
            $logger->log('info', 'Using VCL {id} which head is {rollbackID}', ['id' => $vclID, 'rollbackID' => $rollbackID]);

            $vclCommit = $client->updateVCL($vclID, $newVCL);
            $logger->log('info', 'Pushed new VCL with the commit ID {id}', ['id' => $vclCommit]);
        }

        $groupID = $client->getGroupID($vclGroup);
        if (!$groupID) {
            throw new InvalidOptionException($vclGroup.' needs to be a valid group name');
        }

        $logger->info('Deploying VCL {id} to group {groupID}', ['id' => $vclID, 'groupID' => $groupID]);
        [$success, $compilationData, $deployData] = $client->deploy($groupID, $vclID);
        if (!$success) {
            $logger->log('error', 'Deployment failed');
            $logger->log('error', 'Compilation errors: {errors}', ['errors' => Json::encode($compilationData)]);
            $logger->log('error', 'Deployment errors: {errors}', ['errors' => Json::encode($deployData)]);

            $id = $client->rollback($vclID, $rollbackID);
            $logger->log('info', 'Rolled VCL {vclID} back to {id}', ['vclID' => $vclID, 'id' => $id]);

            return 1;
        }

        $logger->log('info', 'Successfully deployed VCL {id} to group {groupID}', ['id' => $vclID, 'groupID' => $groupID]);
        $logger->log('info', 'Compilation data: {data}', ['data' => Json::encode($compilationData)]);
        $logger->log('info', 'Deployment data: {data}', ['data' => Json::encode($deployData)]);

        return 0;
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
