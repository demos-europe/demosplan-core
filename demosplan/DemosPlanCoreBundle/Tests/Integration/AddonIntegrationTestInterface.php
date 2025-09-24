<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tests\Integration;

use Symfony\Component\DependencyInjection\ContainerInterface;

interface AddonIntegrationTestInterface
{
    public function getAddonName(): string;

    public function getTestName(): string;

    public function runIntegrationTest(ContainerInterface $container): AddonTestResult;

    public function setupTestData(ContainerInterface $container, ?object $testCase = null): void;

    public function cleanupTestData(ContainerInterface $container): void;
}