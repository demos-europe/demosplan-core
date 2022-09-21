<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Map;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\Maps\Xplanbox;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DemosPlanXplanboxController extends BaseController
{
    /**
     * Gib den Startkartenausschnitt zu einem Verfahren aus.
     *
     * @Route(
     *     name="DemosPlan_xplanbox_get_bounds",
     *     path="/xplanbox/getBounds/{procedureName}",
     *     requirements={"procedureName"=".+"},
     *     options={"expose": true},
     * )
     *
     * @DplanPermissions("feature_use_xplanbox")
     *
     * @param string $procedureName
     *
     * @return Response
     */
    public function getLgvXplanboxBoundsAction(Xplanbox $xplanbox, $procedureName)
    {
        try {
            $procedure = $xplanbox->getXplanboxBounds($procedureName);

            $response = [
                'code'    => 200,
                'success' => false,
            ];

            if (0 < count($procedure)) {
                //prepare the response
                $response = [
                    'code'      => 100,
                    'success'   => true,
                    'procedure' => $procedure,
                ];
            }

            //return result as JSON
            return new Response(Json::encode($response));
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }
}
