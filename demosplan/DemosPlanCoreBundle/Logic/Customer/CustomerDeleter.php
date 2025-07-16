<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Customer;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureDeleter;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use Exception;

class CustomerDeleter extends CoreService
{
    public function __construct(
        private readonly SqlQueriesService $queriesService,
        private readonly ProcedureRepository $procedureRepository,
        private readonly ProcedureDeleter $procedureDeleter,
    ) {
    }

    /**
     * @throws Exception
     */
    public function deleteCustomer(string $customerId, bool $isDryRun): array
    {
        // get and delete customer related procedures
        $customerProcedureIds = array_map(
            static fn (Procedure $procedure): string => $procedure->getId(),
            $this->procedureRepository->findBy(['customer' => $customerId])
        );
        $this->procedureDeleter->deleteProcedures($customerProcedureIds, $isDryRun);

        // delete branding as brandings are unique to customers
        $this->deleteCustomerUniqueBrandingIfExists($customerId, $isDryRun);

        // delete customer videos
        $this->deleteCustomerVideos($customerId, $isDryRun);

        // delete sign language overview video
        $this->deleteCustomerSignLanguageOverviewVideo($customerId, $isDryRun);

        // delete customer support_contacts
        $this->deleteCustoemrSupportContacts($customerId, $isDryRun);

        // delete faq categories
        $this->deleteFaqCategories($customerId, $isDryRun);

        $this->deleteInstitutionTagCategories($customerId, $isDryRun);

        // delete customer counties
        $this->deleteCustomerCounties($customerId, $isDryRun);

        // delete role-user-customer relations
        $this->deleteFromRoleUserCustomer($customerId, $isDryRun);

        // collect possibly orphaned orgas - orgas with no orgaType for no customer
        $possiblyOrphanedOrgas = $this->collectPossibleOrgaOrphans($customerId);

        // delete customer-orga-orgaType relations
        $this->deleteCustoemrOrgaOrgaTypeRelations($customerId, $isDryRun);

        // delete customer
        $this->deleteFromCustomerTable($customerId, $isDryRun);

        return $possiblyOrphanedOrgas;
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
     */
    public function rollBackTransaction(): void
    {
        $this->queriesService->rollbackTransaction();
        // reactivate foreign key checks
        $this->queriesService->activateForeignKeyChecks();
    }

    /**
     * @throws Exception
     */
    private function collectPossibleOrgaOrphans(string $customerId): array
    {
        $possibleOrgaOrphans = [];
        $orgaIdsOfDifferentCustomerRelations = array_column(
            $this->queriesService->fetchFromTableByExcludedParameter(
                ['_o_id'],
                'relation_customer_orga_orga_type',
                '_c_id',
                [$customerId]
            ),
            '_o_id'
        );
        $orgaIdsOfTypesFromCustomerToDelete = array_column(
            $this->queriesService->fetchFromTableByParameter(
                ['_o_id'],
                'relation_customer_orga_orga_type',
                '_c_id',
                [$customerId]
            ),
            '_o_id'
        );
        foreach ($orgaIdsOfTypesFromCustomerToDelete as $orgaId) {
            if (!in_array($orgaId, $orgaIdsOfDifferentCustomerRelations)) {
                // This Orga has only orgaTypes within the deleted customer
                $possibleOrgaOrphans[] = $orgaId;
            }
        }

        return $possibleOrgaOrphans;
    }

    /**
     * @throws Exception
     */
    private function deleteCustomerUniqueBrandingIfExists(string $customerId, bool $isDryRun): void
    {
        $brandingIdUniqueArray = array_column(
            $this->queriesService->fetchFromTableByParameter(
                ['branding_id'],
                'customer',
                '_c_id',
                [$customerId]
            ),
            'branding_id'
        );
        if (0 < count($brandingIdUniqueArray)) {
            // get branding logo fileId
            $fileIds = array_column(
                $this->queriesService->fetchFromTableByParameter(
                    ['logo'],
                    'branding',
                    'id',
                    $brandingIdUniqueArray
                ),
                'logo'
            );
            if (0 < count($fileIds)) {
                // delete logo if present
                $this->queriesService->deleteFromTableByIdentifierArray(
                    '_files',
                    '_f_ident',
                    $fileIds,
                    $isDryRun
                );
            }
            // delete branding if present
            $this->queriesService->deleteFromTableByIdentifierArray(
                'branding',
                'id',
                $brandingIdUniqueArray,
                $isDryRun
            );
        }
    }

    /**
     * @throws Exception
     */
    private function deleteCustoemrOrgaOrgaTypeRelations(string $customerId, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'relation_customer_orga_orga_type',
            '_c_id',
            [$customerId],
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteFromCustomerTable(string $customerId, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'customer',
            '_c_id',
            [$customerId],
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteFromRoleUserCustomer(string $customerId, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'relation_role_user_customer',
            'customer',
            [$customerId],
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteCustomerCounties(string $customerId, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'customer_county',
            'customer_id',
            [$customerId],
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteFaqCategories(string $customerId, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'faq_category',
            'customer_id',
            [$customerId],
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteCustomerSignLanguageOverviewVideo(string $customerId, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'sign_language_overview_video',
            'customer_id',
            [$customerId],
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteCustomerVideos(string $customerId, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'video',
            'customer_context_id',
            [$customerId],
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteCustoemrSupportContacts(string $customerId, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'support_contact',
            'customer',
            [$customerId],
            $isDryRun
        );
    }

    private function deleteInstitutionTagCategories(string $customerId, bool $isDryRun): void
    {
        $this->deleteInstitutionTags($customerId, $isDryRun);

        $this->queriesService->deleteFromTableByIdentifierArray(
            'institution_tag_category',
            'customer_id',
            [$customerId],
            $isDryRun
        );
    }

    private function deleteInstitutionTags(string $customerId, bool $isDryRun): void
    {
        $categoryIds = array_column(
            $this->queriesService->fetchFromTableByParameter(
                ['id'],
                'institution_tag_category',
                'customer_id',
                [$customerId]
            ),
            'id'
        );

        $this->queriesService->deleteFromTableByIdentifierArray(
            'institution_tag',
            'category_id',
            $categoryIds,
            $isDryRun
        );
    }
}
