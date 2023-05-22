<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class StatementElementNotFoundException extends ResourceNotFoundException
{
    /**
     * @return static
     */
    public static function create(): self
    {
        return new self('No matching element could be found.');
    }

    public static function createFromId(string $id): self
    {
        return new self("StatementElement with ID {$id} was not found.");
    }

    public static function missingParent(string $parentId): self
    {
        return new self("Can't create planning document category with parent ID '$parentId' for which no planning document category exists");
    }
}
