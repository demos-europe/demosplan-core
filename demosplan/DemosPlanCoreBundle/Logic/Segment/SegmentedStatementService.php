<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\TopLevel;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanStatementBundle\Exception\NoAiServiceSaltConfiguredException;
use demosplan\DemosPlanStatementBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use demosplan\DemosPlanCoreBundle\Exception\MisconfiguredException;
use demosplan\DemosPlanCoreBundle\Transformers\Segment\ProposalsToDraftsInfoTransformer;
use Psr\Log\LoggerInterface;

class SegmentedStatementService
{
    /** @var StatementService */
    private $statementService;

    /** @var ProposalsToDraftsInfoTransformer */
    private $proposalsToDraftsInfoTransformer;
    /**
     * @var GlobalConfig|GlobalConfigInterface
     */
    private $globalConfig;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(StatementService $statementService, ProposalsToDraftsInfoTransformer $proposalsToDraftsInfoTransformer, GlobalConfigInterface $globalConfig, LoggerInterface $logger)
    {
        $this->statementService = $statementService;

        $this->proposalsToDraftsInfoTransformer = $proposalsToDraftsInfoTransformer;

        /** @var GlobalConfig $globalConfig */
        if ('' === $globalConfig->getAiServiceSalt() || '' === $globalConfig->getAiServicePostUrl()) {
            throw MisconfiguredException::missingParameters();
        }

        $this->globalConfig = $globalConfig;
        $this->logger = $logger;
    }

    public function updateStatement(
        TopLevel $requestData,
        ?string $piSegmentsProposalResourceUrl = null
    ): void {
        $this->logger->info('PI-Communication-Info: Before getObjectToCreate');
        $segmentedStatement = $requestData->getFirst('SegmentedStatement');
        $this->logger->info(Json::encode($segmentedStatement));
        $statementId = $segmentedStatement->get('relationships.statement.data.id');
        $this->logger->info('PI-Communication-Info: Received Statement Id: '.$statementId);
        $statement = $this->statementService->getStatement($statementId);
        if (null === $statement) {
            $this->logger->error('PI-Communication-Error: Statement not found');
            throw new BadRequestException('invalid data in meta field for statement ID', 0, StatementNotFoundException::createFromId($statementId));
        }
        $this->logger->info('PI-Communication-Info: Before transform');
        $draftsInfoJson = $this->proposalsToDraftsInfoTransformer->transform($segmentedStatement);
        $this->logger->info('PI-Communication-Info: After transform');
        $this->logger->info('PI-Communication-Info: Generated Json : '.$draftsInfoJson);
        $this->logger->info('PI-Communication-Info: Before Saving Drafts');
        $statement->setDraftsListJson($draftsInfoJson);
        if (null !== $piSegmentsProposalResourceUrl) {
            $statement->setPiSegmentsProposalResourceUrl($piSegmentsProposalResourceUrl);
        }
        $this->statementService->updateStatementFromObject($statement, true);
        $this->logger->info('PI-Communication-Info: After Saving Drafts');
    }

    /**
     * @throws NoAiServiceSaltConfiguredException
     */
    public function isAiRequestTokenValid(Statement $statement, string $token): bool
    {
        $salt = $this->globalConfig->getAiServiceSalt();
        if ('' === $salt) {
            $this->logger->error('PI-Communication-Error: No Salt configured');
            throw NoAiServiceSaltConfiguredException::create();
        }
        $hash = hash('sha256', $salt.$statement->getId());

        if ($hash !== $token) {
            $this->logger->error('PI-Communication-Error: Token not valid');
        }

        return $hash === $token;
    }
}
