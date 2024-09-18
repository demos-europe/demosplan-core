<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Orga;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureDeleter;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use Doctrine\DBAL\Schema\SchemaException;
use Exception;

class OrgaDeleter extends CoreService
{
    public function __construct(
        private readonly SqlQueriesService $queriesService,
        private readonly ProcedureRepository $procedureRepository,
        private readonly ProcedureDeleter $procedureDeleter
    ) {
    }

    /**
     * @throws Exception
     */
    public function deleteOrganisations(array $orgaIds, bool $isDryRun): void
    {
        // delete orga slugs
        $this->deleteOrgaSlug($orgaIds, $isDryRun);

        // delete organisations address book entry
        $this->deleteAddressBookEntry($orgaIds, $isDryRun);

        // delete relation customer orga orga type
        $this->deleteRelationCustomerOrgaOrgaType($orgaIds, $isDryRun);

        // delete elements orga doctrine. This relation table seems not to be anymore used, all related Data will
        // be anyway removed.
        $this->deleteElementsOrgaDoctrine($orgaIds, $isDryRun);

        // delete organisation addresses doctrine
        $this->deleteOrgaAddressDoctrine($orgaIds, $isDryRun);

        // delete organisation department doctrine
        $this->deleteOrgaDepartmentDoctrine($orgaIds, $isDryRun);

        // delete organisation user doctrine
        $this->deleteOrgaUserDoctrine($orgaIds, $isDryRun);

        // delete institution tag and orga institution tag
        $this->deleteOrgaInstitutionTag($orgaIds, $isDryRun);

        // delete progression userstory votes
        $this->deleteProgressionUserStoryVotes($orgaIds, $isDryRun);

        // delete orga settings
        $this->deleteSettings($orgaIds, $isDryRun);

        // delete procedure orga doctrine
        $this->deleteProcedureOrgaDoctrine($orgaIds, $isDryRun);

        // delete orga report entries
        $this->deleteReportEntries($orgaIds, $isDryRun);

        $this->deleteAccesssControl($orgaIds, $isDryRun);

        $orgasProcedureIds = Collect($this->procedureRepository->findBy(['orga' => $orgaIds]))->map(
            static fn (Procedure $procedure): string => $procedure->getId()
        );
        // delete procedures
        // handles tables with orga_ids as well as procedureIds like:
        // procedure_planningoffices, procedure_orga_datainput, institution_mail, _draft_statement_versions
        $this->procedureDeleter->deleteProcedures($orgasProcedureIds->toArray(), $isDryRun);

        $this->deleteProcedurePlannungOffices($orgaIds, $isDryRun);
        // delete organisations
        $this->deleteOrgas($orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    public function beginTransactionAndDisableForeignKeyChecks(): void
    {
        // deactivate foreign key checks
        $this->queriesService->deactivateForeignKeyChecks();
        // start doctrine transaction
        $this->queriesService->beginTransaction();
    }

    /**
     * @throws Exception
     */
    public function commitTransactionAndEnableForeignKeyChecks(): void
    {
        // commit all changes
        $this->queriesService->commitTransaction();
        // reactivate foreign key checks
        $this->queriesService->activateForeignKeyChecks();
    }

    /**
     * @throws Exception
     *
     * Planning offices and procedures share a manyToMany relation and therefore we have to delete these relations
     * from both sides { @see ProcedureDeleter::deleteProcedurePlannungOffices() }
     */
    private function deleteProcedurePlannungOffices(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'procedure_planningoffices',
            '_o_id',
            $orgaIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteOrgaSlug(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('orga_slug', 'o_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteAddressBookEntry(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('address_book_entry', 'organisation_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteRelationCustomerOrgaOrgaType(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('relation_customer_orga_orga_type', '_o_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteElementsOrgaDoctrine(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_elements_orga_doctrine', '_o_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteOrgaAddressDoctrine(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_orga_addresses_doctrine', '_o_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteOrgaDepartmentDoctrine(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_orga_departments_doctrine', '_o_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteOrgaUserDoctrine(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_orga_users_doctrine', '_o_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteProgressionUserStoryVotes(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_progression_userstory_votes', '_puv_orga_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteSettings(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_settings', '_s_orga_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureOrgaDoctrine(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_procedure_orga_doctrine', '_o_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteReportEntries(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_report_entries', '_re_identifier', $orgaIds, $isDryRun);
    }

    private function deleteAccesssControl(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('access_control', 'orga_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     * @throws SchemaException
     */
    private function deleteOrgaInstitutionTag(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('institution_tag', 'owning_organisation_id', $orgaIds, $isDryRun);
        $this->queriesService->deleteFromTableByIdentifierArray('orga_institution_tag', 'orga__o_id', $orgaIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteOrgas(array $orgaIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_orga', '_o_id', $orgaIds, $isDryRun);
    }
}
