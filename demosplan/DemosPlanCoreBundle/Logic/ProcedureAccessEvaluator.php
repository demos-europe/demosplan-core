<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\Querying\Contracts\ConditionFactoryInterface;
use EDT\Querying\Contracts\FunctionInterface;
use Psr\Log\LoggerInterface;

class ProcedureAccessEvaluator
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    /**
     * @var EntityFetcher
     */
    private $entityFetcher;

    /**
     * @var ConditionFactoryInterface
     */
    private $conditionFactory;

    public function __construct(
        DqlConditionFactory $conditionFactory,
        EntityFetcher $entityFetcher,
        GlobalConfigInterface $globalConfig,
        LoggerInterface $logger
    ) {
        $this->globalConfig = $globalConfig;
        $this->logger = $logger;
        $this->entityFetcher = $entityFetcher;
        $this->conditionFactory = $conditionFactory;
    }

    /**
     * ```
     * "invited" werden Institutionen, wenn sie eingeladen werden. Es "ownt" das Verfahren prinzipiell die
     * Orga, die das Verfahren erstellt hat, eingeschränkt durch die explizit ausgewählten
     * Nutzer*innen, der Orga, die in den Grundeinstellungen ausgewählt werden. Werden
     * Planungsbüros dem Verfahren zugewiesen, ownen sie das Verfahren auch.
     * ```.
     *
     * **Keep in sync with {@link ProcedureAccessEvaluator::getOwnedProcedureConditionForResources}**
     */
    public function isOwningProcedure(User $user, Procedure $procedure): bool
    {
        // procedure is deleted
        if ($procedure->isDeleted()) {
            return false;
        }

        $orgaIdOfProcedure = $procedure->getOrgaId();
        $procedurePlanningOffices = $procedure->getPlanningOfficesIds();
        $planningAgencyIsAuthorized = $this->conditionFactory->propertyHasAnyOfValues(
            $procedurePlanningOffices,
            'orga', 'id'
        );

        // Organisation ist Inhaberin
        $orgaOwnsProcedure = $this->conditionFactory->false();
        if (null !== $orgaIdOfProcedure) {
            $this->logger->debug('Permissions: Check whether orga owns procedure');
            // Fachplaner-Admin GLAUTH Kommune oder Fachlplaner SB

            //Fachplaner admin oder Fachplaner Sachbearbeiter oder Plattform-Admin oder AHB-Admin
            $orgaOwnsRoleCondition = $this->getOwnsOrgaRoleCondition();
            if ($this->entityFetcher->objectMatches($user, $orgaOwnsRoleCondition)) {
                $this->logger->debug('User is FP*');
                $orgaOwnsProcedure = $this->conditionFactory->propertyHasValue(
                    $orgaIdOfProcedure,
                    'orga', 'id'
                );

                //T8427:
                if ($this->globalConfig->hasProcedureUserRestrictedAccess()) {
                    $userIsAuthorized = $this->conditionFactory->propertyHasAnyOfValues(
                        $procedure->getAuthorizedUserIds(),
                        'id'
                    );

                    $orgaOwnsProcedure = $this->conditionFactory->anyConditionApplies(
                        $userIsAuthorized,
                        $planningAgencyIsAuthorized
                    );
                }
            }
        }

        // Wurde dem Verfahren ein Planungsbüro zugeordnet?
        $planningAgencyOwnsProcedure = $this->conditionFactory->false();
        if (0 < count($procedurePlanningOffices)) {
            $this->logger->debug('Procedure has PlanningOffices');
            // ist es ein PLanungsbüro?
            $privatePlanningAgency = $this->conditionFactory->propertyHasValue(
                Role::PRIVATE_PLANNING_AGENCY,
                'roleInCustomers', 'role', 'code'
            );
            if ($this->entityFetcher->objectMatches($user, $privatePlanningAgency)) {
                $this->logger->debug('Permissions → User has role RMOPPO');

                $planningAgencyOwnsProcedure = $planningAgencyIsAuthorized;
            }
        }

        if ($this->entityFetcher->objectMatchesAny($user, [
            $orgaOwnsProcedure,
            $planningAgencyOwnsProcedure,
        ])) {
            $this->logger->debug('Permissions → Orga owns procedure');

            return true;
        }

        $this->logger->debug('Permissions → Orga does not own procedure');

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function filterNonOwnedProcedureIds(User $user, Procedure ...$procedures): array
    {
        return collect($procedures)
            ->filter(function (Procedure $procedure) use ($user): bool {
                return $this->isOwningProcedure($user, $procedure);
            })
            ->map(static function (Procedure $procedure): string {
                return $procedure->getId();
            })
            ->all();
    }

    /**
     * Check if the user has {@link Role::PROCEDURE_DATA_INPUT} set as role and its
     * organisation is set as {@link Procedure::$dataInputOrganisations}.
     */
    public function isAllowedAsDataInputOrga(User $user, ?Procedure $procedure): bool
    {
        return null !== $procedure
            && $user->hasRole(Role::PROCEDURE_DATA_INPUT)
            && in_array(
                $user->getOrganisationId(),
                $procedure->getDataInputOrgaIds(),
                true
            );
    }

    /**
     * Returns a condition to match users having the roles to theoretically own a procedure.
     *
     * @return FunctionInterface<bool>
     */
    private function getOwnsOrgaRoleCondition(): FunctionInterface
    {
        return $this->conditionFactory->propertyHasAnyOfValues(
            [
                Role::CUSTOMER_MASTER_USER,
                Role::PLANNING_AGENCY_ADMIN,
                Role::PLANNING_AGENCY_WORKER,
                Role::HEARING_AUTHORITY_ADMIN,
                Role::HEARING_AUTHORITY_WORKER,
            ],
            'roleInCustomers', 'role', 'code'
        );
    }
}
