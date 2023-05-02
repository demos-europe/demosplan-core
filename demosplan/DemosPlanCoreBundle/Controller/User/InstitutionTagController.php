<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InstitutionTagController extends BaseController
{
    /**
     * @Route(
     *     name="DemosPlan_get_institution_tag_management",
     *     path="/institutions/tags",
     *     methods={"GET"},
     *     options={"expose": true}
     * )
     *
     * @DplanPermissions("area_institution_tag_manage")
     */
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
