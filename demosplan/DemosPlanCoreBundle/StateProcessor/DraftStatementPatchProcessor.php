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
use ApiPlatform\State\ProcessorInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\DraftStatementResource;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Repository\DraftStatementRepository;
use demosplan\DemosPlanCoreBundle\ResourceAccess\DraftStatementAccessChecker;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldValueCreator;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

class DraftStatementPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DraftStatementAccessChecker $accessChecker,
        private readonly DraftStatementRepository $draftStatementRepository,
        private readonly CurrentUserInterface $currentUser,
        private readonly CustomFieldValueCreator $customFieldValueCreator,
        #[Autowire(service: PersistProcessor::class)] private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        Assert::isInstanceOf($data, DraftStatementResource::class);

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if (!$this->accessChecker->isUpdateAllowed()) {
            return null;
        }

        $draftStatement = $this->applyResourceToEntity($data);
        $this->persistProcessor->process($draftStatement, $operation, $uriVariables, $context);
        $data->id = $draftStatement->getId();

        return $data;
    }

    private function applyResourceToEntity(DraftStatementResource $resource): DraftStatement
    {
        if (null === $resource->id) {
            throw new InvalidArgumentException('No draft statement ID provided');
        }

        try {
            $draftStatement = $this->draftStatementRepository->getEntityByIdentifier(
                $resource->id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Draft statement %s not found', $resource->id));
        }

        // Field-level permission gate — mirrors the permission-gated updatable
        // closure in DraftStatementResourceType::getProperties(). A payload
        // from a user without the permission must be rejected rather than
        // silently dropped.
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
}
