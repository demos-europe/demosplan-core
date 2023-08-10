<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\UserFilterSet;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserFilterSetRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;

class UserFilterSetService extends CoreService
{
    public function __construct(private readonly ProcedureRepository $procedureRepository, private readonly UserFilterSetRepository $userFilterSetRepository, private readonly UserRepository $userRepository)
    {
    }

    /**
     * @param string $procedureId
     * @param string $userId
     * @param string $name
     *
     * @return bool
     */
    public function saveUserFilterSet($procedureId, $userId, $name, HashedQuery $filterSet)
    {
        $user = $this->userRepository->find($userId);
        $procedure = $this->procedureRepository->find($procedureId);

        $userFilterSet = new UserFilterSet();

        $userFilterSet->setName($name);
        $userFilterSet->setUser($user);
        $userFilterSet->setFilterSet($filterSet);
        $userFilterSet->setProcedure($procedure);

        $this->getLogger()->debug('saveUserFilterSet()', ['procedureId' => $procedureId, 'name' => $name]);

        return $this->userFilterSetRepository->addObject($userFilterSet);
    }

    /**
     * @param string $userFilterSetId ID to identifies the Entity to delete
     *
     * @return bool true if successfully deleted the given entity, otherwise false
     */
    public function deleteUserFilterSet($userFilterSetId): bool
    {
        // check if userfilter set is owned by current user?!
        $userFilterSet = $this->userFilterSetRepository->get($userFilterSetId);

        return $this->userFilterSetRepository->deleteObject($userFilterSet);
    }
}
