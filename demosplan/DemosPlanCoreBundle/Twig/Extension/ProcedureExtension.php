<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use Exception;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\TwigFunction;

/**
 * Procedure specific functions.
 */
class ProcedureExtension extends ExtensionBase
{
    /**
     * @var Permissions
     */
    protected $permissions;

    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    public function __construct(
        ContainerInterface $container,
        private readonly CurrentUserInterface $currentUser,
        GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger,
        PermissionsInterface $permissions,
        private readonly ProcedureService $procedureService,
        private readonly TranslatorInterface $translator)
    {
        parent::__construct($container);
        $this->globalConfig = $globalConfig;
        $this->permissions = $permissions;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getProcedurePhase', $this->getPhase(...)),
            new TwigFunction('getProcedurePhaseKey', $this->getPhaseKey(...)),
            new TwigFunction('getProcedureStartDate', $this->getStartDate(...)),
            new TwigFunction('getProcedureEndDate', $this->getEndDate(...)),
            new TwigFunction('getProcedureName', $this->getNameFunction(...)),
            new TwigFunction('getProcedureDaysLeft', $this->getDaysLeft(...)),
            new TwigFunction('ownsProcedure', $this->ownsProcedure(...)),
            new TwigFunction('getProcedurePermissionset', $this->getProcedurePermissionset(...)),
        ];
    }

    /**
     * Get the translated phase of a procedure.
     * Default is the translation of the internal phase name.
     *
     * @param Procedure|array $procedure
     * @param string          $type       auto|public
     * @param string|null     $givenPhase
     *
     * @return string
     */
    public function getPhase($procedure, $type = 'auto', $givenPhase = null)
    {
        try {
            $procedure = $this->getProcedureObject($procedure);
        } catch (Exception) {
            return '';
        }

        $publicNameRequested = (
            'public' === $type
            || $this->isPublicUser()
            || (
                $this->ownsProcedure($procedure)
                && !$this->permissions->hasPermission('feature_institution_participation')
            )
        );

        // return external/public phaseName
        $phase = is_null($givenPhase) ? $procedure->getPublicParticipationPhase() : $givenPhase;
        if ($publicNameRequested && $this->permissions->hasPermission('area_public_participation')) {
            return $this->globalConfig->getPhaseNameWithPriorityExternal($phase);
        }

        $internalPhase = is_null($givenPhase) ? $procedure->getPhase() : $givenPhase;

        // return internal phaseName
        return $this->globalConfig->getPhaseNameWithPriorityInternal($internalPhase);
    }

    /**
     * @param Procedure|array $procedureSomething
     * @param string          $type               auto|public
     *
     * @return string
     */
    public function getPhaseKey($procedureSomething, $type = 'auto')
    {
        try {
            $procedure = $this->getProcedureObject($procedureSomething);
        } catch (Exception) {
            return '';
        }

        $publicNameRequested = ('public' === $type || $this->isPublicUser());

        if ($publicNameRequested && $this->permissions->hasPermission('area_public_participation')) {
            return $procedure->getPublicParticipationPhase();
        }

        return $procedure->getPhase();
    }

    /**
     * @param string $type auto|public
     *
     * @return int
     *
     * @throws InvalidArgumentException
     */
    public function getStartDate(array $procedure, $type = 'auto')
    {
        if (0 === count($procedure)) {
            throw new InvalidArgumentException('Got empty procedure: '.var_export($procedure, true));
        }

        if (// should return public date
            ('public' === $type || $this->isPublicUser())
            // can return public date
            && $this->permissions->hasPermission('area_public_participation')
            // has public date
            && array_key_exists('publicParticipationStartDate', $procedure)
        ) {
            if ($procedure['publicParticipationStartDate'] instanceof DateTime) {
                // hello database
                return $procedure['publicParticipationStartDate']->getTimestamp();
            }

            if (is_numeric($procedure['publicParticipationStartDate'])) {
                // hello elastic search, good bye objects
                // shedding tears over here
                return $procedure['publicParticipationStartDate'];
            }

            if (is_string($procedure['publicParticipationStartDate'])) {
                $carbon = Carbon::parse($procedure['publicParticipationStartDate']);

                return $carbon->getTimestamp();
            }
        }

        if (array_key_exists('startDate', $procedure)) {
            if ($procedure['startDate'] instanceof DateTime) {
                // hello database
                return $procedure['startDate']->getTimestamp();
            }

            if (is_numeric($procedure['startDate'])) {
                // hello elastic search, good bye objects
                // shedding tears over here
                return $procedure['startDate'];
            }

            if (is_string($procedure['startDate'])) {
                // hello elastic search, good bye objects
                // shedding tears over here
                // fun times, now we have an iso date. at least something that looks like one.
                $carbon = Carbon::parse($procedure['startDate']);

                return $carbon->getTimestamp();
            }
        }

        throw new InvalidArgumentException('Failed to determine requested start date value.');
    }

    /**
     * @param array  $procedure
     * @param string $type      auto|public
     *
     * @return int
     *
     * @throws InvalidArgumentException
     */
    public function getEndDate($procedure, $type = 'auto')
    {
        if ($procedure instanceof Procedure) {
            try {
                $procedureObject = $this->getProcedureObject($procedure);
            } catch (Exception $exception) {
                throw new RuntimeException('Got unretrievable procedure: '.var_export($procedure, true), 0, $exception);
            }

            return $this->getEndDateFromProcedureObject($procedureObject, $type);
        } else {
            if (!is_array($procedure)
                || (
                    is_array($procedure)
                    && 0 === count($procedure)
                )
            ) {
                throw new RuntimeException('Got empty procedure: '.var_export($procedure, true));
            }
        }
    }

    protected function getEndDateFromProcedureObject(Procedure $procedure, $type)
    {
        if (// should return public date
            ('public' === $type || $this->isPublicUser())
            // can return public date
            && $this->permissions->hasPermission('area_public_participation')) {
            $externalEndDate = $procedure->getPublicParticipationEndDate();
            if ($externalEndDate instanceof DateTime) {
                // hello database
                return $externalEndDate->getTimestamp();
            }

            if (is_numeric($externalEndDate)) {
                // hello elastic search, good bye objects
                // shedding tears over here
                return $externalEndDate;
            }

            if (is_string($externalEndDate)) {
                $carbon = Carbon::parse($externalEndDate);

                return $carbon->getTimestamp();
            }
        }

        $internalEndDate = $procedure->getEndDate();
        if ($internalEndDate instanceof DateTime) {
            // hello database
            return $internalEndDate->getTimestamp();
        }

        if (is_numeric($internalEndDate)) {
            // hello elastic search, good bye objects
            // shedding tears over here
            return $internalEndDate;
        }

        if (is_string($internalEndDate)) {
            // hello elastic search, good bye objects
            // shedding tears over here
            // fun times, now we have an iso date. at least something that looks like one.

            $carbon = Carbon::parse($internalEndDate);

            return $carbon->getTimestamp();
        }

        throw new InvalidArgumentException('Failed to determine requested end date value.');
    }

    /**
     * Returns the name of the given procedure.
     *
     * @param array|Procedure $procedure
     * @param string          $type      auto|public
     *
     * @return string
     */
    public function getNameFunction($procedure, $type = 'auto')
    {
        try {
            $procedure = $this->getProcedureObject($procedure);
        } catch (Exception $exception) {
            $this->logger->error('Could not get procedure object', [$exception]);

            return '';
        }

        if ($this->ownsProcedure($procedure) && $procedure->getName() !== $procedure->getExternalName()) {
            return $procedure->getName().' ('.$procedure->getExternalName().')';
        }

        $publicNameRequested = ('public' === $type || $this->isPublicUser());

        if ($publicNameRequested && $this->permissions->hasPermission('area_public_participation')) {
            return $procedure->getExternalName();
        }

        return $procedure->getName();
    }

    /**
     * Get Days left. End date should be counted as "1 day left".
     */
    public function getDaysLeft(array $procedureArray, string $type): string
    {
        try {
            $procedure = $this->getProcedureObject($procedureArray);
        } catch (Exception) {
            return '';
        }

        return $this->getDaysLeftFromProcedureObject($procedure, $type);
    }

    public function getDaysLeftFromProcedureObject(Procedure $procedure, string $type)
    {
        // Do not show 'daysleft' for planners as it is difficult to display
        // distinct daysleft for two phases
        if ($this->ownsProcedure($procedure)) {
            return '';
        }

        $externalPhases = $this->globalConfig->getExternalPhasesAssoc();
        $internalPhases = $this->globalConfig->getInternalPhasesAssoc();

        // Handle public users / externalPhases
        $externalPhaseIdentifier = $procedure->getPublicParticipationPhase();
        if ($this->isPublicUser() && array_key_exists($externalPhaseIdentifier, $externalPhases)) {
            $externalPhase = $externalPhases[$externalPhaseIdentifier];

            // Show a different 'daysleft' message if participationstate is finished.
            if (array_key_exists(Procedure::PARTICIPATIONSTATE_KEY, $externalPhase)
                && Procedure::PARTICIPATIONSTATE_FINISHED === $externalPhase[Procedure::PARTICIPATIONSTATE_KEY]) {
                return $this->translator->trans('days.left.participation.finished');
            }

            // Do not show 'daysleft' for not finished but readable procedures,
            // assuming they are in "preparation" phase.
            if ($this->isUnfinishedReadableProcedure($externalPhase, 'external')) {
                return '';
            }
        }

        // Handle logged in users / internalPhases
        $internalPhaseIdentifier = $procedure->getPhase();
        if (!$this->isPublicUser() && array_key_exists($internalPhaseIdentifier, $internalPhases)) {
            $internalPhase = $internalPhases[$internalPhaseIdentifier];

            // Show a different 'daysleft' message if participationstate is finished.
            if (array_key_exists(Procedure::PARTICIPATIONSTATE_KEY, $internalPhase)
                && Procedure::PARTICIPATIONSTATE_FINISHED === $internalPhase[Procedure::PARTICIPATIONSTATE_KEY]) {
                return $this->translator->trans('days.left.participation.finished');
            }

            // Do not show 'daysleft' for not finished but readable procedures,
            // assuming they are in "preparation" phase.
            if ($this->isUnfinishedReadableProcedure($internalPhase, 'internal')) {
                return '';
            }
        }

        // calculate Days left
        $endTimestamp = $this->getEndDateFromProcedureObject($procedure, $type);
        $daysLeft = $this->getDaysLeftDays($endTimestamp);

        return $this->getDaysLeftString($daysLeft);
    }

    /**
     * Get Days left. End date should be counted as "1 day left".
     */
    public function getDaysLeftDays(string $endTimestamp): int
    {
        return Carbon::yesterday()->diffInDays(Carbon::createFromTimestamp($endTimestamp), false);
    }

    protected function getDaysLeftString(int $daysLeft): string
    {
        if (0 >= $daysLeft) {
            return '';
        }

        return $this->translator->trans('days.left', ['count' => $daysLeft]);
    }

    /**
     * Get Procedure permissionset.
     *
     * @param string $scope external|internal
     *
     * @return string
     */
    public function getProcedurePermissionset($scope)
    {
        return $this->permissions->getPermissionset($scope);
    }

    /**
     * Is the user Guest or Citizen?
     */
    protected function isPublicUser(): bool
    {
        if (!$this->currentUser->getUser() instanceof User) {
            return true;
        }

        if ($this->currentUser->getUser()->isPublicUser()) {
            return true;
        }

        return false;
    }

    /**
     * Checks if procedure is not finished but readable.
     */
    protected function isUnfinishedReadableProcedure(array $phase, string $scope): bool
    {
        $noParticipationStateKeySet = !array_key_exists(Procedure::PARTICIPATIONSTATE_KEY, $phase);
        $participationStateKeySet = array_key_exists(Procedure::PARTICIPATIONSTATE_KEY, $phase);
        $participationStateNotFinished = $participationStateKeySet && Procedure::PARTICIPATIONSTATE_FINISHED !== $phase[Procedure::PARTICIPATIONSTATE_KEY];
        $isPermissionSetRead = Procedure::PROCEDURE_PHASE_PERMISSIONSET_READ === $this->permissions->getPermissionset($scope);

        return (($participationStateKeySet && $participationStateNotFinished) || $noParticipationStateKeySet) && $isPermissionSetRead;
    }

    /**
     * Is the user a responsible planner for this procedure?
     * Needs to be public as it is called from twig.
     *
     * @param Procedure|array $procedureSomething
     */
    public function ownsProcedure($procedureSomething): bool
    {
        try {
            $procedure = $this->getProcedureObject($procedureSomething);
        } catch (Exception) {
            return false;
        }
        if (!$this->currentUser->getUser() instanceof User) {
            return false;
        }

        $this->permissions->setProcedure($procedure);
        if ($this->permissions->ownsProcedure()) {
            return true;
        }

        return false;
    }

    /**
     * @param Permissions $permissions
     */
    public function setPermissions($permissions)
    {
        $this->permissions = $permissions;
    }

    /**
     * @return GlobalConfigInterface
     */
    public function getGlobalConfig()
    {
        return $this->globalConfig;
    }

    /**
     * @param GlobalConfigInterface $globalConfig
     */
    public function setGlobalConfig($globalConfig)
    {
        $this->globalConfig = $globalConfig;
    }

    /**
     * Get {@link Procedure} object from array if needed.
     *
     * @param string|array|Procedure $procedure
     *
     * @throws Exception
     */
    protected function getProcedureObject($procedure): Procedure
    {
        if ($procedure instanceof Procedure) {
            return $procedure;
        }

        if (is_array($procedure) && array_key_exists('id', $procedure)) {
            $procedureObject = $this->procedureService->getProcedure($procedure['id']);
            if ($procedureObject instanceof Procedure) {
                return $procedureObject;
            }
        }

        if (is_string($procedure)) {
            $procedureObject = $this->procedureService->getProcedure($procedure);
            if ($procedureObject instanceof Procedure) {
                return $procedureObject;
            }
        }

        throw new InvalidArgumentException('Could not find Procedure');
    }
}
