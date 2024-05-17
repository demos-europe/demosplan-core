<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers\AssessmentTable;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ClaimResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable\StatementBulkEditVO;
use Exception;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\NullResource;
use League\Fractal\Resource\ResourceAbstract;

class StatementBulkEditTransformer extends BaseTransformer
{
    /** @var string */
    protected $type = 'StatementBulkEdit';

    protected array $availableIncludes = ['assignee', 'statements'];
    /** @var UserService */
    protected $userService;
    /** @var StatementService */
    protected $statementService;

    public function __construct(StatementService $statementService, UserService $userService)
    {
        $this->statementService = $statementService;
        $this->userService = $userService;
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    public function transform(StatementBulkEditVO $statementBulkEdit): array
    {
        $result = [
            'id' => $statementBulkEdit->getId(),
        ];
        $recommendationAddition = $statementBulkEdit->getRecommendationAddition();
        if (null !== $recommendationAddition) {
            $result['recommendationAddition'] = $recommendationAddition;
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function includeAssignee(StatementBulkEditVO $statementBulkEdit): ResourceAbstract
    {
        if (null === $statementBulkEdit->getAssigneeId()) {
            return new NullResource();
        }

        $userService = $this->getUserService();
        $assignee = $userService->getSingleUser($statementBulkEdit->getAssigneeId());

        return $this->resourceService->makeItemOfResource(
            $assignee,
            ClaimResourceType::getName()
        );
    }

    /**
     * @throws Exception
     */
    public function includeStatements(StatementBulkEditVO $statementBulkEdit): Collection
    {
        $statementService = $this->getStatementService();
        $statementIds = $statementBulkEdit->getStatementIdsInProcedure()->getStatementIds();
        $statements = $statementService->getStatementsByIds($statementIds);

        return $this->resourceService->makeCollectionOfResources(
            $statements,
            StatementResourceType::getName()
        );
    }

    public function getUserService(): UserService
    {
        return $this->userService;
    }

    public function getStatementService(): StatementService
    {
        return $this->statementService;
    }
}
