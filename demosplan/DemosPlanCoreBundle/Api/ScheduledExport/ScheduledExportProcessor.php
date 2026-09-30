<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\ScheduledExport;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ExportSchedule;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventListener\DemosPlanResponseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Exception\DeletionFailedException;
use demosplan\DemosPlanCoreBundle\Exception\PersistResourceException;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobFingerprint;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportScheduleRunCalculator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\ScheduledExportRepository;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

class ScheduledExportProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ScheduledExportAccessChecker $accessChecker,
        private readonly ScheduledExportRepository $scheduledExportRepository,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserInterface $currentUser,
        private readonly ExportScheduleRunCalculator $runCalculator,
        private readonly MessageBagInterface $messageBag,
    ) {
    }

    /**
     * @throws PersistResourceException
     * @throws DeletionFailedException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ScheduledExportResource|Response|null
    {
        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        // DELETE carries no body, so $data is null: handle it before asserting on the payload.
        if ($operation instanceof Delete) {
            $this->delete((string) ($uriVariables['id'] ?? ''));

            return $this->createDeletionResponse();
        }

        Assert::isInstanceOf($data, ScheduledExportResource::class);

        if ($operation instanceof Patch) {
            return $this->update((string) $uriVariables['id'], $data);
        }

        if ($operation instanceof Post) {
            return $this->create($data);
        }

        throw new LogicException(sprintf('%s is wired as the processor for unsupported operation "%s"; only Post, Patch, and Delete are handled.', self::class, $operation::class));
    }

    /**
     * @throws PersistResourceException
     */
    private function create(ScheduledExportResource $data): ScheduledExportResource
    {
        // Presence is enforced by the scheduledExport:create validation group; narrow for static analysis.
        Assert::stringNotEmpty($data->frequency);
        Assert::stringNotEmpty($data->parameters);
        $this->assertFrequencyFieldsConsistent($data->frequency, $data->weekday, $data->dayOfMonth);

        $procedure = $this->getCurrentProcedure();

        /** @var User $user */
        $user = $this->currentUser->getUser();

        $scheduledExport = new ExportSchedule();
        $scheduledExport->setUserId($user->getId());
        $scheduledExport->setProcedureId($procedure->getId());
        $scheduledExport->setFrequency($data->frequency);
        $scheduledExport->setWeekday($data->weekday);
        $scheduledExport->setDayOfMonth($data->dayOfMonth);
        $scheduledExport->setParameters($data->parameters);
        $scheduledExport->setParametersHash(ExportJobFingerprint::forScheduledExport($data->parameters));
        $scheduledExport->setNextRunAt($this->runCalculator->firstRunAtOrAfter($scheduledExport, new DateTime()));

        if (!$this->scheduledExportRepository->addObject($scheduledExport)) {
            throw new PersistResourceException('Could not persist the scheduled export.');
        }

        return ScheduledExportResource::fromEntity($scheduledExport);
    }

    /**
     * Only frequency, weekday, and dayOfMonth affect when the schedule is next due, so nextRunAt is
     * only recomputed when one of those three was actually sent - touching only parameters (or
     * nothing at all) leaves the existing due date alone.
     *
     * @throws PersistResourceException
     */
    private function update(string $scheduledExportId, ScheduledExportResource $data): ScheduledExportResource
    {
        $scheduledExport = $this->findScheduledExport($scheduledExportId);
        $timingChanged = false;

        if (null !== $data->frequency) {
            Assert::stringNotEmpty($data->frequency);
            $scheduledExport->setFrequency($data->frequency);
            $timingChanged = true;
        }

        if (null !== $data->weekday) {
            $scheduledExport->setWeekday($data->weekday);
            $timingChanged = true;
        }

        if (null !== $data->dayOfMonth) {
            $scheduledExport->setDayOfMonth($data->dayOfMonth);
            $timingChanged = true;
        }

        $this->assertFrequencyFieldsConsistent(
            $scheduledExport->getFrequency(),
            $scheduledExport->getWeekday(),
            $scheduledExport->getDayOfMonth()
        );

        if (null !== $data->parameters) {
            Assert::stringNotEmpty($data->parameters);
            $scheduledExport->setParameters($data->parameters);
            $scheduledExport->setParametersHash(ExportJobFingerprint::forScheduledExport($data->parameters));
        }

        if ($timingChanged) {
            $scheduledExport->setNextRunAt($this->runCalculator->firstRunAtOrAfter($scheduledExport, new DateTime()));
        }

        $scheduledExport->setModifiedDate(new DateTime());

        if (!$this->scheduledExportRepository->updateObject($scheduledExport)) {
            throw new PersistResourceException('Could not update the scheduled export.');
        }

        return ScheduledExportResource::fromEntity($scheduledExport);
    }

    /**
     * The repository catches its own failures and answers with false, so the result has to be checked:
     * without it a failed deletion would still be confirmed and the frontend would drop an entry that is
     * still stored.
     *
     * @throws DeletionFailedException rendered as a 500, since a deletion that fails is not the
     *                                 client's mistake. The cause is already in the repository's log
     */
    private function delete(string $scheduledExportId): void
    {
        if (!$this->scheduledExportRepository->deleteObject($this->findScheduledExport($scheduledExportId))) {
            throw new DeletionFailedException();
        }

        $this->messageBag->add('confirm', 'confirm.scheduledExport.deleted');
    }

    /**
     * Answered as an empty JSON document rather than by letting API Platform build the response from
     * the `output: false` declaration: that yields a plain Response, and
     * {@see DemosPlanResponseEventSubscriber} merges the
     * message bag into JsonResponses only - the confirmation would be withheld here and then shown on
     * whatever request comes next. Handing it a JsonResponse also makes it lift the 204 to a 200, which
     * is the same answer the rest of the JSON:API deletions give.
     *
     * The explicit `data: null` is required because the frontend's vuex-json-api parses every 200 and
     * rejects a document carrying neither `data` nor `errors` - a meta-only body would turn the
     * successful deletion into a client-side ApiError.
     */
    private function createDeletionResponse(): JsonResponse
    {
        return new JsonResponse(['data' => null], Response::HTTP_NO_CONTENT);
    }

    /**
     * Looked up through the access conditions, so another user's schedule, or one in another
     * procedure, is indistinguishable from a nonexistent one.
     */
    private function findScheduledExport(string $scheduledExportId): ExportSchedule
    {
        try {
            return $this->scheduledExportRepository->getEntityByIdentifier(
                $scheduledExportId,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            $this->messageBag->add('error', 'error.scheduledExport.not.found');

            throw new NotFoundHttpException(sprintf('Scheduled export "%s" not found.', $scheduledExportId));
        }
    }

    /**
     * weekday/dayOfMonth are always optional at the DTO level (see ScheduledExportResource), since
     * whether either is required depends on frequency rather than on which operation is running - so
     * that cross-field rule is enforced here instead of via a validation group.
     */
    private function assertFrequencyFieldsConsistent(string $frequency, ?int $weekday, ?int $dayOfMonth): void
    {
        if (ExportSchedule::FREQUENCY_WEEKLY === $frequency && null === $weekday) {
            $this->messageBag->add('error', 'error.scheduledExport.weekday.required');

            throw new BadRequestHttpException('A weekday is required for a weekly export schedule.');
        }

        if (ExportSchedule::FREQUENCY_MONTHLY === $frequency && null === $dayOfMonth) {
            $this->messageBag->add('error', 'error.scheduledExport.dayOfMonth.required');

            throw new BadRequestHttpException('A day of month is required for a monthly export schedule.');
        }
    }

    private function getCurrentProcedure(): Procedure
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            $this->messageBag->add('error', 'error.scheduledExport.procedure.missing');

            throw new BadRequestHttpException('A procedure context is required for scheduled export operations.');
        }

        return $procedure;
    }
}
