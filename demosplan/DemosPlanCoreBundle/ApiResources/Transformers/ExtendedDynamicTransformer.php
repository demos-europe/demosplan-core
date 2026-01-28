<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ApiResources\Transformers;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\ClaimResource;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\StateProvider\ClaimStateProvider;
use EDT\JsonApi\OutputHandling\DynamicTransformer;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\Wrapping\ResourceBehavior\ResourceReadability;
use Exception;
use League\Fractal\TransformerAbstract;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Extended DynamicTransformer that bridges EDT to API Platform for specific relationships.
 *
 * This allows gradual migration from EDT to API Platform by:
 * - Keeping Statement in EDT (uses this transformer)
 * - Using API Platform ClaimResource for assignee relationship
 * - No ClaimResourceType needed
 *
 * How it works:
 * 1. Statement uses this transformer (via StatementResourceType.getTransformer())
 * 2. When assignee is included, createRelationshipTransformer() is called
 * 3. Detects it's Claim by checking type name
 * 4. Returns inline API Platform transformer instead of EDT DynamicTransformer
 * 5. Assignee data comes from ClaimStateProvider + ClaimResource (API Platform)
 */
class ExtendedDynamicTransformer extends DynamicTransformer
{
    public function __construct(
        string $typeName,
        string $entityClass,
        ResourceReadability $readability,
        MessageFormatter $messageFormatter,
        ?LoggerInterface $logger,
        private readonly ?NormalizerInterface $normalizer,
    ) {
        parent::__construct($typeName, $entityClass, $readability, $messageFormatter, $logger);
    }

    /**
     * Override vendor's createRelationshipTransformer() to support API Platform resources.
     *
     * This is the key method that enables the EDT → API Platform bridge.
     *
     * Vendor location: EDT\JsonApi\OutputHandling\DynamicTransformer::createRelationshipTransformer()
     * Vendor line: 232
     *
     * Flow:
     * 1. Check if relationship is already a TransformerAbstract → return directly
     * 2. Check if relationship type is 'Claim' (duck typing) → use API Platform
     * 3. Otherwise → use parent's EDT logic (backward compatibility)
     *
     * @param mixed $relationshipType The relationship type (EDT ResourceType or other)
     *
     * @return TransformerAbstract The transformer to use for this relationship
     *
     * @throws LogicException If API Platform dependencies not set when needed
     */
    protected function createRelationshipTransformer($relationshipType): TransformerAbstract
    {
        if ($relationshipType instanceof ApiPlatformRelationshipConfig) {
            return $this->createApiPlatformTransformer(
                $relationshipType->getStateProvider(),   // ⭐ From config
                $relationshipType->getResourceClass(),   // ⭐ From config
                $relationshipType->getTypeName()         // ⭐ From config
            );
        }

        return parent::createRelationshipTransformer($relationshipType);
    }

    /**
     * Create inline API Platform transformer for Claim resources.
     *
     * Returns an anonymous Fractal transformer that:
     * 1. Receives User entity
     * 2. Calls ClaimStateProvider to get ClaimResource (API Platform)
     * 3. Normalizes ClaimResource using Symfony serializer
     * 4. Returns simple array (Fractal-compatible)
     *
     * This transformer is created inline to keep everything in one file.
     *
     * @return TransformerAbstract Anonymous transformer that bridges to API Platform
     */
    private function createApiPlatformTransformer(
        ProviderInterface $stateProvider,  // ⭐ Accept as parameter (not property)
        string $resourceClass,
        string $typeName,
    ): TransformerAbstract {
        // Capture dependencies for the closure
        $normalizer = $this->normalizer;

        // Return anonymous transformer class
        return new class($stateProvider, $normalizer, $resourceClass, $typeName) extends TransformerAbstract {
            public function __construct(
                private readonly ProviderInterface $stateProvider,
                private readonly NormalizerInterface $normalizer,
                private readonly string $resourceClass,
                private readonly string $typeName,
            ) {
            }

            /**
             * Transform User entity to Claim data using API Platform.
             *
             * This is called by Fractal when the assignee relationship is included.
             *
             * Flow:
             * 1. Create API Platform operation metadata
             * 2. Call ClaimStateProvider.provide() with user ID
             * 3. Receive ClaimResource (API Platform DTO)
             * 4. Normalize using Symfony serializer (same as API Platform endpoints)
             * 5. Return simple array for Fractal
             *
             * @param object|null $entity The entity to transform (User, StatementVote, Department, etc.)
             *
             * @return array The transformed API Platform resource data
             */
            public function transform($entity): array
            {
                // Handle null case
                if (null === $entity) {
                    return [
                        'id'       => null,
                    ];
                }

                try {
                    // Step 1: Create API Platform operation metadata
                    // This tells API Platform what operation we're performing
                    $operation = new Get(
                        class: $this->resourceClass,
                        provider: $this->stateProvider::class
                    );

                    // Step 2: Use API Platform state provider to get the resource
                    // This calls ClaimStateProvider->provide() which converts User → ClaimResource
                    $claimResource = $this->stateProvider->provide(
                        $operation,
                        ['id' => $entity->getId()],
                        []
                    );

                    // If state provider returns null, return empty data
                    if (null === $claimResource) {
                        return [
                            'id'       => $entity->getId(),
                        ];
                    }

                    // Step 3: Normalize using API Platform's serializer
                    // This uses the same serialization logic as /api/3.0/claim_resources/{id}
                    // The normalizer handles JSON:API format, serialization groups, etc.
                    $normalized = $this->normalizer->normalize(
                        $claimResource,
                        'jsonapi',
                        [
                            'resource_class' => $this->resourceClass,
                            'operation'      => $operation,
                        ]
                    );

                    // Step 4: Extract data from JSON:API structure
                    // API Platform normalizer returns: ['id' => '...', 'attributes' => [...]]
                    // Fractal expects flat structure: ['id' => '...', 'name' => '...', ...]

                    $data = [];

                    if (isset($normalized['data']) && isset($normalized['data']['attributes'])) {
                        $data = $normalized['data']['attributes'];
                        $data['id'] = $normalized['data']['attributes']['_id'];
                        unset($data['_id']);
                    }

                    return $data;
                } catch (Exception $e) {
                    // Log error but don't break the entire response
                    // Return minimal data so the API response is still valid
                    error_log(sprintf(
                        'Error transforming Claim resource for user %s: %s',
                        $entity->getId(),
                        $e->getMessage()
                    ));

                    return [
                        'id'       => $entity->getId(),
                        'name'     => $entity->getName(),
                        'orgaName' => $entity->getOrgaName(),
                    ];
                }
            }

            /**
             * Get the type name for JSON:API responses.
             */
            public function getType(): string
            {
                return 'Claim';
            }
        };
    }
}
