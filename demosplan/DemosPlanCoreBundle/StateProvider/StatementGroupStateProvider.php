<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StateProvider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementGroupResource;
use demosplan\DemosPlanCoreBundle\Application\Header;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class StatementGroupStateProvider implements ProviderInterface
{
    private const SORTABLE_FIELDS = [
        'createdDate' => '_st_created_date',
        'created'     => '_st_created_date',
        'id'          => '_st_id',
    ];

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly Connection $connection,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), StatementGroupResource::class);

        // TEMP: security disabled for local exploration — restore isAvailable() check before merging.
        // if (!$this->isAvailable()) {
        //     throw new AccessDeniedHttpException('Access denied: insufficient permissions to access statement groups');
        // }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($context);
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    private function provideSingle(string $id): ?StatementGroupResource
    {
        $row = $this->connection->createQueryBuilder()
            ->select('_st_id', '_st_created_date')
            ->from('_statement')
            ->where('_st_id = :id')
            ->andWhere('entity_type = :type')
            ->andWhere('_st_deleted = 0')
            ->setParameter('id', $id)
            ->setParameter('type', 'StatementGroup')
            ->executeQuery()
            ->fetchAssociative();

        if (false === $row) {
            return null;
        }

        $memberIds = $this->fetchMemberIds([$id]);

        return $this->hydrate($row, $memberIds[$id] ?? []);
    }

    private function provideCollection(array $context = []): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('_st_id', '_st_created_date')
            ->from('_statement', 'g')
            ->where('g.entity_type = :type')
            ->andWhere('g._st_deleted = 0')
            ->andWhere('g.head_statement_id IS NULL')
            ->andWhere('g.moved_statement_id IS NULL')
            ->andWhere('EXISTS (SELECT 1 FROM _statement m WHERE m.head_statement_id = g._st_id AND m._st_deleted = 0)')
            ->setParameter('type', 'StatementGroup');

        $procedureId = $this->resolveProcedureId($context);
        if (null !== $procedureId) {
            $qb->andWhere('g._p_id = :procedureId')->setParameter('procedureId', $procedureId);
        }

        foreach ($this->getOrderBy($context) as $column => $direction) {
            $qb->addOrderBy($column, $direction);
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        if ([] === $rows) {
            return [];
        }

        $groupIds = array_column($rows, '_st_id');
        $memberIds = $this->fetchMemberIds($groupIds);

        return array_map(
            fn (array $row): StatementGroupResource => $this->hydrate($row, $memberIds[$row['_st_id']] ?? []),
            $rows
        );
    }

    /**
     * @param string[] $groupIds
     *
     * @return array<string, string[]>
     */
    private function fetchMemberIds(array $groupIds): array
    {
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $members = $this->connection->executeQuery(
            "SELECT _st_id, head_statement_id FROM _statement
             WHERE head_statement_id IN ($placeholders)
             AND _st_deleted = 0",
            $groupIds
        )->fetchAllAssociative();

        $result = array_fill_keys($groupIds, []);
        foreach ($members as $member) {
            $result[$member['head_statement_id']][] = $member['_st_id'];
        }

        return $result;
    }

    /**
     * @param string[] $memberIds
     */
    private function hydrate(array $row, array $memberIds = []): StatementGroupResource
    {
        $resource = new StatementGroupResource();
        $resource->id = $row['_st_id'];
        $resource->createdDate = new DateTime($row['_st_created_date']);
        $resource->memberIds = $memberIds;

        return $resource;
    }

    private function resolveProcedureId(array $context): ?string
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if ($procedure instanceof Procedure) {
            return $procedure->getId();
        }

        $request = $context['request'] ?? null;
        if (null === $request) {
            return null;
        }

        $headerId = $request->headers->get(Header::PROCEDURE_ID);
        if (null === $headerId || '' === $headerId || '0' === $headerId || 'undefined' === $headerId) {
            return null;
        }

        return $headerId;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_procedures');
    }

    private function getOrderBy(array $context): array
    {
        $default = ['_st_created_date' => 'DESC'];

        if (!array_key_exists('request', $context)) {
            return $default;
        }

        $sortQueryParamValue = $context['request']->query->get('sort');
        if (null === $sortQueryParamValue || '' === $sortQueryParamValue) {
            return $default;
        }

        $orderBy = [];
        foreach (explode(',', $sortQueryParamValue) as $sortField) {
            $sortField = trim($sortField, " \t\n\r\0\x0B\"'");
            if ('' === $sortField) {
                continue;
            }
            $direction = 'ASC';
            if (str_starts_with($sortField, '-')) {
                $direction = 'DESC';
                $sortField = substr($sortField, 1);
            }
            $column = self::SORTABLE_FIELDS[$sortField] ?? null;
            if (null !== $column) {
                $orderBy[$column] = $direction;
            }
        }

        return [] === $orderBy ? $default : $orderBy;
    }
}
