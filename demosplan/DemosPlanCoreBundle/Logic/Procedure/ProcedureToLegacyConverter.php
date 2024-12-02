<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSubscription;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\LegacyResult;
use Doctrine\Common\Collections\Collection;
use Exception;

use function collect;

/**
 * @deprecated Use Procedure Object instead
 */
class ProcedureToLegacyConverter extends CoreService
{
    public function __construct(private readonly DateHelper $dateHelper, private readonly EntityHelper $entityHelper, private readonly ProcedureRepository $procedureRepository)
    {
    }

    /**
     * Convert Doctrineresult into legacyformat as pure array without Classes.
     *
     * @param Procedure|array $procedure
     *
     * @throws Exception
     */
    public function convertToLegacy($procedure): array
    {
        if (!$procedure instanceof Procedure) {
            // Legacy returnvalues if no procedure found
            return [
                'closed'              => false,
                'deleted'             => false,
                'master'              => false,
                'publicParticipation' => false,
            ];
        }

        // planning agencies other institutions are split up
        $procedureArray = $this->convertOrgasToLegacy($procedure);

        $procedureArray['agencyExtraEmailAddresses'] = $procedure
            ->getAgencyExtraEmailAddresses()
            ->map(static fn (EmailAddress $emailAddress) => $emailAddress->getFullAddress());

        // When using objects this is not needed any more

        if (null !== $procedureArray['settings']) {
            $procedureArray['settings'] = $this->entityHelper->toArray($procedureArray['settings']);
            $procedureArray['phaseObject'] = $this->entityHelper->toArray($procedure->getPhaseObject());
            $procedureArray['publicParticipationPhaseObject'] = $this->entityHelper->toArray($procedure->getPublicParticipationPhaseObject());
            $procedureArray['pictogram'] = $procedureArray['settings']['pictogram'];
            $procedureArray['pictogramCopyright'] = $procedureArray['settings']['pictogramCopyright'];
            $procedureArray['pictogramAltText'] = $procedureArray['settings']['pictogramAltText'];
        }

        $procedureArray['isMapEnabled'] = false;
        if (isset($procedureArray['elements']) && $procedureArray['elements'] instanceof Collection) {
            $mapElements = $procedureArray['elements']->filter(
                static fn ($entry) => 'map' === $entry->getCategory()
                    && true === $entry->getEnabled()
            );
            if (0 < $mapElements->count()) {
                $procedureArray['isMapEnabled'] = true;
            }

            // T11703 disable map in procedures of type les. This is by no means
            // elegant nor scalable. Better way needs to be found
            // todo: alles in dem profiler hier kann weg und durch eine neue funktionalität mit dem
            //       verfahrenstypen ersetzt werden. das ist mir aber zu riskant, weil da ggf. doch noch was dran
            //       hängt was nicht ersatzlos weg kann. daher beschränke ich mich jetzt darauf, die funktionalität 1:1
            //       nachzubauen, anstatt etwas falsches zu löschen. gerne nachholen, vermuteter aufwand: 10 minuten.
            //       15.10.2020
            $behaviorDefinition = $procedure->getProcedureBehaviorDefinition();
            if ($behaviorDefinition instanceof ProcedureBehaviorDefinition
                && !$behaviorDefinition->isAllowedToEnableMap()) {
                $procedureArray['isMapEnabled'] = false;
            }
        }

        return $procedureArray;
    }

