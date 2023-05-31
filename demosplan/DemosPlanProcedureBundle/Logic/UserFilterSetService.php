<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\UserFilterSet;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanProcedureBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanProcedureBundle\Repository\UserFilterSetRepository;

class UserFilterSetService extends CoreService
{
    /**
     * @var ProcedureRepository
     */
    private $procedureRepository;
    /**
     * @var UserFilterSetRepository
     */
    private $userFilterSetRepository;
    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(
        ProcedureRepository $procedureRepository,
        UserFilterSetRepository $userFilterSetRepository,
        UserRepository $userRepository
    ) {
        $this->procedureRepository = $procedureRepository;
        $this->userFilterSetRepository = $userFilterSetRepository;
        $this->userRepository = $userRepository;
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
