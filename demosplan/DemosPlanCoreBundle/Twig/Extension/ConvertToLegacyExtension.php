<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Exception;
use Psr\Container\ContainerInterface;
use Twig\TwigFilter;

/**
 * Class ConvertToLegacyExtension.
 *
 * This is meant as a temporary fix to make refactoring around statements and draftstatements easier. Ideally, it will
 * be obsolete once we have a universal transformer extension processing all possible inputs.
 *
 * At the moment it can only handle statements and draftStatements.
 */
class ConvertToLegacyExtension extends ExtensionBase
{
    public function __construct(
        ContainerInterface $container,
        private readonly DraftStatementService $draftStatementService,
        private readonly StatementService $statementService)
    {
        parent::__construct($container);
    }

    /**
     * (non-PHPdoc).
     *
     * @see AbstractExtension::getFilters()
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('convertToLegacy', $this->convertToLegacy(...)),
        ];
    }

    /**
     * Check whether value is not defined, then return given return value.
     *
     * @throws Exception
     */
    public function convertToLegacy(array $statements): array
    {
        $returnArray = [];
        foreach ($statements as $statement) {
            if ($statement instanceof DraftStatement) {
                $returnArray[] = $this->draftStatementService->convertToLegacy($statement);
            }
            if ($statement instanceof Statement) {
                $returnArray[] = $this->statementService->convertToLegacy($statement);
            }
        }

        return $returnArray;
    }
}