    /**
     * @throws Exception
     *
     * @todo Besser in die Datenbankabfrage aufnehmen
     */
    protected function convertOrgasToLegacy(Procedure $procedure): array
    {
        $nonPlanningOfficeOrganisationIds = $this->procedureRepository->getInvitedOrgaIds($procedure->getId());
        $planningOfficeIds = $this->procedureRepository->getPlanningOfficeIds($procedure->getId());
        $isCustomerMasterBlueprint = $procedure->isCustomerMasterBlueprint();
        $planningOfficeOrganisations = collect($procedure->getPlanningOffices())
            ->transform(static fn (Orga $orga) => [
                'ident'     => $orga->getId(),
                'name'      => $orga->getName(),
                'nameLegal' => $orga->getName(),
            ])->all();
        $dataInputOrgaIds = $procedure->getDataInputOrgaIds();
        $authorizedUserIds = $procedure->getAuthorizedUserIds();

        // T34551 changed the relation of procedure to customer.
        // previously the procedure->customer relation was null Except for the default-customer-blueprint.
        // Now only the customer holds the relation to its default-blueprint.
        $customerToLegacy = $procedure->isCustomerMasterBlueprint() ? $procedure->getCustomer() : null;

        // explicitly define legacy procedure array to be able to optimize database calls
        // (e.g. avoid costly getOrganisationIds() call). More over there is no need to automatically
        // include new properties to the legacy array
        return [
            'agencyExtraEmailAddresses'             => $procedure->getAgencyExtraEmailAddresses(),
            'agencyMainEmailAddress'                => $procedure->getAgencyMainEmailAddress(),
            'ars'                                   => $procedure->getArs(),
            'authorizedUsers'                       => $procedure->getAuthorizedUsers(),
            'authorizedUserIds'                     => $authorizedUserIds,
            'closed'                                => $procedure->getClosed(),
            'closedDate'                            => $procedure->getClosedDate(),
            'coordinate'                            => $procedure->getCoordinate(),
            'createdDate'                           => $procedure->getCreatedDate(),
            'currentSlug'                           => $procedure->getCurrentSlug(),
            'customer'                              => $customerToLegacy,
            'dataInputOrganisations'                => $procedure->getDataInputOrganisations(),
            'dataInputOrgaIds'                      => $dataInputOrgaIds,
            'deleted'                               => $procedure->getDeleted(),
            'deletedDate'                           => $procedure->getDeletedDate(),
            'desc'                                  => $procedure->getDesc(),
            'elements'                              => $procedure->getElements(),
            'endDate'                               => $procedure->getEndDate(),
            'externalDesc'                          => $procedure->getExternalDesc(),
            'externalName'                          => $procedure->getExternalName(),
            'externId'                              => $procedure->getExternId(),
            'id'                                    => $procedure->getId(),
            'ident'                                 => $procedure->getId(),
            'isCustomerMasterBlueprint'             => $isCustomerMasterBlueprint,
            'locationName'                          => $procedure->getLocationName(),
            'locationPostCode'                      => $procedure->getLocationPostCode(),
            'logo'                                  => $procedure->getLogo(),
            'master'                                => $procedure->getMaster(),
            'masterTemplate'                        => $procedure->isMasterTemplate(),
            'municipalCode'                         => $procedure->getMunicipalCode(),
            'name'                                  => $procedure->getName(),
            'notificationReceivers'                 => $procedure->getNotificationReceivers(),
            'orga'                                  => $procedure->getOrga(),
            'orgaId'                                => $procedure->getOrgaId(),
            'orgaName'                              => $procedure->getOrgaName(),
            'organisation'                          => $nonPlanningOfficeOrganisationIds,
            'organisationIds'                       => $nonPlanningOfficeOrganisationIds,
            'phase'                                 => $procedure->getPhase(),
            'phaseName'                             => $procedure->getPhaseName(),
            'phasePermissionset'                    => $procedure->getPhasePermissionset(),
            'planningOffices'                       => $planningOfficeOrganisations,
            'planningOfficesIds'                    => $planningOfficeIds,
            'plisId'                                => $procedure->getPlisId(),
            'procedureBehaviorDefinition'           => $procedure->getProcedureBehaviorDefinition(),
            'procedureCategories'                   => $procedure->getProcedureCategories(),
            'procedureType'                         => $procedure->getProcedureType(),
            'procedureUiDefinition'                 => $procedure->getProcedureUiDefinition(),
            'publicParticipation'                   => $procedure->getPublicParticipation(),
            'publicParticipationContact'            => $procedure->getPublicParticipationContact(),
            'publicParticipationEndDate'            => $procedure->getPublicParticipationEndDate(),
            'publicParticipationPhase'              => $procedure->getPublicParticipationPhase(),
            'publicParticipationPhaseName'          => $procedure->getPublicParticipationPhaseName(),
            'publicParticipationPhasePermissionset' => $procedure->getPublicParticipationPhasePermissionset(),
            'publicParticipationPublicationEnabled' => $procedure->getPublicParticipationPublicationEnabled(),
            'publicParticipationStartDate'          => $procedure->getPublicParticipationStartDate(),
            'publicParticipationStep'               => $procedure->getPublicParticipationStep(),
            'settings'                              => $procedure->getSettings(),
            'shortUrl'                              => $procedure->getShortUrl(),
            'slugs'                                 => $procedure->getSlugs(),
            'startDate'                             => $procedure->getStartDate(),
            'statementFormDefinition'               => $procedure->getStatementFormDefinition(),
            'statements'                            => $procedure->getStatements(),
            'step'                                  => $procedure->getStep(),
            'surveys'                               => $procedure->getSurveys(),
            'topics'                                => $procedure->getTopics(),
        ];
    }

    /**
     * Convert Result to Legacy.
     *
     * @param array       $list
     * @param string|null $search
     * @param array       $aggregation Elasticsearch aggregation converted to legacy
     *
     * @internal param array $filter
     */
    public function toLegacyResult($list, $search = '', $aggregation = []): LegacyResult
    {
        $filterSet = [
            'total'   => count($aggregation),
            'offset'  => 0,
            'limit'   => 0,
            'filters' => $aggregation,
        ];

        return new LegacyResult($list, $filterSet, [], count($list), $search ?? '');
    }

    /**
     * Convert Doctrineresult into legacyformat as pure array without Classes.
     *
     * @param ProcedureSubscription|array $procedureSubscription
     */
    public function convertSubscriptionToLegacy($procedureSubscription): ?array
    {
        if (!$procedureSubscription instanceof ProcedureSubscription) {
            // Legacy returnvalues if no procedure found
            return [];
        }
        $procedureSubscription = $this->entityHelper->toArray($procedureSubscription);

        return $this->dateHelper->convertDatesToLegacy($procedureSubscription);
    }
}
