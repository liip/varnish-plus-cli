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

    public function compile(string $template, string $filename, array $options = []): void
    {
        $filesystem = new Filesystem();
        $targetFilename = $filesystem->readlink($filename);
        if (null === $targetFilename) {
            $targetFilename = $filename;
        }

        $filesystem->mkdir(\dirname($targetFilename));

        $content = $this->twig->render($template, $options);
        $filesystem->dumpFile($targetFilename, $content);
    }
}
