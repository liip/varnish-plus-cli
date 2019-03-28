<?php

declare(strict_types=1);

namespace App\Command;

use App\VclTwigCompiler;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class VclTwigCompileCommand extends Command
{
    protected static $defaultName = 'vcl:twig:compile';

    protected function configure(): void
    {
        $this
            ->setDescription('Compile the VCL files using twig')
            ->addArgument('templateDir', InputArgument::REQUIRED, 'Template directory with twig templates')
            ->addArgument('rootTemplate', InputArgument::REQUIRED, 'Template name to compile, relative to templateDir')
            ->addArgument('filename', InputArgument::REQUIRED, 'Target filename for the compiled VCL')
            ->addOption('twig-variable', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Define a variable for twig as key=value, e.g. maintenance=1. Repeat option to define several variables.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($output, [
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        ]);

        $options = [];
        foreach ($this->getInputArray($input, 'twig-variable') as $variableDefinition) {
            $variable = explode('=', $variableDefinition, 2);
            if (2 !== \count($variable)) {
                throw new InvalidArgumentException(sprintf('Variable definition "%s" is not in the form of key=value', $variableDefinition));
            }
            $options[$variable[0]] = $variable[1];
        }

        $templateDir = $this->getInputString($input, 'templateDir');
        $rootTemplate = $this->getInputString($input, 'rootTemplate');
        $filename = $this->getInputString($input, 'filename');

        $loader = new FilesystemLoader([$templateDir]);
        $twig = new Environment($loader);

        $vclTwigCompiler = new VclTwigCompiler($twig);

        $vclTwigCompiler->compile($rootTemplate, $filename, $options);

        $logger->log('info', 'Compiled VCL template {template} to {filename}', ['template' => $rootTemplate, 'filename' => $filename]);

        return 0;
    }

    private function getInputArray(InputInterface $input, string $name): array
    {
        $array = $input->getOption($name);
        if (!\is_array($array)) {
            throw new InvalidArgumentException($name.' is expected to be an array');
        }

        return $array;
    }

    private function getInputString(InputInterface $input, string $name): string
    {
        $argument = $input->getArgument($name);
        if (\is_array($argument)) {
            throw new InvalidArgumentException($name.' needs to be a single value');
        }

        return (string) $argument;
    }
}
