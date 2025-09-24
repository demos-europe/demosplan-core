<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tests\Integration;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class AddonIntegrationTestRegistry
{
    /** @var AddonIntegrationTestInterface[] */
    private iterable $addonTests;

    public function __construct(
        #[TaggedIterator('addon_integration_test')]
        iterable $addonTests
    ) {
        $this->addonTests = $addonTests;
    }

    /**
     * @return AddonIntegrationTestInterface[]
     */
    public function getAddonTests(): iterable
    {
        return $this->addonTests;
    }

    public function getAddonTest(string $addonName): ?AddonIntegrationTestInterface
    {
        foreach ($this->addonTests as $addonTest) {
            if ($addonTest->getAddonName() === $addonName) {
                return $addonTest;
            }
        }

        return null;
    }
}