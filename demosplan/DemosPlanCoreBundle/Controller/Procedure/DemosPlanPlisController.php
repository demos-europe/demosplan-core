<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\Plis;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DemosPlanPlisController extends BaseController
{
    /**
     * Gib den Planungsanlass zu einem Verfahren aus der PLIS-Datenbank aus.
     *
     * @DplanPermissions("feature_use_plis")
     *
     * @param string $uuid
     *
     * @return Response
     */
    #[Route(name: 'DemosPlan_plis_get_procedure', path: '/plis/getProcedure/{uuid}', options: ['expose' => true])]
    public function getLgvPlisPlanningcauseAction(Plis $procedureHandlerBobhh, $uuid)
    {
        try {
            $procedure = $procedureHandlerBobhh->getLgvPlisPlanningcause($uuid);

            // prepare the response
            $response = [
                'code'    => 200,
                'success' => false,
            ];
            if (0 < count($procedure)) {
                // prepare the response
                $response = [
                    'code'      => 100,
                    'success'   => true,
                    'procedure' => $procedure,
                ];
            }

            // return result as JSON
            return new Response(Json::encode($response));
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * Gib den Namen zu einem Verfahren aus der PLIS-Datenbank aus.
     *
     * @DplanPermissions("feature_use_plis")
     *
     * @param string $uuid Procedure Identifier
     *
     * @return JsonResponse
     */
    #[Route(name: 'DemosPlan_plis_get_procedure_name', path: '/plis/getProcedureName/{uuid}', options: ['expose' => true])]
    public function getLgvPlisProcedureNameJsonAction(Plis $plis, $uuid)
    {
        try {
            $procedureList = $plis->getLgvPlisProcedureList();

            if (0 === count($procedureList)) {
                throw new Exception('Kein Verfahren gefunden');
            }
            $procedureName = '';
            foreach ($procedureList as $procedure) {
                if ($uuid == $procedure['uuid']) {
                    $procedureName = $procedure['procedureName'];
                    break;
                }
            }
            // prepare the response
            $response = [
                'code'          => 100,
                'success'       => true,
                'procedureName' => $procedureName,
            ];

            // return result as JSON
            return new JsonResponse($response);
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }
}
