#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Command\VclDeployCommand;
use App\Command\VclTwigCompileCommand;
use Symfony\Component\Console\Application;

$application = new Application('Varnish Plus CLI');

$application->add(new VclTwigCompileCommand());
$application->add(new VclDeployCommand());

$application->run();
