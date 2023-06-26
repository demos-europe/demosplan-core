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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\ManualStatementCreatedEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\StatementCreatedEventInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\ConsultationTokenStatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\ConsultationTokenService;

/**
 * Take care of consultation tokens for new statements.
 *
 * This handles the creation of tokens for newly created statements.
 *
 * Case 1)
 *
 * Actively created statements either by planners or by participants.
 * Tokens for these statements are automatic, i.e. `isManual: false`,
 * and they don't have a note set on the token as the corresponding
 * field does not exist in the various statement creation forms.
 *
 * Case 2)
 *
 * The consultation list allows adding new Tokens (`isManual: true`).
 * Doing that creates a shadow statement which can later be filled
 * with the remaining information by the planners.
 *
 * The two cases are separated by different events.
 */
class StatementCreationConsultationSubscriber extends BaseEventSubscriber
{
    /**
     * @var ConsultationTokenService
     */
    private $consultationService;

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    public function __construct(
        ConsultationTokenService $consultationService,
        GlobalConfigInterface $globalConfig,
        MessageBagInterface $messageBag,
        PermissionsInterface $permissions)
    {
        $this->consultationService = $consultationService;
        $this->permissions = $permissions;
        $this->messageBag = $messageBag;
        $this->globalConfig = $globalConfig;
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            StatementCreatedEventInterface::class                  => 'handleActivelyCreatedStatement',
            ManualStatementCreatedEventInterface::class            => 'handleActivelyCreatedStatement',
            ConsultationTokenStatementCreatedEvent::class          => 'handleTokenStatement',
        ];
    }

    public function handleActivelyCreatedStatement(StatementCreatedEventInterface $event): void
    {
        if (!$this->checkRequiredPermissions()) {
            return;
        }

        if ($event instanceof ConsultationTokenStatementCreatedEvent) {
            return;
        }

        $this->createTokenFromEvent($event, false);
    }

    public function handleTokenStatement(ConsultationTokenStatementCreatedEvent $event): void
    {
        if (!$this->checkRequiredPermissions()) {
            return;
        }

        $this->createTokenFromEvent($event, true);
    }

    private function shouldCreateToken(Statement $statement): bool
    {
        $hasWritePermission = $this->hasPhaseWritePermission($statement->getPhase());
        $isPublicCitizenStatement = $statement->isCreatedByCitizen();
        $isPlannerCreatedCitizenStatement = $statement->isPlannerCreatedCitizenStatement();

        return $hasWritePermission && ($isPublicCitizenStatement || $isPlannerCreatedCitizenStatement);
    }

    private function hasPhaseWritePermission(string $phase): bool
    {
        $procedurePhases = $this->globalConfig->getExternalPhases();
        foreach ($procedurePhases as $procedurePhase) {
            if ($procedurePhase['key'] === $phase && 'write' === $procedurePhase['permissionset']) {
                return true;
            }
        }

        return false;
    }

    private function createTokenFromEvent(StatementCreatedEventInterface $event, bool $isManual): void
    {
        $statement = $event->getStatement();

        $note = '';
        if ($event instanceof ConsultationTokenStatementCreatedEvent) {
            $note = $event->getTokenNote();
        }

        if ($this->shouldCreateToken($statement)) {
            try {
                $this->consultationService->createToken(
                    $statement,
                    $note,
                    $isManual
                );
            } catch (ViolationsException $e) {
                $this->messageBag->addViolations($e->getViolations());
            }
        }
    }

    private function checkRequiredPermissions(): bool
    {
        return $this->permissions->hasPermissions(
            ['area_admin_consultations', 'feature_public_consultation'],
            'OR'
        );
    }
}
