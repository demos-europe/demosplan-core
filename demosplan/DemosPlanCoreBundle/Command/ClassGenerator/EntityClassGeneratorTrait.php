<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Command\ClassGenerator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
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
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $directoryPath));
        }

        file_put_contents("$directoryPath/$className.php", $content);
    }
}
