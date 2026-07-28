<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\ProcedurePhaseDefinition;

use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DemosPlanPhaseDefinitionController extends BaseController
{
    #[DplanPermissions('area_customer_procedure_phase_definitions')]
    #[Route(path: '/procedure/phase-definitions', name: 'DemosPlan_procedure_phases_definition', methods: ['GET'])]
    public function procedurePhaseDefinitions(): Response
    {
        return $this->render(
            '@DemosPlanCore/DemosPlanProcedurePhasesDefinition/procedure_phases_definition.html.twig',
            ['title' => 'phases.definition']
        );
    }
}
