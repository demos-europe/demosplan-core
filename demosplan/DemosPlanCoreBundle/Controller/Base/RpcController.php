<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Base;

use Carbon\Exceptions\InvalidFormatException;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\ValueObject\RpcRequestData;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\Validation;

class RpcController extends BaseController
{
    /**
     * @param array{actions:array, data:array} $requiredFields
     *
     * @throws JsonException
     */
    protected function getIncomingRpcData(Request $request, array $requiredFields): RpcRequestData
    {
        $requestData = $this->extractDataFromRequest($request);

        $rpc = new RpcRequestData();
        $rpc->setActions($this->getActions($requestData, $requiredFields));
        $rpc->setData($this->getData($requestData, $requiredFields));
        $rpc->lock();

        return $rpc;
    }

    /**
     * @return array<string,array<string,mixed>> array with two arrays which have the keys 'actions' and 'data'
     *
     * @throws JsonException
     */
    private function extractDataFromRequest(Request $request): array
    {
        $content = $request->getContent();

        return Json::decodeToArray($content)['data'];
    }

    /**
     * Sanitizes input regarding allowed types and field names
     * todo: instead of making this code perfect, please consider using Symfony Forms or JSON Schema instead
     * todo: consider merging with the following method, but since this whole concept is still very fresh, I didn't want
     *       to waste more time with this for now, good thing to do in refactoring days, once it passed the review.
     *
     * @param array<string,array<string,mixed>> $requestData    array with two arrays which have the keys 'actions' and
     *                                                          'data'
     * @param array<string,array<string,mixed>> $requiredFields array with two arrays which have the keys 'actions' and
     *                                                          'data'
     *
     * @return array<string,mixed>
     */
    private function getActions(array $requestData, array $requiredFields): array
    {
        if (array_key_exists('actions', $requiredFields) && array_key_exists('actions', $requestData) &&
            0 < count($requestData['actions']) && 0 < count($requiredFields['actions'])
        ) {
            $providedActions = $requestData['actions'];
            $allowedActions = $requiredFields['actions'];
            $output = [];
            foreach ($allowedActions as $allowedActionName => $allowedActionType) {
                if (
                    array_key_exists($allowedActionName, $providedActions) &&
                    $this->generallyIgnoreAllNullValuesInRpcActions($providedActions, $allowedActionName)
                ) {
                    $wrongTypeProvided = false;
                    if ('bool' === $allowedActionType) {
                        $value = $providedActions[$allowedActionName];
                        if (is_bool($value)) {
                            $output[$allowedActionName] = $value;
                        } else {
                            $wrongTypeProvided = true;
                        }
                    } elseif ('string' === $allowedActionType) {
                        $value = $providedActions[$allowedActionName];
                        if (is_string($value)) {
                            $output[$allowedActionName] = $value;
                        } else {
                            $wrongTypeProvided = true;
                        }
                    } else {
                        throw new NotYetImplementedException(sprintf('Please implement the type %s for RPCs.', $allowedActionType));
                    }
                    if ($wrongTypeProvided) {
                        throw new InvalidTypeException(sprintf('Wrong type provided for %s', $allowedActionName));
                    }
                }
            }

            return $output;
        }
        throw new InvalidFormatException('Invalid RPC format provided.');
    }

    /**
     * This method serves as documentation to the rule that NULL actions should be ignored, per convention.
     */
    private function generallyIgnoreAllNullValuesInRpcActions(array $providedActions, string $allowedActionName): bool
    {
        return null !== $providedActions[$allowedActionName];
    }

    /**
     * @param array<string,array<string,mixed>> $requestData    array with two arrays which have the keys 'actions' and
     *                                                          'data'
     * @param array<string,array<string,mixed>> $requiredFields array with two arrays which have the keys 'actions' and
     *                                                          'data'
     *
     * @return array<string,mixed>
     */
    private function getData(array $requestData, array $requiredFields): array
    {
        $output = [];
        $isFilledRequest = array_key_exists('data', $requiredFields)
            && array_key_exists('data', $requestData)
            && 0 < count($requestData['data'])
            && 0 < count($requiredFields['data']);

        if (!$isFilledRequest) {
            return $output;
        }
        $allowedData = $requiredFields['data'];
        $providedData = $requestData['data'];
        foreach ($allowedData as $allowedDataName => $allowedDataType) {
            if (array_key_exists($allowedDataName, $providedData)) {
                if ('UUID' !== $allowedDataType) {
                    throw new NotYetImplementedException(sprintf('Please implement the type %s for RPCs.', $allowedDataType));
                }
                $value = $providedData[$allowedDataName];
                $validator = Validation::createValidator();
                $violations = $validator->validate($value, new Uuid());
                if (0 !== $violations->count()) {
                    throw new InvalidTypeException('Invalid UUID format provided');
                }
                $output[$allowedDataName] = $value;
            }
        }

        return $output;
    }
}
