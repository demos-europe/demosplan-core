<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\FilterHashException;
use demosplan\DemosPlanCoreBundle\Repository\HashedQueryRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\StoredQuery\AssessmentTableQuery;
use demosplan\DemosPlanCoreBundle\StoredQuery\StoredQueryInterface;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use Exception;

class HashedQueryService
{
    public function __construct(private readonly HashedQueryRepository $hashedQueryRepository, private readonly ProcedureRepository $procedureRepository)
    {
    }

    public function findHashedQueryWithHash(?string $filterSetHash): ?HashedQuery
    {
        return $this->hashedQueryRepository->findOneBy(['hash' => $filterSetHash]);
    }

    /**
     * @throws Exception
     */
    private function createFromQuery(StoredQueryInterface $storedQuery): HashedQuery
    {
        $hashedQuery = new HashedQuery();

        $hashedQuery->setStoredQuery($storedQuery);
        $hashedQuery->setHash($storedQuery->getHash());

        /** @var Procedure $procedure */
        $procedure = $this->procedureRepository->findOneBy(['id' => $storedQuery->getProcedureId()]);

        $hashedQuery->setProcedure($procedure);

        $this->hashedQueryRepository->addObject($hashedQuery);

        return $hashedQuery;
    }

    /**
     * This will either fetch an existing query matching the given one
     * or create a new one and return the result.
     *
     * @throws Exception
     */
    public function findOrCreateFromQuery(StoredQueryInterface $storedQuery): HashedQuery
    {
        $hashedQuery = $this->findHashedQueryWithHash($storedQuery->getHash());

        return $hashedQuery ?? $this->createFromQuery($storedQuery);
    }

    /**
     * Use for {@link AssessmentTableQuery} only.
     *
     * @param string $hash
     * @param bool   $original
     *
     * @throws Exception
     */
    public function handleFilterHashWithoutRequest(array $formValues, string $procedureId, $hash = null, $original = false): HashedQuery
    {
        // be very vocal about missing data for a correct filter hash generation
        $requiredKeys = ['filters', 'searchFields', 'search', 'view_mode'];

        if (!$original) {
            // original statement list has no sort
            $requiredKeys[] = 'sort';
        }

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $formValues)) {
                if ('sort' === $key) {
                    $formValues['sort'] = ToBy::createArray('submitDate', 'asc');
                } else {
                    throw FilterHashException::missingRequestField($key);
                }
            }
        }

        // The digest we get in the requestVariables wins against the digest from the
        // FilterHashObject. Because if the someone requests an url with hash he wants
        // to load a FilterSet. If not we want to save it to database, so we can easily get it later.

        $formValues['filters']['original'] = $original ? 'IS NULL' : 'IS NOT NULL';

        $assessmentTableQuery = new AssessmentTableQuery();
        $assessmentTableQuery->setProcedureId($procedureId);
        $assessmentTableQuery->setFilters($formValues['filters'] ?? []);
        $assessmentTableQuery->setSearchFields($formValues['searchFields'] ?? []);
        $assessmentTableQuery->setSearchWord($formValues['search'] ?? '');
        $assessmentTableQuery->setSorting($formValues['sort'] ?? []);
        $assessmentTableQuery->setViewMode($formValues['view_mode']);

        $rParamsHash = $assessmentTableQuery->getHash();

        // check if exists else insert
        $hashedQuery = $this->findHashedQueryWithHash($hash ?? $rParamsHash);

        if (null === $hashedQuery) {
            $hashedQuery = $this->createFromQuery($assessmentTableQuery);
        }

        // rParams win against hash
        if ($rParamsHash !== $hash && 0 !== count($formValues)) {
            $filterSet = $this->findOrCreateFromQuery($assessmentTableQuery);
        } else {
            $filterSet = $hashedQuery;
        }

        return $filterSet;
    }
}
