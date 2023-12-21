<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools;

use Exception;
use Symfony\Component\HttpFoundation\File\File;

interface VirusCheckInterface
{
    /**
     * Scan a specific file for a virus.
     *
     * @throws Exception
     */
    public function hasVirus(File $file): bool;
}
