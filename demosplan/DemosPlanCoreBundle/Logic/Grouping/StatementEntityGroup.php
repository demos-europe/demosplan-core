<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Grouping;

use function array_map;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use JsonSerializable;

/**
 * Entity groups for statements.
 *
 * Statement Entity groups are used in the assessment table, both for
 * display via the API and to help with creating the various exports.
 *
 * @template-extends AbstractEntityGroup<Statement>
 */
class StatementEntityGroup extends AbstractEntityGroup implements JsonSerializable
{
    /**
     * This serialization is tailored specifically for displaying the grouped assessment table views.
     */
    public function jsonSerialize(): array
    {
        return [
            'title'     => $this->getTitle(),
            'level'     => $this->getLevel(),
            'subgroups' => array_values($this->getSubgroups()),
            'entries'   => array_values(
                array_map(static fn(Statement $statement) => $statement->getId(), $this->getEntries())
            ),
            'total'     => $this->getTotal(),
        ];
    }
}
