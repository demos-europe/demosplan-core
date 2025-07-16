<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Logger\ApiLoggerInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\TopLevel;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ClaimResourceType;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\JsonApi\Validation\FieldsValidator;
use EDT\Wrapping\TypeProviders\PrefilledTypeProvider;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;

class DemosPlanClaimAPIController extends APIController
{
    public function __construct(
        private readonly ProcedureHandler $procedureHandler,
        private readonly StatementHandler $statementHandler,
        private readonly UserService $userService,
        ApiLoggerInterface $apiLogger,
        FieldsValidator $fieldsValidator,
        PrefilledTypeProvider $resourceTypeProvider,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        GlobalConfigInterface $globalConfig,
        MessageBagInterface $messageBag,
        MessageFormatter $messageFormatter,
        SchemaPathProcessor $schemaPathProcessor
    ) {
        parent::__construct(
            $apiLogger,
            $resourceTypeProvider,
            $fieldsValidator,
            $translator,
            $logger,
            $globalConfig,
            $messageBag,
            $schemaPathProcessor,
            $messageFormatter
        );
    }

    /**
     * @DplanPermissions("feature_statement_assignment")
     */
    #[Route(path: '/api/1.0/statement/{statementId}/relationships/assignee', methods: ['PATCH'], name: 'dplan_claim_statements_api', options: ['expose' => true])]
    public function updateStatementAssignmentAction(string $statementId): APIResponse
    {
        return $this->updateStatementOrStatementFragmentAssignment($statementId, Statement::class);
    }

    /**
     * @DplanPermissions("feature_statement_assignment")
     */
    #[Route(path: '/api/1.0/fragment/{entityId}/relationships/assignee', methods: ['PATCH'], name: 'dplan_claim_fragments_api', options: ['expose' => true])]
    public function updateFragmentAssignmentAction(string $entityId): APIResponse
    {
        return $this->updateStatementOrStatementFragmentAssignment($entityId, StatementFragment::class);
    }

    /**
     * API Method to assign & unassign users to statements or statementFragments.
     * For assignment, add userId, for unassignment add null (empty string is also supported but not welcome).
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/claim/ Wiki: Claim / Assign / Zuweisen
     *
     * @param string $class May be either statement class or statementFragment class
     *
     * @throws MessageBagException
     */
    private function updateStatementOrStatementFragmentAssignment(string $entityId, string $class): APIResponse
    {
        if (!in_array($class, [Statement::class, StatementFragment::class], true)) {
            throw new InvalidArgumentException('Invalid class, only statements or fragmentStatements are allowed.');
        }
        $messageArray = [
            Statement::class         => [
                'assigned'   => 'confirm.statement.assignment.assigned',
                'unassigned' => 'confirm.statement.assignment.unassigned',
                'changed'    => 'confirm.statement.assignment.changed',
                'error'      => 'error.statement.assignment.changed',
            ],
            StatementFragment::class => [
                'assigned'   => 'confirm.fragment.assignment.assigned',
                'unassigned' => 'confirm.fragment.assignment.unassigned',
                'changed'    => 'confirm.fragment.assignment.changed',
                'error'      => 'error.fragment.assignment.changed',
            ],
        ];
        try {
            if (!($this->requestData instanceof TopLevel)) {
                throw BadRequestException::normalizerFailed();
            }

            /** @var ResourceObject $resourceObjectUser */
            $resourceObjectUser = $this->requestData->getFirst('user');
            $assigneeIdUnvalidated = ($resourceObjectUser ? $resourceObjectUser->get('id') : null);
            $assigneeIdUnvalidated = ('' === $assigneeIdUnvalidated ? null : $assigneeIdUnvalidated);

            // get entity
            if (Statement::class === $class) {
                $entityToUpdate = $this->statementHandler->getStatement($entityId);
            } else { // statementFragment
                $entityToUpdate = $this->statementHandler->getStatementFragment($entityId);
            }
            if (null === $entityToUpdate) {
                throw new UnexpectedValueException('Could not find ID of statement / statementFragment ID: %s', $entityId);
            }

            // select and validate assignee user
            $assignee = null;
            $previousAssignee = $entityToUpdate->getAssignee();
            if (null !== $assigneeIdUnvalidated) {
                // get and validate list of authorized users
                $assignee = $this->userService->getSingleUser($assigneeIdUnvalidated);
                if (!$this->procedureHandler->isUserExistentAndAuthorized($entityToUpdate->getProcedureId(), $assignee) && (StatementFragment::class === $class && $assignee->getDepartment()->getId() !== $entityToUpdate->getDepartment()->getId())) {
                    // in case of statement fragments, user may be reviewer albeit not authorized for procedure
                    throw new AccessDeniedException('Tried to assign a user who is not authorized.');
                }
            }

            // update entity
            if (Statement::class === $class) {
                $this->statementHandler->setAssigneeOfStatement($entityToUpdate, $assignee);
            } else { // statementFragment
                $this->statementHandler->setAssigneeOfStatementFragment($entityToUpdate, $assignee);
            }

            // determine confirm messages
            $message = $messageArray[$class]['assigned'];
            if (null === $assigneeIdUnvalidated) {
                $message = $messageArray[$class]['unassigned'];
            } elseif (null !== $previousAssignee) {
                $message = $messageArray[$class]['changed'];
            }
            $this->messageBag->add('confirm', $message);

            // get new assignee and prepare assignee data for return
            $assignee = $entityToUpdate->getAssignee();

            if (null !== $assignee) {
                // case: assign or change
                $item = $this->resourceService->makeItemOfResource($assignee, ClaimResourceType::getName());

                return $this->renderResource($item);
            }

            // case: reset
            return $this->renderEmpty();
        } catch (Exception $e) {
            $this->messageBag->add('error', $messageArray[$class]['error']);

            return $this->handleApiError($e);
        }
    }
}
