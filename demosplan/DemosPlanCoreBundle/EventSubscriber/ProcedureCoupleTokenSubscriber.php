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

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\GetPropertiesEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\PostNewProcedureCreatedEventInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureCoupleToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\Procedure\EventConcern;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureCoupleTokenAlreadyUsedException;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\GetPropertiesEvent;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\PrepareReportFromProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\TokenFactory;
use demosplan\DemosPlanCoreBundle\Repository\EntitySyncLinkRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureCoupleTokenRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\Event\BeforeDeletionEvent;
use EDT\JsonApi\Event\BeforeUpdateEvent;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilder;
use EDT\JsonApi\Utilities\PropertyBuilderFactory;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProcedureCoupleTokenSubscriber extends BaseEventSubscriber
{
    private const SYNCHRONIZED_PROPERTY = 'synchronized';

    /**
     * Key of the related event concern.
     */
    final public const IDENTIFIER = 'ProcedureCoupleTokenSubscriber';

    public function __construct(
        private readonly CurrentProcedureService $currentProcedureProvider,
        private readonly CurrentUserInterface $currentUserProvider,
        private readonly EntitySyncLinkRepository $entitySyncLinkRepository,
        private readonly MessageBagInterface $messageBag,
        private readonly PrepareReportFromProcedureService $prepareReportFromProcedureService,
        private readonly ProcedureCoupleTokenRepository $tokenRepository,
        private readonly PropertyBuilderFactory $propertyBuilderFactory,
        private readonly StatementResourceType $statementResourceType,
        private readonly TokenFactory $tokenFactory,
        protected TranslatorInterface $translator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeUpdateEvent::class                        => 'preventUpdateAndDeletion',
            BeforeDeletionEvent::class                      => 'preventUpdateAndDeletion',
            PostNewProcedureCreatedEventInterface::class    => [
                ['createTokenForProcedure'],
                ['coupleProcedures'],
            ],
            GetPropertiesEventInterface::class      => 'addProperties',
        ];
    }

    public function preventUpdateAndDeletion(BeforeUpdateEvent|BeforeDeletionEvent $event): void
    {
        $type = $event->getType();
        if (!$type instanceof StatementResourceType) {
            return;
        }

        $link = $this->entitySyncLinkRepository->findOneBy([
            'class'    => Statement::class,
            'sourceId' => $event->getEntityIdentifier(),
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

    public function addProperties(GetPropertiesEvent $event): void
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

        // add `synchronized` attribute
        $configBuilder = $event->getConfigBuilder();
        $attributeConfigBuilder = $this->createSynchronizedAttribute();
        $configBuilder->setAttributeConfigBuilder(self::SYNCHRONIZED_PROPERTY, $attributeConfigBuilder);
    }

    /**
     * @return AttributeConfigBuilder<ClauseFunctionInterface<bool>, Statement>
     */
    protected function createSynchronizedAttribute(): AttributeConfigBuilder
    {
        $attributeConfigBuilder = $this->propertyBuilderFactory->createAttribute(
            $this->statementResourceType->getEntityClass(),
            self::SYNCHRONIZED_PROPERTY
        );
        $attributeConfigBuilder->readable(
            false,
            fn (Statement $statement): bool => null !== $this->entitySyncLinkRepository->findOneBy([
                'sourceId' => $statement->getId(),
                'class'    => Statement::class,
            ])
        );

        return $attributeConfigBuilder;
    }
}
