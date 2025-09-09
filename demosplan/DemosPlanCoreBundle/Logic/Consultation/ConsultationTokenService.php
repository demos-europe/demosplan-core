<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Consultation;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ConsultationToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Event\ConsultationTokenCreatedEvent;
use demosplan\DemosPlanCoreBundle\Event\ConsultationTokenStatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Repository\ConsultationTokenRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ConsultationTokenResourceType;
use Doctrine\ORM\EntityNotFoundException;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\SortMethodInterface;
use Exception;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ConsultationTokenService
{
    /**
     * Values allowed to be used for the token.
     *
     * Possibly confusing characters (`1`, `i`, `l`, `0`, `O`, `o`) are not included.
     */
    private const TOKEN_CHARACTERS = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ';

    public function __construct(
        private readonly ConsultationTokenRepository $consultationTokenRepository,
        private readonly ConsultationTokenResourceType $consultationTokenResourceType,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly ElementsService $elementsService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly PermissionsInterface $permissions,
        private readonly SortMethodFactory $sortMethodFactory,
        private readonly StatementHandler $statementHandler,
        private readonly StatementService $statementService,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * This method will automatically create an original statement and a copy in the assessment
     * table. Both are set in the corresponding properties in the (also) automatically created token.
     */
    public function createTokenStatement(
        string $submitterName,
        string $submitterEmailAddress,
        string $note,
        string $submitterCity,
        string $submitterPostalCode,
        string $submitterStreet,
        string $submitterHouseNumber
    ): void {
        $this->permissions->checkPermission('area_admin_consultations');

        // Get the procedure to use, must not be null.
        $procedure = $this->currentProcedureService->getProcedureWithCertainty('Expected a procedure to create the token for.');

        $procedureId = $procedure->getId();
        $externId = $this->statementService->getNextValidExternalIdForProcedure($procedureId, true);
        $element = $this->elementsService->getStatementElement($procedureId);

        $originalStatementData = [
            'author_name'           => $submitterName,
            'civic'                 => true,
            'element'               => $element,
            'externId'              => $externId,
            'houseNumber'           => $submitterHouseNumber,
            'isManualStatement'     => true,
            'meta'                  => [
                StatementMeta::SUBMITTER_ROLE => StatementMeta::SUBMITTER_ROLE_CITIZEN,
            ],
            'orga_city'             => $submitterCity,
            'orga_postalcode'       => $submitterPostalCode,
            'orga_street'           => $submitterStreet,
            'pId'                   => $procedureId,
            'phase'                 => $this->getWritableExternalPhase(),
            'publicVerified'        => Statement::PUBLICATION_PENDING,
            'submit_name'           => $submitterName,
            'submit_type'           => Statement::SUBMIT_TYPE_UNKNOWN,
            'submittedDate'         => Carbon::now()->format('d.m.Y'),
            'submitterEmailAddress' => $submitterEmailAddress,
        ];

        $newOriginalStatement = $this->statementService->createOriginalStatement($originalStatementData);
        $copyOfStatement = $this->statementHandler->createNonOriginalStatement($originalStatementData, $newOriginalStatement);
        $this->eventDispatcher->dispatch(new ConsultationTokenStatementCreatedEvent($copyOfStatement, $note));
    }

    /**
     * Creates a token from the given parameters and automatically validates it.
     */
    public function createToken(Statement $statement, string $note = '', bool $manuallyCreated = false): void
    {
        $token = $this->createUniqueTokenString();

        $tokenEntity = new ConsultationToken($token, $statement, $manuallyCreated);
        $tokenEntity->setNote($note);

        $this->ensureValidity($tokenEntity);
        $this->consultationTokenRepository->persistAndDelete([$tokenEntity], []);

        $this->eventDispatcher->dispatch(new ConsultationTokenCreatedEvent($tokenEntity));
    }

    protected function createRandomString(int $length, string $allowedChars): string
    {
        $maxCharIndex = strlen($allowedChars) - 1;
        if (1 >= $maxCharIndex) {
            throw new InvalidArgumentException('Expected at least 2 allowed characters.');
        }
        $token = '';
        for ($i = 0; $i < $length; ++$i) {
            // maybe using random_int for each index is a bit overkill, but at least the result should be random
            // https://symfony.com/doc/current/components/security/secure_tools.html#generating-a-secure-random-number
            $index = random_int(0, $maxCharIndex);
            $token .= $allowedChars[$index];
        }

        return $token;
    }

    protected function createUniqueTokenString(): string
    {
        $emergencyCounter = 0;
        do {
            $token = $this->createRandomString(8, self::TOKEN_CHARACTERS);
            ++$emergencyCounter;
            if ($emergencyCounter > 1000) {
                throw new Exception('Couldn\' find an unreserved token after 1000 attempts.');
            }
        } while ($this->isTokenStringInUse($token));

        return $token;
    }

    protected function isTokenStringInUse(string $tokenString): bool
    {
        $result = $this->consultationTokenRepository->findBy([
            'token' => $tokenString,
        ]);

        return 0 !== count($result);
    }

    /**
     * @throws ViolationsException
     */
    protected function ensureValidity(ConsultationToken $token): void
    {
        $violations = $this->validator->validate($token);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findByTokenString(string $token): ConsultationToken
    {
        /** @var ?ConsultationToken $consultationToken */
        $consultationToken = $this->consultationTokenRepository->findOneBy(['token' => $token]);

        if (null === $consultationToken) {
            throw new EntityNotFoundException("Could not find ConsultationToken with token '{$token}'");
        }

        return $consultationToken;
    }

    /**
     * @param array<int, string> $sortParams
     *
     * @throws PathException
     */
    public function getTokenListFromResourceType(string $procedureId, array $sortParams): array
    {
        $condition = $this->conditionFactory
            ->allConditionsApply(
                $this->conditionFactory->propertyHasValue($procedureId, $this->consultationTokenResourceType->statement->procedure->id),
            );
        $sort = $this->getSortMethod($sortParams);

        return $this->consultationTokenResourceType->getEntities([$condition], [$sort]);
    }

    public function getTokenForStatement(Statement $statement): ?ConsultationToken
    {
        return $this->consultationTokenRepository->findOneBy([
            'statement' => $statement->getId(),
        ]);
    }

    public function updateEmailOfToken(ConsultationToken $token, MailSend $mailSend): void
    {
        $token->setSentEmail($mailSend);
        $this->ensureValidity($token);

        $this->consultationTokenRepository->persistAndDelete([$token], []);
    }

    private function getWritableExternalPhase()
    {
        $procedurePhases = $this->globalConfig->getExternalPhases();
        foreach ($procedurePhases as $procedurePhase) {
            if ('write' === $procedurePhase['permissionset']) {
                return $procedurePhase['key'];
            }
        }

        return Procedure::PROCEDURE_PARTICIPATION_PHASE;
    }

    /**
     * @param array<int, string> $sortParams
     *
     * @throws PathException
     */
    private function getSortMethod(array $sortParams): SortMethodInterface
    {
        $sortProperty = match ($sortParams['key']) {
            'submitterEmailAddress' => $this->consultationTokenResourceType->statement->initialOrganisationEmail,
            'token'                 => $this->consultationTokenResourceType->token,
            'note'                  => $this->consultationTokenResourceType->note,
            default                 => $this->consultationTokenResourceType->statement->submitName,
        };
        if ('1' === $sortParams['direction']) {
            return $this->sortMethodFactory->propertyAscending($sortProperty);
        }

        return $this->sortMethodFactory->propertyDescending($sortProperty);
    }
}
