<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

final class VclTwigCompiler
{
    public function __construct(
        private readonly Environment $twig
    ) {
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
