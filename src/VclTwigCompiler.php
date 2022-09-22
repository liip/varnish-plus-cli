<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

class VclTwigCompiler
{
    public const MAINTENANCE_FLAG = 'maintenance';

    /**
     * @var Environment
     */
    private $twig;

    public function __construct(
        Environment $twig
    ) {
        $this->twig = $twig;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function compile(string $template, string $filename, array $context = []): void
    {
        $filesystem = new Filesystem();
        $targetFilename = $filesystem->readlink($filename);
        if (null === $targetFilename) {
            $targetFilename = $filename;
        }

        $filesystem->mkdir(\dirname($targetFilename));

        $content = $this->twig->render($template, $context);
        $filesystem->dumpFile($targetFilename, $content);
    }
}
