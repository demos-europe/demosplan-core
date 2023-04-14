<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanStatementBundle\Repository\PriorityAreaRepository;
use Exception;

class PriorityAreaService extends CoreService
{
    /**
     * @var PriorityAreaRepository
     */
    private $priorityAreaRepository;

    public function __construct(PriorityAreaRepository $priorityAreaRepository)
    {
        $this->priorityAreaRepository = $priorityAreaRepository;
    }

    /**
     * Returns a specific priorityArea.
     *
     * @param string $id - identifies the county
     *
     * @return PriorityArea|null
     */
    public function getPriorityArea($id)
    {
        try {
            $result = $this->priorityAreaRepository->get($id);
        } catch (Exception $e) {
            $this->logger->error('Get PriorityArea with ID: '.$id.' failed: ', [$e]);

            return null;
        }

        return $result;
    }

    /**
     * Returns all priorityAreas.
     *
     * @return PriorityArea[]
     */
    public function getAllPriorityAreas()
    {
        try {
            return $this->priorityAreaRepository->getAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Returns all priorityAreas as JSON String.
     *
     * @return array
     */
    public function getAllPriorityAreasAsArray()
    {
        $priorityAreas = $this->getAllPriorityAreas();

        return \collect($priorityAreas)->map(
            function (PriorityArea $priorityArea) {
                // use 'name' instead of 'key' to make it working in twig
                return ['id' => $priorityArea->getId(), 'name' => $priorityArea->getKey()];
            }
        )
            ->sortBy('name')
            ->values()
            ->toArray();
    }

    /**
     * @param string $key
     *
     * @return array
     */
    public function getPriorityAreasByKey($key)
    {
        return $this->priorityAreaRepository->findBy(['key' => $key]);
    }
}
