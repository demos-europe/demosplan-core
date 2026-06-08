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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTime;
use Doctrine\DBAL\Connection;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementGroupResource;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

class StatementGroupMemberProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StatementGroupResource
    {
        Assert::isInstanceOf($data, StatementGroupResource::class);

        $groupId = $uriVariables['id'];

        $group = $this->connection->createQueryBuilder()
            ->select('_st_id', '_st_created_date', '_p_id')
            ->from('_statement')
            ->where('_st_id = :id')
            ->andWhere('entity_type = :type')
            ->andWhere('_st_deleted = 0')
            ->setParameter('id', $groupId)
            ->setParameter('type', 'StatementGroup')
            ->executeQuery()
            ->fetchAssociative();

        if (false === $group) {
            throw new NotFoundHttpException(sprintf('StatementGroup "%s" not found', $groupId));
        }

        $statementIds = $data->statementIds;

        if ([] !== $statementIds) {
            $this->validateAndAssignMembers($statementIds, $groupId, $group['_p_id']);
        }

        $resource = new StatementGroupResource();
        $resource->id = $group['_st_id'];
        $resource->createdDate = new DateTime($group['_st_created_date']);

        return $resource;
    }

    /**
     * @param string[] $statementIds
     */
    private function validateAndAssignMembers(array $statementIds, string $groupId, string $procedureId): void
    {
        $placeholders = implode(',', array_fill(0, count($statementIds), '?'));
        $existing = $this->connection->executeQuery(
            "SELECT _st_id FROM _statement
             WHERE _st_id IN ($placeholders)
             AND _p_id = ?
             AND _st_deleted = 0
             AND entity_type != 'StatementGroup'",
            [...$statementIds, $procedureId]
        )->fetchFirstColumn();

        $missing = array_diff($statementIds, $existing);
        if ([] !== $missing) {
            throw new BadRequestHttpException(sprintf(
                'Statement(s) not found in this procedure: %s',
                implode(', ', $missing)
            ));
        }

        foreach ($statementIds as $statementId) {
            $this->connection->update(
                '_statement',
                [
                    'head_statement_id' => $groupId,
                    'entity_type'       => 'StatementMember',
                ],
                ['_st_id' => $statementId]
            );
        }
    }
}
