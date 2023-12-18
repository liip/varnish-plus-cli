<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class BaseDeployCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('vcl', InputArgument::REQUIRED, 'VCL file to deploy')
            ->addOption('uri', 'u', InputOption::VALUE_REQUIRED, 'URI to deploy to [required]')
            ->addOption('username', '', InputOption::VALUE_REQUIRED, 'Username [required]')
            ->addOption('password', '', InputOption::VALUE_REQUIRED, 'Password [required]')
            ->addOption('vcl-name', '', InputOption::VALUE_REQUIRED, 'VCL name [required]')
            ->addOption('vcl-group', '', InputOption::VALUE_REQUIRED, 'VCL group [required]')
            ->addOption('verify-tls', '', InputOption::VALUE_REQUIRED, 'Specifies TLS verification, true|false|/path/to/certificate. See http://docs.guzzlephp.org/en/stable/request-options.html#verify for possible options', 'true')
        ;
    }

    protected function getArgumentString(InputInterface $input, string $name): string
    {
        $argument = $input->getArgument($name);
        if (\is_array($argument)) {
            throw new InvalidArgumentException($name.' needs to be a single value');
        }

        return (string) $argument;
    }

    protected function requireStringOption(InputInterface $input, string $name): string
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

    protected function convertToBoolean(string $value): bool
    {
        return match ($value) {
            'true' => true,
            'false' => false,
            default => (bool) $value,
        };
    }
}
