<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;

/**
 * @template-extends CoreRepository<ProcedurePhaseDefinition>
 *
 * @method ProcedurePhaseDefinition|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProcedurePhaseDefinition|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProcedurePhaseDefinition[]    findAll()
 * @method ProcedurePhaseDefinition[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProcedurePhaseDefinitionRepository extends CoreRepository
{
}
