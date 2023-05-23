<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class ProcedureProposalNotFound extends ResourceNotFoundException
{
    /**
     * @return static
     */
    public static function createFromId(string $id): self
    {
        return new self("Procedure Proposal with ID {$id} was not found.");
    }
}
