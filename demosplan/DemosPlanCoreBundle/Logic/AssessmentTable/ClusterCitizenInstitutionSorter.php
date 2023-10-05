<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

class ClusterCitizenInstitutionSorter implements ArraySorterInterface
{
    /**
     * @param Statement[]|StatementFragment[] $items
     *
     * {@inheritdoc}
     */
    public function sortArray(array $items): array
    {
        $groups = collect($items)
            ->groupBy(
                function ($item) {
                    $statement = $this->getStatement($item);

                    if ($statement->isClusterStatement()) {
                        return 0; // clustered statements
                    }
                    if (Statement::EXTERNAL === $statement->getPublicStatement()
                        || $statement->isSubmittedByCitizen()) {
                        return 1; // statements submitted by citizens
                    }

                    return 2; // statements submitted by organizations
                }
            );
        $clusters = collect($groups->get(0, []))->all();
        usort($clusters, $this->sort(...));
        $citizens = collect($groups->get(1, []))->all();
        usort($citizens, $this->sort(...));
        $institutions = collect($groups->get(2, []))->all();
        usort($institutions, $this->sort(...));

        return array_merge($clusters, $institutions, $citizens);
    }

    /**
     * @param Statement|StatementFragment $a
     * @param Statement|StatementFragment $b
     *
     * @return int
     */
    protected function sort($a, $b)
    {
        $statementA = $this->getStatement($a);
        $statementB = $this->getStatement($b);
        $orgaCmp = strcasecmp(
            $statementA->getMeta()->getOrgaName(),
            $statementB->getMeta()->getOrgaName()
        );
        if (0 !== $orgaCmp) {
            return $orgaCmp;
        }
        $departmentCmp = strcasecmp(
            $statementA->getMeta()->getOrgaDepartmentName(),
            $statementB->getMeta()->getOrgaDepartmentName()
        );
        if (0 !== $departmentCmp) {
            return $departmentCmp;
        }
        if (Statement::EXTERNAL === $statementA->getPublicStatement()) {
            return strcasecmp(
                $statementA->getMeta()->getAuthorName(),
                $statementB->getMeta()->getAuthorName()
            );
        }

        return strcasecmp(
            $statementA->getMeta()->getSubmitName(),
            $statementB->getMeta()->getSubmitName()
        );
    }

    /**
     * @param Statement|StatementFragment $statementOrFragment
     */
    protected function getStatement($statementOrFragment): Statement
    {
        if ($statementOrFragment instanceof StatementFragment) {
            return $statementOrFragment->getStatement();
        }
        if ($statementOrFragment instanceof Statement) {
            return $statementOrFragment;
        }
        $type = gettype($statementOrFragment);
        throw new InvalidArgumentException("unexpected type: {$type}");
    }
}
