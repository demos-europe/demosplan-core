<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class StatementGroupCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly StatementHandler $statementHandler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $request = $context['request'];
        $body = json_decode((string) $request->getContent(), true);

        $procedureId = $uriVariables['procedureId'];
        $clusterName = $body['data']['attributes']['clusterName'] ?? null;
        $headStatementId = $body['data']['attributes']['headStatementId'] ?? null;
        $statementIds = array_column($body['data']['relationships']['statements']['data'] ?? [], 'id');

        if (null === $headStatementId || '' === $headStatementId) {
            throw new BadRequestHttpException('headStatementId is required');
        }

        if ([] === $statementIds) {
            throw new BadRequestHttpException('At least one statement relationship is required');
        }

        $this->statementHandler->createStatementCluster(
            $procedureId,
            $statementIds,
            $headStatementId,
            $clusterName
        );

        return new JsonResponse(
            [
                'data' => [
                    'type'          => 'statement',
                    'attributes'    => [
                        'clusterName'     => $clusterName,
                        'headStatementId' => $headStatementId,
                    ],
                    'relationships' => [
                        'statements' => [
                            'data' => array_map(
                                static fn (string $id): array => ['id' => $id, 'type' => 'statement'],
                                $statementIds
                            ),
                        ],
                    ],
                ],
            ],
            Response::HTTP_CREATED,
            ['Content-Type' => 'application/vnd.api+json']
        );
    }
}
