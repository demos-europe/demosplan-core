<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use EDT\Wrapping\Contracts\PropertyAccessException;
use EDT\Wrapping\Contracts\RelationshipAccessException;
use EDT\Wrapping\Contracts\TypeRetrievalAccessException;
use EDT\Wrapping\Contracts\Types\TypeInterface;
use EDT\Wrapping\Utilities\PropertyPathProcessor;
use EDT\Wrapping\Utilities\TypeAccessors\AbstractProcessorConfig;
use Psr\Log\LoggerInterface;

class DplanPropertyPathProcessor extends PropertyPathProcessor
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    private AbstractProcessorConfig $processorConfig;

    public function __construct(AbstractProcessorConfig $processorConfig, LoggerInterface $logger)
    {
        parent::__construct($processorConfig);
        $this->logger = $logger;
        $this->processorConfig = $processorConfig;
    }

    /**
     * Simulates old {@link PropertyPathProcessor} behavior in which the last path segment was not
     * validated. But here we at least log invalid segments.
     */
    public function processPropertyPath(TypeInterface $type, array $newPath, string $currentPathPart, string ...$remainingParts): array
    {
        // Check if the current type needs mapping to the backing object schema, if so, apply it.
        $pathToAdd = $this->processorConfig->getDeAliasedPath($type, $currentPathPart);
        // append the de-aliased path to the processed path
        array_push($newPath, ...$pathToAdd);

        if ([] === $remainingParts) {
            try {
                $propertyTypeIdentifier = $this->getPropertyTypeIdentifier($type, $currentPathPart);
                if (null !== $propertyTypeIdentifier) {
                    $this->processorConfig->getRelationshipType($propertyTypeIdentifier);
                }
            } catch (PropertyAccessException|TypeRetrievalAccessException $exception) {
                $this->logger->warning($exception->getMessage(), ['exception' => $exception]);
            }

            return $newPath;
        }

        $propertyTypeIdentifier = $this->getPropertyTypeIdentifier($type, $currentPathPart);
        if (null !== $propertyTypeIdentifier) {
            try {
                // even if we don't need the $nextTarget here because there may be no
                // remaining segments, we still check with this call if the current
                // relationship is valid in this path
                $nextTarget = $this->processorConfig->getRelationshipType($propertyTypeIdentifier);

                // otherwise, we continue the mapping recursively
                return $this->processPropertyPath($nextTarget, $newPath, ...$remainingParts);
            } catch (TypeRetrievalAccessException $exception) {
                throw RelationshipAccessException::relationshipTypeAccess($type, $currentPathPart, $exception);
            }
        }

        // the current segment is an attribute followed by more segments,
        // thus we throw an exception
        throw PropertyAccessException::nonRelationship($currentPathPart, $type);
    }
}
