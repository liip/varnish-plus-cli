<?php

declare(strict_types=1);

namespace App\Command;

use App\VacClient;
use App\VclDeployer;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

final class VacDeployCommand extends BaseDeployCommand
{
    protected function configure(): void
    {
        $this
            ->setName('vac:deploy')
            ->setDescription('Deploy compiled VCL to legacy Varnish Admin Console (VAC)')
        ;
        parent::configure();
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

        return (new VclDeployer($client))->deploy($logger, $vcl, $vclName, $vclGroup);
    }
}
