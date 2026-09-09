<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use DemosEurope\DemosplanAddon\Contracts\Services\TransactionServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Constraint\ProcedureWithStatementsCustomFieldConstraint;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Factory\EntityCustomFieldUsageStrategyFactory;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CustomFieldDeleter
{
    public function __construct(
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly EntityCustomFieldUsageStrategyFactory $entityCustomFieldUsageStrategyFactory,
        private readonly TransactionServiceInterface $transactionService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ConnectionException
     * @throws ORMException
     */
    public function deleteCustomField(string $entityId): void
    {
        $this->transactionService->executeAndFlushInTransaction(
            function () use ($entityId): void {
                /** @var CustomFieldConfiguration $customFieldConfiguration */
                $customFieldConfiguration = $this->customFieldConfigurationRepository->find($entityId);

                if (!$customFieldConfiguration) {
                    throw new InvalidArgumentException("CustomFieldConfiguration with ID '{$entityId}' not found");
                }

                $violations = $this->validator->validate(
                    $customFieldConfiguration,
                    [new ProcedureWithStatementsCustomFieldConstraint(['message' => 'CustomField cannot be deleted: Procedure with statements'])]
                );

                if ($violations->count() > 0) {
                    throw new InvalidArgumentException((string) $violations);
                }

                $entityStrategy = $this->entityCustomFieldUsageStrategyFactory->createUsageRemovalStrategy($customFieldConfiguration->getTargetEntityClass());
                $entityStrategy->removeUsages($entityId);

                $this->customFieldConfigurationRepository->deleteObject($customFieldConfiguration);
            }
        );
    }
}
