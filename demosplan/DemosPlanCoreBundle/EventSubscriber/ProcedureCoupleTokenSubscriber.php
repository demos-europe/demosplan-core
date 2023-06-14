<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\Events\GetPropertiesEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\PostNewProcedureCreatedEventInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureCoupleToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\BeforeResourceDeletionEvent;
use demosplan\DemosPlanCoreBundle\Event\BeforeResourceUpdateEvent;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use demosplan\DemosPlanCoreBundle\Event\Procedure\EventConcern;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\GetInternalPropertiesEvent;
use demosplan\DemosPlanCoreBundle\Logic\TokenFactory;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Repository\EntitySyncLinkRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureCoupleTokenRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use demosplan\DemosPlanProcedureBundle\Exception\ProcedureCoupleTokenAlreadyUsedException;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanProcedureBundle\Logic\PrepareReportFromProcedureService;
use EDT\JsonApi\ResourceTypes\PropertyBuilder;
use EDT\PathBuilding\End;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProcedureCoupleTokenSubscriber extends BaseEventSubscriber
{
    private const SYNCHRONIZED_PROPERTY = 'synchronized';

    /**
     * Key of the related event concern.
     */
    public const IDENTIFIER = 'ProcedureCoupleTokenSubscriber';

    /**
     * @var CurrentUserInterface
     */
    private $currentUserProvider;

    /**
     * @var ProcedureCoupleTokenRepository
     */
    private $tokenRepository;

    /**
     * @var TokenFactory
     */
    private $tokenFactory;

    /**
     * @var PrepareReportFromProcedureService
     */
    private $prepareReportFromProcedureService;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    /**
     * @var EntitySyncLinkRepository
     */
    private $entitySyncLinkRepository;

    /**
     * @var StatementResourceType
     */
    private $statementResourceType;

    /**
     * @var CurrentProcedureService
     */
    private $currentProcedureProvider;

    public function __construct(
        CurrentProcedureService $currentProcedureProvider,
        CurrentUserInterface $currentUserProvider,
        EntitySyncLinkRepository $entitySyncLinkRepository,
        MessageBagInterface $messageBag,
        PrepareReportFromProcedureService $prepareReportFromProcedureService,
        ProcedureCoupleTokenRepository $tokenRepository,
        StatementResourceType $statementResourceType,
        TokenFactory $tokenFactory,
        TranslatorInterface $translator
    ) {
        $this->currentUserProvider = $currentUserProvider;
        $this->prepareReportFromProcedureService = $prepareReportFromProcedureService;
        $this->tokenFactory = $tokenFactory;
        $this->tokenRepository = $tokenRepository;
        $this->translator = $translator;
        $this->messageBag = $messageBag;
        $this->entitySyncLinkRepository = $entitySyncLinkRepository;
        $this->statementResourceType = $statementResourceType;
        $this->currentProcedureProvider = $currentProcedureProvider;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeResourceUpdateEvent::class                => 'preventUpdateAndDeletion',
            BeforeResourceDeletionEvent::class              => 'preventUpdateAndDeletion',
            PostNewProcedureCreatedEventInterface::class    => [
                ['createTokenForProcedure'],
                ['coupleProcedures'],
            ],
            GetPropertiesEventInterface::class              => 'addProperties',
            GetInternalPropertiesEvent::class               => 'addInternalProperties',
        ];
    }

    /**
     * @param BeforeResourceUpdateEvent|BeforeResourceDeletionEvent $event
     */
    public function preventUpdateAndDeletion(DPlanEvent $event): void
    {
        if (!$event->getResourceType() instanceof StatementResourceType) {
            return;
        }

        /** @var Statement $statement */
        $statement = $event->getEntity();
        $link = $this->entitySyncLinkRepository->findOneBy([
            'class'    => Statement::class,
            'sourceId' => $statement->getId(),
        ]);

        if (null !== $link) {
            throw new InvalidArgumentException("Synchronized statements can't be updated.");
        }
    }

    public function createTokenForProcedure(PostNewProcedureCreatedEventInterface $event): void
    {
        try {
            // Note that this may not always be the creating user, e.g. when a procedure is created via XTA.
            if (!$this->currentUserProvider->hasPermission('feature_procedure_couple_token_autocreate')) {
                return;
            }

            $procedure = $event->getProcedure();
            if ($procedure->getMaster()) {
                return;
            }

            $token = $this->tokenFactory->createSaltedToken($procedure->getId(), ProcedureCoupleToken::TOKEN_LENGTH);
            $this->tokenRepository->createAndFlushEntity($procedure, $token);
        } catch (Exception $exception) {
            $event->addCriticalEventConcern(
                self::IDENTIFIER,
                new EventConcern(
                    $this->translator->trans('error.procedure.token.creation'),
                    $exception
                )
            );
        }
    }

    public function coupleProcedures(PostNewProcedureCreatedEventInterface $event): void
    {
        try {
            // Note that this may not always be the creating user, e.g. when a procedure is created via XTA.
            if (!$this->currentUserProvider->hasPermission('feature_procedure_couple_by_token')) {
                return;
            }

            if ($event->getProcedure()->getMaster()) {
                return;
            }

            if (null === $event->getToken()) {
                return;
            }

            $user = $this->currentUserProvider->getUser();
            $targetProcedure = $event->getProcedure();
            $token = $this->tokenRepository->coupleProcedure($targetProcedure, $event->getToken());
            $this->prepareReportFromProcedureService->addReportsOnProcedureCouple($token, $user);

            $this->messageBag->add(
                'confirm',
                'confirm.procedureCouple.created',
                ['name' => $token->getSourceProcedure()->getName()]
            );
        } catch (ResourceNotFoundException $exception) {
            $event->addCriticalEventConcern(
                self::IDENTIFIER,
                new EventConcern($this->translator->trans('error.procedure.token.not.found'), $exception)
            );
        } catch (ProcedureCoupleTokenAlreadyUsedException $exception) {
            $event->addCriticalEventConcern(
                self::IDENTIFIER,
                new EventConcern($this->translator->trans('error.procedure.token.already.used'), $exception)
            );
        } catch (InvalidDataException $exception) {
            $event->addCriticalEventConcern(
                self::IDENTIFIER,
                new EventConcern($this->translator->trans('error.procedure.already.deleted'), $exception)
            );
        } catch (ViolationsException $exception) {
            foreach ($exception->getViolationsAsStrings() as $violationMessage) {
                $event->addCriticalEventConcern(
                    self::IDENTIFIER,
                    new EventConcern($violationMessage, $exception)
                );
            }
        } catch (Exception $exception) {
            $event->addCriticalEventConcern(
                self::IDENTIFIER,
                new EventConcern($this->translator->trans('error.coupleProcedure.unspecific'), $exception)
            );
        }
    }

    public function addProperties(GetPropertiesEventInterface $event): void
    {
        // The synchronized property is added to statement resource only
        if (!$event->getType() instanceof StatementResourceType) {
            return;
        }

        // No procedure context, then no synchronized property
        $currentProcedure = $this->currentProcedureProvider->getProcedure();
        if (null === $currentProcedure) {
            return;
        }

        // Only a source procedure coupled to another one is allowed to know about the
        // synchronized property
        $token = $this->tokenRepository->getTokenForCoupledProcedure($currentProcedure);
        if (null === $token) {
            return;
        }

        $path = new End();
        $path->setParent($this->statementResourceType);
        $path->setParentPropertyName(self::SYNCHRONIZED_PROPERTY);
        $property = new PropertyBuilder($path, $this->statementResourceType->getEntityClass());
        $property->readable(false, function (Statement $statement): bool {
            return null !== $this->entitySyncLinkRepository->findOneBy([
                'sourceId' => $statement->getId(),
                'class'    => Statement::class,
            ]);
        });
        $event->addProperty($property);
    }

    public function addInternalProperties(GetInternalPropertiesEvent $event): void
    {
        // The synchronized property is added to statement resource only
        if (!$event->getType() instanceof StatementResourceType) {
            return;
        }

        $properties = $event->getProperties();
        $properties[self::SYNCHRONIZED_PROPERTY] = null;
        $event->setProperties($properties);
    }
}
