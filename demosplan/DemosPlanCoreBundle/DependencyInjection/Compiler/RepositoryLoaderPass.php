<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;

/**
 * This class is a custom compiler pass for Symfony's Dependency Injection component.
 * It modifies the service definitions for Doctrine repositories and automatically sets
 * the entity class argument.
 */
class RepositoryLoaderPass implements CompilerPassInterface
{
    /**
     * Processes the ContainerBuilder to manipulate the service definitions.
     */
    public function process(ContainerBuilder $container): void
    {
        // Find all services tagged with 'doctrine.repository_service'
        $repositories = $container->findTaggedServiceIds('doctrine.repository_service');

        foreach ($repositories as $id => $tags) {
            // Skip if the service id does not match the pattern
            if (!preg_match('|^(.*)\\\\(.*)Repository$|', $id, $matches)) {
                continue;
            }

            // Extract the repository name from the service id
            $repositoryName = $matches[2];

            // Find the class file for the repository
            $className = $this->findClassFile($repositoryName);

            // Skip if the class file could not be found
            if (null === $className) {
                continue;
            }

            // Get the service definition
            $definition = $container->findDefinition($id);

            // If the service is a Doctrine repository, set the entity class argument
            if ($definition->getClass()) {
                try {
                    $definition->setArgument('$entityClass', $className);
                } catch (Exception) {
                    // no need to catch it here, special cases are handled via services_repositories.yml
                }
            }
        }
    }

    /**
     * Finds the class file for a given class name.
     *
     * @param string $className the name of the class to find
     *
     * @return string|null the fully qualified class name if the file is found, null otherwise
     */
    private function findClassFile(string $className): ?string
    {
        // Create a new Finder instance
        $finder = new Finder();

        // Configure the Finder to search for the class file
        $finder->files()->name($className.'.php')->in(DemosPlanPath::getRootPath('demosplan/DemosPlanCoreBundle/Entity'));

        // Iterate over the (one) Finder result
        foreach ($finder as $file) {
            // Get the relative path of the file
            $relative = $file->getRelativePathname();

            // Return the fully qualified class name
            return sprintf(
                '%s%s',
                'demosplan\DemosPlanCoreBundle\Entity\\',
                str_replace(['/', '.php'], ['\\', ''], $relative)
            );
        }

        // If no file was found, return null
        return null;
    }
}
