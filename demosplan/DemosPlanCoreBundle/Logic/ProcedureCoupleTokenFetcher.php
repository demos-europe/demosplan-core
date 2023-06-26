<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureCoupleToken;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureCoupleTokenRepository;

class ProcedureCoupleTokenFetcher
{
    /**
     * @var ProcedureCoupleTokenRepository
     */
    private $procedureCoupleTokenRepository;

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    public function __construct(
        ProcedureCoupleTokenRepository $procedureCoupleTokenRepository,
        PermissionsInterface $permissions
    ) {
        $this->procedureCoupleTokenRepository = $procedureCoupleTokenRepository;
        $this->permissions = $permissions;
    }

    /**
     * Returns non-`null` if the creation of the
     * given procedure resulted in a token being created. Hence,
     * the returned token can be used to show the token string so that it
     * can be used to create a new procedure the given procedure should be
     * coupled with.
     *
     * If non-`null` is returned, then {@link ProcedureCoupleToken::$sourceProcedure}
     * will always be the given procedure.
     */
    public function getTokenForSourceProcedure(Procedure $sourceProcedure): ?ProcedureCoupleToken
    {
        if (!$this->permissions->hasPermission('feature_procedure_couple_token_autocreate')) {
            return null;
        }

        return $this->procedureCoupleTokenRepository->findOneBy([
            'sourceProcedure' => $sourceProcedure,
        ]);
    }

    /**
     * Returns non-`null` if the given
     * procedure was created from a token. Hence, the return
     * can be used to show with which procedure the current one was coupled
     * with on creation.
     *
     * If non-`null` is returned, then {@link ProcedureCoupleToken::$targetProcedure}
     * will always be the given procedure.
     */
    public function getTokenForTargetProcedure(Procedure $targetProcedure): ?ProcedureCoupleToken
    {
        if (!$this->permissions->hasPermission('feature_procedure_couple_by_token')) {
            return null;
        }

        return $this->procedureCoupleTokenRepository->findOneBy([
            'targetProcedure' => $targetProcedure,
        ]);
    }

    /**
     * Gets a sourceProcedure from a given coupled targetProcedure.
     */
    public function getSourceProcedureFromTokenByTargetProcedure(Procedure $targetProcedure): ?Procedure
    {
        /** @var ProcedureCoupleToken|null $procedureCoupleToken */
        $procedureCoupleToken = $this->procedureCoupleTokenRepository->findOneBy([
            'targetProcedure' => $targetProcedure,
        ]);

        if (null === $procedureCoupleToken) {
            return null;
        }

        return $procedureCoupleToken->getSourceProcedure();
    }

    /**
     * Gets a targetProcedure from a given coupled sourceProcedure.
     */
    public function getTargetProcedureFromTokenBySourceProcedure(Procedure $sourceProcedure): ?Procedure
    {
        /** @var ProcedureCoupleToken|null $procedureCoupleToken */
        $procedureCoupleToken = $this->procedureCoupleTokenRepository->findOneBy([
            'sourceProcedure' => $sourceProcedure,
        ]);

        if (null === $procedureCoupleToken) {
            return null;
        }

        return $procedureCoupleToken->getTargetProcedure();
    }

    public function isSourceAndCoupledProcedure(Procedure $sourceProcedure): bool
    {
        /** @var ProcedureCoupleToken|null $procedureCoupleToken */
        $procedureCoupleToken = $this->procedureCoupleTokenRepository->findOneBy([
            'sourceProcedure' => $sourceProcedure,
        ]);

        if (null === $procedureCoupleToken) {
            return false;
        }

        return null !== $procedureCoupleToken->getTargetProcedure();
    }
}
