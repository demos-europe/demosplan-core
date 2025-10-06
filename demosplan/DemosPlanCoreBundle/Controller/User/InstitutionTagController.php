<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InstitutionTagController extends BaseController
{
    /**
     * @DplanPermissions("area_institution_tag_manage")
     */
    #[Route(name: 'DemosPlan_get_institution_tag_management', path: '/institutions/tags', methods: ['GET'], options: ['expose' => true])]
    public function getInstitutionTagManagement(): Response
    {
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/institution_tag_management.html.twig',
            [
                'title' => 'institution.tags.management',
            ]
        );
    }
}
