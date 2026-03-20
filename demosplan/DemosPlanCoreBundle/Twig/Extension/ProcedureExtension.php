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
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedurePhaseDefinitionService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use Exception;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\TwigFilter;
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
        private readonly ProcedurePhaseDefinitionService $procedurePhaseDefinitionService,
        private readonly ProcedureService $procedureService,
        private readonly TranslatorInterface $translator)
    {
        parent::__construct($container);
        $this->globalConfig = $globalConfig;
        $this->permissions = $permissions;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('replacePhaseUuid', $this->replacePhaseUuid(...)),
        ];
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
            new TwigFunction('isPreparationPhase', $this->isPreparationPhase(...)),
        ];
    }

    /**
     * Get the phase name of a procedure.
     * Default is the internal phase name.
     *
     * @param string $type auto|public
     */
    public function getPhase(array|Procedure $procedure, string $type = 'auto'): string
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
        if ($publicNameRequested && $this->permissions->hasPermission('area_public_participation')) {
            return $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition()->getName();
        }

        // return internal phaseName
        return $procedure->getPhaseObject()->getPhaseDefinition()->getName();
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
            return $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition()->getId() ?? '';
        }

        return $procedure->getPhaseObject()->getPhaseDefinition()->getId() ?? '';
    }

    /**
     * Returns true if the given procedure's phase is a "preparation" phase:
     * permissionSet = 'read' and participationState = null (visible but not open for participation yet).
     *
     * @param string $type auto|public
     */
    public function isPreparationPhase(array|Procedure $procedure, string $type = 'auto'): bool
    {
        try {
            $procedure = $this->getProcedureObject($procedure);
        } catch (Exception) {
            return false;
        }

        $publicNameRequested = ('public' === $type || $this->isPublicUser());

        if ($publicNameRequested && $this->permissions->hasPermission('area_public_participation')) {
            $definition = $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition();
        } else {
            $definition = $procedure->getPhaseObject()->getPhaseDefinition();
        }

        return Procedure::PROCEDURE_PHASE_PERMISSIONSET_READ === $definition->getPermissionSet()
            && null === $definition->getParticipationState();
    }

    /**
     * Replaces a UUID segment in a contextual help key with the corresponding phase definition name and customer.
     * E.g. "help.public.detail.phase.{uuid}" → "help.public.detail.phase.Beteiligung in Vorbereitung (Kunde XY)".
     * If no phase definition is found for the UUID, the original key is returned unchanged.
     */
    public function replacePhaseUuid(string $key): string
    {
        return preg_replace_callback(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            function (array $matches): string {
                $definition = $this->procedurePhaseDefinitionService->findById($matches[0]);
                if (null === $definition) {
                    return $matches[0];
                }

                $customerName = $definition->getCustomer()?->getName() ?? '';

                return $definition->getName().($customerName !== '' ? ' ('.$customerName.')' : '');
            },
            $key
        ) ?? $key;
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
        if ([] === $procedure) {
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
        if (false === $procedure instanceof Procedure && (!is_array($procedure)
            || (
                is_array($procedure)
                && [] === $procedure
            ))) {
            throw new RuntimeException('Got empty procedure: '.var_export($procedure, true));
        }

        try {
            $procedureObject = $this->getProcedureObject($procedure);
        } catch (Exception $exception) {
            throw new RuntimeException('Got unretrievable procedure: '.var_export($procedure, true), 0, $exception);
        }

        return $this->getEndDateFromProcedureObject($procedureObject, $type);
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

        // Handle public users / external phase
        if ($this->isPublicUser()) {
            $externalPhaseDefinition = $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition();

            // Show a different 'daysleft' message if participationstate is finished.
            if (Procedure::PARTICIPATIONSTATE_FINISHED === $externalPhaseDefinition->getParticipationState()) {
                return $this->translator->trans('days.left.participation.finished');
            }

            // Do not show 'daysleft' for not finished but readable procedures,
            // assuming they are in "preparation" phase.
            if ($this->isUnfinishedReadableProcedure($externalPhaseDefinition, 'external')) {
                return '';
            }
        }

        // Handle logged in users / internal phase
        if (!$this->isPublicUser()) {
            $internalPhaseDefinition = $procedure->getPhaseObject()->getPhaseDefinition();

            // Show a different 'daysleft' message if participationstate is finished.
            if (Procedure::PARTICIPATIONSTATE_FINISHED === $internalPhaseDefinition->getParticipationState()) {
                return $this->translator->trans('days.left.participation.finished');
            }

            // Do not show 'daysleft' for not finished but readable procedures,
            // assuming they are in "preparation" phase.
            if ($this->isUnfinishedReadableProcedure($internalPhaseDefinition, 'internal')) {
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

        return $this->currentUser->getUser()->isPublicUser();
    }

    /**
     * Checks if procedure is not finished but readable.
     */
    protected function isUnfinishedReadableProcedure(ProcedurePhaseDefinition $phaseDefinition, string $scope): bool
    {
        $isPermissionSetRead = Procedure::PROCEDURE_PHASE_PERMISSIONSET_READ === $this->permissions->getPermissionset($scope);

        return Procedure::PARTICIPATIONSTATE_FINISHED !== $phaseDefinition->getParticipationState() && $isPermissionSetRead;
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

        return $this->permissions->ownsProcedure();
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
