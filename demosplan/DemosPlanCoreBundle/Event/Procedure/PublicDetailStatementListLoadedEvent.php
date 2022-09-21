<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use Tightenco\Collect\Support\Collection;

class PublicDetailStatementListLoadedEvent extends DPlanEvent
{
    /**
     * @var Collection
     */
    protected $statements;

    /**
     * @var Collection
     */
    protected $likedStatementIds;
    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;
    /**
     * @var User
     */
    protected $user;

    /**
     * Scope for getting statementlist from Cookie @todo feels bad.
     *
     * @var string
     */
    protected $scope = 'statementId';

    public function __construct(
        Collection $statements,
        \Symfony\Component\HttpFoundation\Request $request,
        User $user
    ) {
        $this->statements = $statements;
        $this->request = $request;
        $this->user = $user;
        $this->likedStatementIds = collect();
    }

    /**
     * @return \Tightenco\Collect\Support\Collection
     */
    public function getStatements()
    {
        return $this->statements;
    }

    /**
     * @param \Tightenco\Collect\Support\Collection $statements
     */
    public function setStatements($statements)
    {
        $this->statements = $statements;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * @return Collection
     */
    public function getLikedStatementIds()
    {
        return $this->likedStatementIds;
    }

    /**
     * @param Collection $likedStatementIds
     */
    public function setLikedStatementIds($likedStatementIds)
    {
        $this->likedStatementIds = $likedStatementIds;
    }
}
