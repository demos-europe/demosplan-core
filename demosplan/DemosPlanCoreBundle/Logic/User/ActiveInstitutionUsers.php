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

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

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
    private const CUSTOMER_PARAMETER = 'activeUsersCustomerId';
    private const ROLE_CODES_PARAMETER = 'activeUsersRoleCodes';
    private const ORGA_IDS_PARAMETER = 'activeUsersOrgaIds';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
}
