<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\MunicipalityRepository;
use Exception;

class MunicipalityService extends CoreService
{
    public function __construct(private readonly MunicipalityRepository $municipalityRepository)
    {
    }

    /**
     * Returns all municipalities.
     *
     * @return Municipality[]
     */
    public function getAllMunicipalities()
    {
        try {
            return $this->municipalityRepository->getAll();
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Returns all municipalities as JSON string.
     *
     * @return array
     */
    public function getAllMunicipalitiesAsArray()
    {
        $municipalities = $this->getAllMunicipalities();

        return \collect($municipalities)->map(
            fn(Municipality $municipality) => ['id' => $municipality->getId(), 'name' => $municipality->getName()]
        )
            ->sortBy('name')
            ->values()
            ->toArray();
    }

    /**
     * Returns a specific municipality.
     *
     * @param string $id - identifies the county
     *
     * @return Municipality|null
     */
    public function getMunicipality($id)
    {
        try {
            $result = $this->municipalityRepository->get($id);
        } catch (Exception $e) {
            $this->logger->error('Get Municipality with ID: '.$id.' failed: ', [$e]);

            return null;
        }

        return $result;
    }

    /**
     * @return Municipality
     *
     * @throws Exception
     */
    public function addMunicipality(array $data)
    {
        return $this->municipalityRepository->add($data);
    }
}
