<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Document;

use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DemosPlanElementsBulkEditController extends BaseController
{
    /**
     * @throws Exception
     */
    #[DplanPermissions('feature_admin_element_edit')]
    #[Route(name: 'dplan_elements_bulk_edit', methods: 'GET', path: '/verfahren/{procedureId}/planunterlagen/kategorien-bearbeiten', options: ['expose' => true])]
    public function showForm(string $procedureId): Response
    {
        return $this->render(
            '@DemosPlanCore/DemosPlanDocument/elements_admin_bulk_edit.html.twig',
            [
                'title'       => 'elements.bulk.edit',
            ]
        );
    }
}
