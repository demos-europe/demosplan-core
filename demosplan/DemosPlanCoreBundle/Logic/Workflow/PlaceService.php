<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Workflow;

use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use Doctrine\ORM\NoResultException;

class PlaceService extends CoreService
{
    /**
     * @var PlaceRepository
     */
    private $placeRepository;

    public function __construct(PlaceRepository $placeRepository)
    {
        $this->placeRepository = $placeRepository;
    }

    /**
     * @throws NoResultException
     */
    public function findWithCertainty(string $id): Place
    {
        return $this->placeRepository->findWithCertainty($id);
    }

    public function findFirstOrderedBySortIndex(string $procedureId): Place
    {
        return $this->placeRepository->findFirstOrderedBySortIndex($procedureId);
    }
}
