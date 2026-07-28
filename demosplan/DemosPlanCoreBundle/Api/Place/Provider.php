<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Place;

use demosplan\DemosPlanCoreBundle\Api\AbstractDoctrineResourceProvider;
use demosplan\DemosPlanCoreBundle\Api\Place\Resource as PlaceResource;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;

/**
 * @template-extends AbstractDoctrineResourceProvider<Place, PlaceResource>
 */
class Provider extends AbstractDoctrineResourceProvider
{
    public function __construct(
        AccessChecker $accessChecker,
        PlaceRepository $placeRepository,
        SortMethodFactory $sortMethodFactory,
    ) {
        parent::__construct($accessChecker, $placeRepository, $sortMethodFactory);
    }

    protected function getResourceClass(): string
    {
        return PlaceResource::class;
    }

    protected function getSortableProperties(): array
    {
        return ['sortIndex'];
    }

    protected function mapToResource(object $entity): object
    {
        return PlaceResource::fromEntity($entity);
    }
}
