<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers;

use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeDisplayService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;

use function array_key_exists;

class EntityContentChangeComparisonTransformer extends BaseTransformer
{
    protected $type = 'EntityContentChangeComparison';

    public function __construct(
        private readonly EntityContentChangeDisplayService $entityContentChangeDisplayService,
        private readonly EntityContentChangeService $entityContentChangeService
    ) {
        parent::__construct();
    }

    /**
     * @param array<int, EntityContentChange> $instanceChangeArray
     */
    public function transform(array $instanceChangeArray): array
    {
        $data = collect($instanceChangeArray)
            ->filter($this->isFieldNameWhitelistedForEntityContentChangeComponent(...))
            ->mapWithKeys(function (EntityContentChange $entityContentChange): array {
                $translationKey = $this->entityContentChangeService->getMappingValue(
                    $entityContentChange->getEntityField(),
                    $entityContentChange->getEntityType(),
                    'translationKey'
                );

                // Diff objects of change instance
                return [$translationKey => $this->entityContentChangeDisplayService->getContentChangeComparisonString($entityContentChange)];
            })
            ->all();

        $data['id'] = 'ThisIdIsNeverActuallyUsed';

        return $data;
    }

    /**
     * This method is not used for it's efficiency but for its explanatory name, so that others easily understand
     * what it's doing. Hence, please don't refactor for efficiency.
     */
    public function isFieldNameWhitelistedForEntityContentChangeComponent(EntityContentChange $object): bool
    {
        return array_key_exists($object->getEntityField(), $this->entityContentChangeService->getFieldMapping($object->getEntityType()));
    }
}
