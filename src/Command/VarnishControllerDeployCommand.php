<?php

declare(strict_types=1);

namespace App\Command;

use App\VarnishControllerClient;
use App\VclDeployer;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

final class VarnishControllerDeployCommand extends BaseDeployCommand
{
    protected function configure(): void
    {
        $this
            ->setName('varnish-controller:deploy')
            ->setDescription('Deploy compiled VCL to the Varnish Controller')
            ->addOption('organization', '', InputOption::VALUE_REQUIRED, 'Organization [required]')
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
        $organization = $this->requireStringOption($input, 'organization');
        $username = $this->requireStringOption($input, 'username');
        $password = $this->requireStringOption($input, 'password');
        $vclName = $this->requireStringOption($input, 'vcl-name');
        $vclGroup = $this->requireStringOption($input, 'vcl-group');
        $verifyTLS = $this->convertToBoolean($this->requireStringOption($input, 'verify-tls'));

        $client = new VarnishControllerClient($uri, $username, $password, $organization, $verifyTLS);

        return (new VclDeployer($client))->deploy($logger, $vcl, $vclName, $vclGroup);
    }
}
