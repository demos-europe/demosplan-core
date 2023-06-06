<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;


use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use ReflectionException;

class StatementUnifiedDataFormatter
{
    public function __construct(private StatementService $statementService)
    {
    }

    /**
     * Statement in unified data format.
     *
     * @return array - formatted statement
     *
     * @throws ReflectionException
     *
     * @deprecated Use {@link formatStatementObject} instead
     */
    public function formatStatementArray(array $statement): array
    {
        return [
            'type'                      => 'statement',
            'attachments'               => $statement['attachments'] ?? null,
            'authoredDate'              => $statement['meta']['authoredDate'] ?? null,
            'cluster'                   => $statement['cluster'] ?? null,
            'documentTitle'             => $statement['document']['title'] ?? null,
            'externId'                  => $statement['externId'] ?? null,
            'formerExternId'            => $statement['formerExternId'] ?? null,
            'elementTitle'              => $statement['element']['title'] ?? null,
            'files'                     => $statement['files'] ?? null,
            'orgaName'                  => $statement['meta']['orgaName'] ?? null,
            'orgaDepartmentName'        => $statement['meta']['orgaDepartmentName'] ?? null,
            'originalId'                => $statement['original']['ident'] ?? null,
            'paragraphTitle'            => $statement['paragraph']['title'] ?? null,
            'parentId'                  => $statement['parent']['ident'] ?? null,
            'polygon'                   => $statement['polygon'] ?? null,
            'publicAllowed'             => $statement['publicAllowed'] ?? null,
            'publicCheck'               => $statement['publicCheck'] ?? null,
            'publicStatement'           => $statement['publicStatement'] ?? null,
            'publicVerified'            => $statement['publicVerified'] ?? null,
            'publicVerifiedTranslation' => $statement['publicVerifiedTranslation'] ?? null,
            'recommendation'            => $statement['recommendation'] ?? null,
            'submit'                    => $statement['submit'] ?? null,
            'submitName'                => $statement['meta']['submitName'] ?? null,
            'authorName'                => $statement['meta']['authorName'] ?? null,
            'text'                      => $statement['text'] ?? null,
            'votes'                     => $statement['votes'] ?? null,
            'votesNum'                  => $statement['votesNum'] ?? null,
            'likesNum'                  => $statement['likesNum'] ?? null,
            'fragments'                 => [],
            'userState'                 => $statement['meta']['userState'] ?? null,
            'userGroup'                 => $statement['meta']['userGroup'] ?? null,
            'userOrganisation'          => $statement['meta']['userOrganisation'] ?? null,
            'movedToProcedureName'      => $statement['movedToProcedureName'] ?? null,
            'movedFromProcedureName'    => $statement['movedFromProcedureName'] ?? null,
            'userPosition'              => $statement['meta']['userPosition'] ?? null,
            'isClusterStatement'        => $statement['isClusterStatement'] ?? null,
            'name'                      => $statement['name'] ?? null,
            'isSubmittedByCitizen'      => $statement['isSubmittedByCitizen'] ?? null,
        ];
    }

    /**
     * Statement in unified data format.
     *
     * @return array formatted statement
     *
     * @throws ReflectionException
     */
    public function formatStatementObject(Statement $statement): array
    {
        $item = $this->statementService->convertToLegacy($statement);
        $item['parent'] = $this->statementService->convertToLegacy($statement->getParent());
        $item['original'] = $this->statementService->convertToLegacy($statement->getOriginal());

        return $this->formatStatementArray($item);
    }
}
