<?php

declare(strict_types=1);

namespace Claudriel\Domain\Pipeline\Pdf;

use Claudriel\Entity\PipelineConfig;
use Claudriel\Entity\Prospect;

interface PdfGeneratorInterface
{
    /**
     * Generate a PDF for the given prospect using workspace branding.
     *
     * @return string Path to the generated PDF file.
     */
    public function generate(Prospect $prospect, PipelineConfig $config): string;

    /**
     * Whether this generator is available on the current system.
     */
    public function isAvailable(): bool;
}
