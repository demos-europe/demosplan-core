<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssignService extends CoreService
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Check if the given Statement is assigned to the given user.
     *
     * @return bool if the assignee of the statement is the given user (same instance)
     *              or both their IDs are set with a non-null value and are the same
     */
    public function isStatementObjectAssignedToUser(Statement $statement, User $user): bool
    {
        $assignee = $statement->getAssignee();
        // assumes that $user can never be null at this point
        if ($assignee === $user) {
            return true;
        }

        if (null === $assignee) {
            return false;
        }

        // needed to prevent null === null returns
        $assigneeId = $assignee->getId();
        if (null === $assigneeId) {
            return false;
        }

        return $user->getId() === $assigneeId;
    }

    /**
     * Check if the given Statement is assigned to the current user.
     */
    public function isStatementObjectAssignedToCurrentUser(Statement $statement): bool
    {
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface) {
            return false;
        }
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->isStatementObjectAssignedToUser($statement, $user);
    }
}
