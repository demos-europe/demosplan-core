<?php

namespace demosplan\DemosPlanCoreBundle\Addon;

final class AddonInfo
{
    /**
     * @param string $name
     * @param array<string,mixed> $manifest
     */
    public function __construct(private string $name, private array $manifest)
    {
    }

    public function getInstallPath(): string
    {
        return $this->manifest['install_path'];
    }

    public function getName(): string
    {
        return $this->name;
    }
}
