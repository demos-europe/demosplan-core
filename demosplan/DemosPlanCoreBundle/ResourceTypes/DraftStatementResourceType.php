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

use DemosEurope\DemosplanAddon\Contracts\Entities\DraftStatementInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\DraftStatementResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldValueCreator;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<DraftStatementInterface>
 *
 * @property-read End $customFields
 */
final class DraftStatementResourceType extends DplanResourceType
{
    public function __construct(
        private readonly CustomFieldValueCreator $customFieldValueCreator,
    ) {
    }

    public static function getName(): string
    {
        return 'DraftStatement';
    }

    public function getEntityClass(): string
    {
        return DraftStatement::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }
        return [$this->conditionFactory->true()];
    }

    public function isGetAllowed(): bool
    {
        return true; //@todo still needs to be adjusted
    }

    public function isListAllowed(): bool
    {
        return true; //@todo still needs to be adjusted
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_statements_custom_fields');
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        /** @var DraftStatementResourceConfigBuilder $draftStatementConfig */
        $draftStatementConfig = $this->getConfig(DraftStatementResourceConfigBuilder::class);

        $draftStatementConfig->id->setReadableByPath()->setFilterable();

        if ($this->currentUser->hasPermission('feature_statements_custom_fields')) {
            $draftStatementConfig->customFields
                ->setReadableByCallable(static fn (DraftStatement $draftStatement): ?array => $draftStatement->getCustomFields()?->toJson())
                ->updatable([],
                    function (DraftStatement $draftStatement, array $customFields): array {
                        $customFieldList = $draftStatement->getCustomFields() ?? new CustomFieldValuesList();
                        $customFieldList = $this->customFieldValueCreator->updateOrAddCustomFieldValues(
                            $customFieldList,
                            $customFields,
                            $draftStatement->getProcedure()->getId(),
                            CustomFieldSupportedEntity::procedure->value,
                            CustomFieldSupportedEntity::draftStatement->value,
                        );
                        $draftStatement->setCustomFields($customFieldList);

                        return [];
                    }
                );
        }

        return $draftStatementConfig;
    }
}
