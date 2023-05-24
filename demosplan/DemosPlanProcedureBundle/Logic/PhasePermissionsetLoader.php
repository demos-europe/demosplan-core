<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Logic;

use function array_key_exists;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;

class PhasePermissionsetLoader
{
    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    public function __construct(GlobalConfig $globalConfig)
    {
        $this->globalConfig = $globalConfig;
    }

    public function loadPhasePermissionsets(Procedure $procedure): Procedure
    {
        $internalPermissionset = $this->getInternalPhasePermissionset($procedure) ?? '';
        $procedure->setPhasePermissionset($internalPermissionset);
        $externalPermissionset = $this->getExternalPhasePermissionset($procedure) ?? '';
        $procedure->setPublicParticipationPhasePermissionset($externalPermissionset);

        return $procedure;
    }

    /**
     * The returned value (`'hidden'`, `'read'` or `'write'`) describes if a user can
     * see a procedure and submit statements. Which value is returned depends on the **internal**
     * phase set in the given {@link Procedure} and the configuration loaded from the
     * `procedurephases.yml`. The three values are interpreted as follows:.
     *
     * * `'hidden'`: invitable institutions ("Institutionen") are not allowed to even see the procedure or its
     *   planning documents; participation (i.e. submitting statements) is also not allowed
     * * `'read'`: invitable institutions ("Institutionen") that are invited into the procedure are allowed to see
     *   the procedure and its (visible) planning documents, but are not allowed to participate
     *   (i.e. submit statements) at all
     * * `'write'`: invitable institutions ("Institutionen") that are invited into the procedure are allowed to see
     *   the procedure and its (visible) planning documents and to participate (i.e. submit statements)
     *
     * The value returned by this method makes no statement about the handling of other users than
     * public agencies ({@link User::isPublicAgency()} returns true). Planners ({@link User::isPlanner()}
     * returns `true`) must always be allowed for procedures they own, as described by `'write'`. See
     * {@link PhasePermissionsetLoader::getExternalPhasePermissionset} regarding the access of citizens and guests.
     */
    public function getInternalPhasePermissionset(Procedure $procedure): ?string
    {
        $internalPhases = $this->globalConfig->getInternalPhasesAssoc();
        $internalPhaseIdentifier = $procedure->getPhase();
        if (array_key_exists($internalPhaseIdentifier, $internalPhases)) {
            return $internalPhases[$internalPhaseIdentifier]['permissionset'] ?? null;
        }

        return Procedure::PROCEDURE_PHASE_PERMISSIONSET_HIDDEN;
    }

    /**
     * The returned value (`'hidden'`, `'read'` or `'write'`) describes if a user can
     * see a procedure and submit statements. Which value is returned depends on the **external**
     * phase set in the given {@link Procedure} and the configuration loaded from the
     * `procedurephases.yml`. The three values are interpreted as follows:.
     *
     * * `'hidden'`: guests and citizens are not allowed to even see the procedure or its
     *   planning documents; participation (i.e. submitting statements) is also not allowed
     * * `'read'`: guests and citizens are allowed to see the procedure and its (visible) planning
     *   documents, but are not allowed to participate (i.e. submit statements) at all
     * * `'write'`: guests and citizens are allowed to see the procedure and its (visible) planning
     *   documents and to participate (i.e. submit statements)
     *
     * The value returned by this method makes no statement about the handling of other users than
     * guests and citizens ({@link User::isPublicAgency()} returns true). Planners ({@link User::isPlanner()}
     * returns `true`) must always be allowed for procedures they own, as described by `'write'`. See
     * {@link PhasePermissionsetLoader::getInternalPhasePermissionset} regarding the access of invitable institutions ("Institutionen").
     *
     * To determine if this method is to be used, use {@link User::isPublicUser}.
     */
    public function getExternalPhasePermissionset(Procedure $procedure): ?string
    {
        $externalPhases = $this->globalConfig->getExternalPhasesAssoc();
        $externalPhaseIdentifier = $procedure->getPublicParticipationPhase();
        if (array_key_exists($externalPhaseIdentifier, $externalPhases)) {
            return $externalPhases[$externalPhaseIdentifier]['permissionset'] ?? null;
        }

        return Procedure::PROCEDURE_PHASE_PERMISSIONSET_HIDDEN;
    }
}
