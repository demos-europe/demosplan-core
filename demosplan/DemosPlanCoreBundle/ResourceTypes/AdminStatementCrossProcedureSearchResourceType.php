<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementDeleter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\StatementResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\JsonApi\ResourceTypes\AbstractResourceType;
use Webmozart\Assert\Assert;

/**
 * Cross-procedure submitter search resource. Exposes statements the current user can administer
 * across all of their procedures so that the FE can search by submitter name without a
 * procedure context.
 *
 * In contrast to {@link StatementResourceType}, this type:
 * - is only available when the user holds `feature_json_api_statement_cross_procedures_search`,
 *   a dedicated permission kept deliberately separate from `area_search_submitter_in_procedures`
 *   (which also activates a procedure-list submitter-search UI flow in legacy projects that enable it)
 * - scopes statements to the user's administrable procedures via pre-fetched IDs (no current procedure context required)
 * - exposes only the property surface the search-results UI needs; it deliberately does not inherit
 *   the assessment-table-specific surface of {@link StatementResourceType}
 *
 * @property-read StatementResourceType $parentStatementOfSegment Do not expose! Alias usage only — needed to filter segment rows out of the result set.
 */
final class AdminStatementCrossProcedureSearchResourceType extends AbstractStatementResourceType
{
    public function __construct(
        HTMLSanitizer $htmlSanitizer,
        StatementService $statementService,
        private readonly ProcedureHandler $procedureHandler,
        private readonly StatementDeleter $statementDeleter,
    ) {
        parent::__construct($htmlSanitizer, $statementService);
    }

    public function getEntityClass(): string
    {
        return Statement::class;
    }

    public static function getName(): string
    {
        return 'AdminStatementCrossProcedureSearch';
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_statement_cross_procedures_search');
    }

    /**
     * `feature_statement_delete` cannot be used here: projects grant it inside their
     * {@see Permissions::setProcedurePermissions()} implementation, usually behind
     * {@see Permissions::ownsProcedure()}, and this resource intentionally operates without a procedure
     * context, where that permission is never enabled.
     *
     * The dedicated permission only opens the delete verb. Which statements it may be applied to is
     * decided by {@see self::getAccessConditions()}, see {@see self::deleteEntity()}.
     */
    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_statement_delete_cross_procedures');
    }

    /**
     * {@see AbstractResourceType::getEntity()} applies {@see self::getAccessConditions()}, so a statement
     * whose procedure the current user does not administer cannot be resolved here and the deletion fails
     * instead of taking effect. That makes the access conditions the authorization for deletions, too.
     */
    public function deleteEntity(string $entityIdentifier): void
    {
        $this->getTransactionService()->executeAndFlushInTransaction(
            function () use ($entityIdentifier): void {
                $statement = $this->getEntity($entityIdentifier);
                Assert::isInstanceOf($statement, Statement::class);
                $success = $this->statementDeleter->deleteStatementObject($statement);
                Assert::true($success, "Deletion of statement failed for the given ID '$entityIdentifier'");
            }
        );
    }

    protected function getAccessConditions(): array
    {
        $allowedProcedureIds = array_map(
            static fn (Procedure $procedure): string => $procedure->getId(),
            $this->procedureHandler->getProceduresForAdmin()
        );

        if ([] === $allowedProcedureIds) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            $this->conditionFactory->propertyIsNull($this->headStatement->id),
            $this->conditionFactory->propertyIsNull($this->movedStatement),
            $this->conditionFactory->propertyIsNull($this->parentStatementOfSegment),
            $this->conditionFactory->propertyIsNotNull($this->original->id),
            /** This is the authorization for reading *and* deleting. The IDs come from
             * {@see ProcedureAccessEvaluator::getOwnsProcedureConditions()}, which reproduces
             * {@see Permissions::ownsProcedure()} — including the requirement that the user is listed in
             * {@see Procedure::getAuthorizedUsers()} whenever `procedure_user_restricted_access` is enabled.
             * A user therefore only ever sees and deletes statements of procedures they could open anyway. */
            $this->conditionFactory->propertyHasAnyOfValues(
                $allowedProcedureIds,
                $this->procedure->id
            ),
            /* Defense in depth: scope to current customer too, mirroring
             * { @see ProcedureResourceType::getResourceTypeConditions()}. Keeps statement
             * visibility consistent with what an `include=procedure` sideload would resolve.*/
            $this->conditionFactory->propertyHasValue(
                $this->currentCustomerService->getCurrentCustomer()->getId(),
                $this->procedure->customer->id
            ),
        ];
    }

    /**
     * Deliberately narrow property surface — only the fields visible in the cross-procedure
     * search results UI may be exposed. Cross-procedure data exposure is constrained by
     * data-minimization rules (procedure boundaries usually contain statement contents);
     * adding fields here without a UI requirement would broaden that exposure.
     */
    protected function getProperties(): ResourceConfigBuilderInterface
    {
        /** @var StatementResourceConfigBuilder $configBuilder */
        $configBuilder = $this->getConfig(StatementResourceConfigBuilder::class);

        $configBuilder->id->readable()->filterable();
        $configBuilder->externId->readable(true);
        $configBuilder->status->readable(true);
        $configBuilder->authorName
            ->aliasedPath(Paths::statement()->meta->authorName)
            ->readable(true)
            ->filterable();
        $configBuilder->submitName
            ->aliasedPath(Paths::statement()->meta->submitName)
            ->readable(true)
            ->filterable();
        $configBuilder->initialOrganisationName
            ->aliasedPath(Paths::statement()->meta->orgaName)
            ->readable(true);
        $configBuilder->procedure
            ->setRelationshipType($this->resourceTypeStore->getProcedureResourceType())
            ->readable()
            ->filterable();

        return $configBuilder;
    }
}
