<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StateProcessor;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\DraftStatementResource;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\DraftStatementRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldValueCreator;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

class DraftStatementStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly DraftStatementRepository $draftStatementRepository,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly CustomFieldValueCreator $customFieldValueCreator,
        #[Autowire(service: PersistProcessor::class)] private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        Assert::isInstanceOf($data, DraftStatementResource::class);

        if (!$this->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if ($operation instanceof Patch && $this->isUpdateAllowed()) {
            $draftStatement = $this->applyResourceToEntity($data);
            $this->persistProcessor->process($draftStatement, $operation, $uriVariables, $context);
            $data->id = $draftStatement->getId();

            return $data;
        }

        return null;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_statements_draft');
    }

    public function isUpdateAllowed(): bool
    {
        return $this->isAvailable();
    }

    private function applyResourceToEntity(DraftStatementResource $resource): DraftStatement
    {
        if (null === $resource->id) {
            throw new InvalidArgumentException('No draft statement ID provided');
        }

        try {
            $draftStatement = $this->draftStatementRepository->getEntityByIdentifier(
                $resource->id,
                $this->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Draft statement %s not found', $resource->id));
        }

        // Mirrors DraftStatementResourceType::getProperties() — customFields update
        // is only attached when the user has the permission, so a payload from a user
        // without it must be rejected rather than silently dropped.
        if (null !== $resource->customFields) {
            if (!$this->currentUser->hasPermission('feature_statements_custom_fields')) {
                throw new AccessDeniedHttpException('Access denied: insufficient permissions to update custom fields');
            }

            $customFieldList = $draftStatement->getCustomFields() ?? new CustomFieldValuesList();
            $customFieldList = $this->customFieldValueCreator->updateOrAddCustomFieldValues(
                $customFieldList,
                $resource->customFields,
                $draftStatement->getProcedure()->getId(),
                CustomFieldSupportedEntity::procedure->value,
                CustomFieldSupportedEntity::statement->value,
            );
            $draftStatement->setCustomFields($customFieldList);
        }

        return $draftStatement;
    }

    /**
     * Mirrors DraftStatementResourceType::getAccessConditions().
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    private function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        $user = $this->currentUser->getUser();
        if (!$user instanceof User) {
            return [$this->conditionFactory->false()];
        }

        return [
            // Current procedure only
            $this->conditionFactory->propertyHasValue($procedure->getId(), ['procedure', 'id']),

            // Not deleted
            $this->conditionFactory->propertyHasValue(false, ['deleted']),

            // Same organization
            $this->conditionFactory->propertyHasValue($user->getOrganisationId(), ['organisation', 'id']),

            // Own drafts only (works for all user types)
            $this->conditionFactory->propertyHasValue($user->getId(), ['user', 'id']),
        ];
    }
}
