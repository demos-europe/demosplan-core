<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

/**
 * The single definition of "an organisation has active institution users": at least one of its users
 * is not deleted and holds an institution role ({@see User::PUBLIC_AGENCY_ROLES}) in the current customer.
 *
 * Everything that needs this information as a column or a filter builds on {@see self::existsDql()},
 * so the rule is written exactly once. Whether an institution may be invited or added to a procedure
 * is a different question and not answered here.
 */
class ActiveInstitutionUsers
{
    /**
     * Name of the computed column added by {@see self::addSelect()}.
     */
    public const DEFAULT_ALIAS = 'hasActiveUsers';

    private const CUSTOMER_PARAMETER = 'activeUsersCustomerId';
    private const ROLE_CODES_PARAMETER = 'activeUsersRoleCodes';
    private const ORGA_IDS_PARAMETER = 'activeUsersOrgaIds';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrgaRepository $orgaRepository,
        private readonly CustomerService $customerService,
    ) {
    }

    /**
     * A DQL condition that is true for the organisation behind the given alias if the rule applies.
     * The two parameters it uses have to be bound with {@see self::bindParameters()}.
     *
     * @param non-empty-string $orgaAlias          DQL alias of an {@see Orga} in the surrounding query
     * @param non-empty-string $customerParameter  name of the parameter that holds the customer id
     * @param non-empty-string $roleCodesParameter name of the parameter that holds the institution role codes
     */
    public function existsDql(string $orgaAlias, string $customerParameter, string $roleCodesParameter): string
    {
        return sprintf(
            'EXISTS (SELECT 1 FROM %s activeOrga '
            .'JOIN activeOrga.users activeUser '
            .'JOIN activeUser.roleInCustomers activeRole '
            .'JOIN activeRole.role activeRoleDefinition '
            .'WHERE activeOrga.id = %s.id '
            .'AND activeUser.deleted = false '
            .'AND IDENTITY(activeRole.customer) = :%s '
            .'AND activeRoleDefinition.code IN (:%s))',
            Orga::class,
            $orgaAlias,
            $customerParameter,
            $roleCodesParameter
        );
    }

    /**
     * Binds the parameters used by {@see self::existsDql()}.
     *
     * @param non-empty-string $customerParameter
     * @param non-empty-string $roleCodesParameter
     */
    public function bindParameters(QueryBuilder $queryBuilder, string $customerParameter, string $roleCodesParameter): void
    {
        $queryBuilder->setParameter($customerParameter, $this->customerService->getCurrentCustomer()->getId());
        $queryBuilder->setParameter($roleCodesParameter, User::PUBLIC_AGENCY_ROLES);
    }

    /**
     * Adds the rule as a computed column to the collection query of an API Platform provider. This is the
     * approach of API Platform's computed-field guide (`docs/guides/computed-field.php` in api-platform/core):
     * the state option `handleLinks` changes the query before it runs. The rows of the result then look like
     * `[0 => Orga, '<alias>' => 1|0]`.
     *
     * The condition is a subselect, so it adds no join and no grouping to the query.
     */
    public function addSelect(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $alias = self::DEFAULT_ALIAS,
    ): void {
        $customerParameter = $queryNameGenerator->generateParameterName(self::CUSTOMER_PARAMETER);
        $roleCodesParameter = $queryNameGenerator->generateParameterName(self::ROLE_CODES_PARAMETER);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        $queryBuilder->addSelect(sprintf(
            '(CASE WHEN %s THEN 1 ELSE 0 END) AS %s',
            $this->existsDql($rootAlias, $customerParameter, $roleCodesParameter),
            $alias
        ));
        $this->bindParameters($queryBuilder, $customerParameter, $roleCodesParameter);
    }

    /**
     * @param list<string> $orgaIds
     *
     * @return list<string> the given ids of the organisations to which the rule applies
     */
    public function findIds(array $orgaIds): array
    {
        if ([] === $orgaIds) {
            return [];
        }

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('candidate.id')
            ->from(Orga::class, 'candidate')
            ->where(sprintf('candidate.id IN (:%s)', self::ORGA_IDS_PARAMETER))
            ->andWhere($this->existsDql('candidate', self::CUSTOMER_PARAMETER, self::ROLE_CODES_PARAMETER))
            ->setParameter(self::ORGA_IDS_PARAMETER, $orgaIds);
        $this->bindParameters($queryBuilder, self::CUSTOMER_PARAMETER, self::ROLE_CODES_PARAMETER);

        return array_map(
            static fn (mixed $id): string => (string) $id,
            $queryBuilder->getQuery()->getSingleColumnResult()
        );
    }

    /**
     * Creates the callback for a computed attribute of an EDT resource type, to be used with
     * `setReadableByCallable()`. EDT calls such a callback once per row, so the callback must not
     * ask the database per row.
     *
     * The first call runs one query. It finds all organisations that the resource type may return, as
     * described by its access conditions, to which the rule applies. Every further call only looks the id up.
     * The cost is one query per request, independent of the number of rows, and none at all if the
     * attribute is never read.
     *
     * The result is remembered by the returned callback. EDT keeps the property configuration of a resource
     * type, and with it the callback, for the lifetime of the resource type instance.
     *
     * @param callable(): list<ClauseFunctionInterface<bool>> $accessConditionsProvider provides the conditions of
     *                                                                                  the resource type, called
     *                                                                                  at most once
     *
     * @return callable(OrgaInterface): bool
     */
    public function createReadCallback(callable $accessConditionsProvider): callable
    {
        $activeOrgaIds = null;

        return function (OrgaInterface $orga) use (&$activeOrgaIds, $accessConditionsProvider): bool {
            $activeOrgaIds ??= array_flip($this->findIdsAmongAccessible($accessConditionsProvider()));

            return isset($activeOrgaIds[$orga->getId()]);
        };
    }

    /**
     * @param list<ClauseFunctionInterface<bool>> $accessConditions
     *
     * @return list<string> the ids of the organisations that fulfill the conditions and to which the rule applies
     */
    private function findIdsAmongAccessible(array $accessConditions): array
    {
        $queryBuilder = $this->orgaRepository->generateAccessConditionQueryBuilder($accessConditions);
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->select(sprintf('DISTINCT %s.id', $rootAlias))
            ->andWhere($this->existsDql($rootAlias, self::CUSTOMER_PARAMETER, self::ROLE_CODES_PARAMETER));
        $this->bindParameters($queryBuilder, self::CUSTOMER_PARAMETER, self::ROLE_CODES_PARAMETER);

        return array_map(
            static fn (mixed $id): string => (string) $id,
            $queryBuilder->getQuery()->getSingleColumnResult()
        );
    }
}
