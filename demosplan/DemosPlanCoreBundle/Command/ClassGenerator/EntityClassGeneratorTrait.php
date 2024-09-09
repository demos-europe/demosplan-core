<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\ClassGenerator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use RuntimeException;
use Webmozart\Assert\Assert;

trait EntityClassGeneratorTrait
{
    /**
     * @return list<class-string>
     */
    protected function getEntities(EntityManagerInterface $entityManager): array
    {
        $classes = array_map(
            fn (ClassMetadata $metadata): string => $metadata->getName(),
            $entityManager->getMetadataFactory()->getAllMetadata()
        );

        Assert::allClassExists($classes);

        return array_values($classes);
    }

    protected function overwriteFile(string $directoryPath, string $className, string $content): void
    {
        if (!is_dir($directoryPath)
            && !mkdir($directoryPath, 0777, true)
            && !is_dir($directoryPath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directoryPath));
        }

        // local file is valid, no need for flysystem
        file_put_contents("$directoryPath/$className.php", $content);
    }
}
