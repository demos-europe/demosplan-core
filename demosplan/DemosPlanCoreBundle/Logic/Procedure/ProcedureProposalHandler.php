<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureProposal;
use demosplan\DemosPlanCoreBundle\Logic\ArrayHelper;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Services\ApiResourceService;
use demosplan\DemosPlanCoreBundle\Transformers\Map\MapOptionsTransformer;
use Exception;

class ProcedureProposalHandler extends CoreHandler
{
    public function __construct(private readonly ArrayHelper $arrayHelper, MessageBagInterface $messageBag, private readonly ProcedureProposalService $procedureProposalService, private readonly ApiResourceService $resourceService, private readonly MapService $mapService)
    {
        parent::__construct($messageBag);
    }

    /**
     * @throws Exception
     */
    public function addProcedureProposal(array $incomingData): ProcedureProposal
    {
        $sanitizationSettings = [
            'strip_tags'  => [
                'name',
                'description',
                'coordinate',
            ],
            'rename_only' => [
                'additionalExplanation',
                'uploadedFiles',
            ],
            'files'       => [
                'uploadedFiles',
            ],
        ];
        $procedureProposalData = $this->sanitizeVariables($incomingData, $sanitizationSettings);

        return $this->procedureProposalService->addProcedureProposal($procedureProposalData);
    }

    /**
     * Filter out only selected variables and apply predefined filters to them.
     */
    protected function sanitizeVariables(array $input, array $sanitizationSettings): array
    {
        $output = [];
        foreach ($sanitizationSettings as $settingKey => $settingValue) {
            foreach ($settingValue as $fieldName) {
                $output = $this->arrayHelper->addToArrayIfKeyExists($output, $input, $fieldName);
            }
            if ('strip_tags' === $settingKey) {
                foreach ($settingValue as $fieldName) {
                    $output[$fieldName] = strip_tags((string) $output[$fieldName]);
                }
            }
            if ('files' === $settingKey) {
                foreach ($settingValue as $fieldName) {
                    if (array_key_exists('uploadedFiles', $input) && '' !== $input['uploadedFiles']) {
                        $output[$fieldName] = explode(',', (string) $input[$fieldName]);
                    }
                }
            }
        }

        return $output;
    }

    /**
     * @throws Exception
     */
    public function transformedMapOptions(): string
    {
        $mapOptions = $this->mapService->getMapOptions(null);

        $fractal = $this->resourceService->getFractal();

        $optionsResource = $this->resourceService->makeItem($mapOptions, MapOptionsTransformer::class);

        return Json::encode($fractal->createData($optionsResource)->toArray());
    }
}
