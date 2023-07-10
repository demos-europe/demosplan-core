<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Utils\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class NamespaceMigrationRule extends AbstractRector
{

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change namespace from the old namespace to the new one.', [
                new CodeSample(
                    // code before
                    'oldNamespace1\oldNamespace2',
                    // code after
                    'newNamespace1\newNamespace2'
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [FullyQualified::class];
    }

    /**
     * @param FullyQualified $node
     */
    public function refactor(Node $node)
    {
        $nodeParts = $node->getParts();
        if ('demosplan' !== $nodeParts[0] || 'DemosPlanCoreBundle' !== $nodeParts[1]) {
            return null;
        }

        $nodeParts[0] = 'DemosEurope';
        $nodeParts[1] = 'Demosplan';

        // I couldn't find a non-deprecated way of doing this
        $node->parts = $nodeParts;

        return $node;
    }
}
