<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

use DateTime;
use DateTimeInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxDelivery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\OcxProblemException;
use demosplan\DemosPlanCoreBundle\Message\OcxProcessDeliveryMessage;
use demosplan\DemosPlanCoreBundle\Repository\OcxDeliveryRepository;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryFailure;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryStatus;
use demosplan\DemosPlanCoreBundle\Types\OcxEntryKind;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Receiving side of an OCX delivery: open, fill, complete, abort. Processing the entries is
 * done asynchronously by {@see \demosplan\DemosPlanCoreBundle\MessageHandler\OcxProcessDeliveryMessageHandler}.
 */
class OcxDeliveryService
{
    private const FAILURE_DETAILS = [
        OcxDeliveryFailure::NO_LONGER_ACCEPTED->value => 'The procedure no longer accepts deliveries from this sender.',
        OcxDeliveryFailure::STALLED->value            => 'Processing stopped and was given up.',
        OcxDeliveryFailure::INTERNAL_ERROR->value     => 'The delivery could not be processed.',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
        private readonly OcxDeliveryFiles $files,
        private readonly OcxDeliveryRepository $deliveryRepository,
        private readonly OcxPayloadValidator $payloadValidator,
        private readonly PermissionsInterface $permissions,
    ) {
    }

    /**
     * @return array{OcxDelivery, bool} the delivery and whether it was created by this call
     *
     * @throws OcxProblemException
     * @throws JsonException
     */
    public function open(string $procedureId, User $user, string $customerId, string $idempotencyKey, string $body): array
    {
        $procedure = $this->findAccessibleProcedure($procedureId, $user, $customerId);

        if ('' === $idempotencyKey || mb_strlen($idempotencyKey) > 255) {
            throw OcxProblemException::unprocessable('Header Idempotency-Key is required and must not exceed 255 characters.');
        }
        $existing = $this->deliveryRepository->findOneByIdempotencyKey($user, $idempotencyKey);
        if ($existing instanceof OcxDelivery) {
            if ($existing->getProcedure()->getId() !== $procedure->getId()) {
                throw OcxProblemException::conflict('Idempotency-Key was already used for another procedure.');
            }
            if (!$this->announcesTheSame($existing->getManifest(), $body)) {
                throw OcxProblemException::conflict('Idempotency-Key was already used for a delivery with other counts, attachments or modules.');
            }

            return [$existing, false];
        }

        $manifest = $this->payloadValidator->parseManifest($body);

        $delivery = new OcxDelivery(
            $procedure,
            $user,
            $idempotencyKey,
            json_decode($body, true, 512, JSON_THROW_ON_ERROR),
            $this->getPermittedKinds()
        );
        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

        $this->logger->info('OCX delivery opened', [
            'deliveryId'  => $delivery->getId(),
            'procedureId' => $procedure->getId(),
            'entries'     => $manifest->counts->entries,
            'parts'       => $manifest->counts->parts,
            'attachments' => $manifest->counts->attachments,
        ]);

        return [$delivery, true];
    }

    /**
     * @throws OcxProblemException
     */
    public function findAccessibleDelivery(string $deliveryId, User $user, string $customerId): OcxDelivery
    {
        $delivery = $this->deliveryRepository->find($deliveryId);
        $orgaId = $user->getOrga()?->getId();
        if (null === $orgaId
            || !$delivery instanceof OcxDelivery
            || $delivery->getOrganisation()?->getId() !== $orgaId
            || $delivery->getProcedure()->getCustomer()?->getId() !== $customerId
        ) {
            throw OcxProblemException::notFound();
        }

        return $delivery;
    }

    /**
     * Repeats the access check of {@see open()} for the worker, since the procedure or the sender
     * may have changed between opening the delivery and processing it.
     */
    public function isProcessable(OcxDelivery $delivery): bool
    {
        $user = $delivery->getUser();

        return !$user->isDeleted()
            && null !== $delivery->getOrganisation()
            && $user->getOrga()?->getId() === $delivery->getOrganisation()->getId()
            && $this->isReceivingProcedure($delivery->getProcedure(), $delivery->getOrganisation()->getId());
    }

    /**
     * The body is spooled to a temporary file, so the size of an attachment does not count against
     * the memory of the request.
     *
     * @param resource $body
     *
     * @return bool whether the content was stored by this call (false: identical content was already there)
     *
     * @throws OcxProblemException
     * @throws Throwable
     */
    public function storeAttachment(OcxDelivery $delivery, string $attachmentId, ?string $contentDigest, $body, ?int $contentLength): bool
    {
        $this->assertOpen($delivery);
        $announced = $this->findAnnouncedAttachment($delivery, $attachmentId);
        $mismatch = OcxProblemException::unprocessable('Attachment '.$attachmentId.' does not match size or sha256 announced in the manifest.');
        if (null !== $contentLength && $contentLength > $announced['size']) {
            throw $mismatch;
        }

        [$path, $sha256, $size] = $this->files->spool($body, $announced['size']);
        try {
            if ($size > $announced['size']) {
                throw $mismatch;
            }
            $this->payloadValidator->assertContentDigest($contentDigest, $sha256);

            $this->assertAttachmentNotReceivedDifferently($delivery, $attachmentId, $sha256);
            if (isset($delivery->getReceivedAttachments()[$attachmentId])) {
                return false;
            }
            if ($announced['sha256'] !== $sha256 || $announced['size'] !== $size) {
                throw $mismatch;
            }

            $fileId = $this->files->storeAttachment($delivery, $announced['filename'], $path);
        } finally {
            (new Filesystem())->remove($path);
        }

        // stored outside the lock, so a large file does not hold up the other uploads of the delivery
        try {
            $stored = $this->withLockedDelivery($delivery, function () use ($delivery, $attachmentId, $sha256, $fileId): bool {
                $this->assertOpen($delivery);
                $this->assertAttachmentNotReceivedDifferently($delivery, $attachmentId, $sha256);
                if (isset($delivery->getReceivedAttachments()[$attachmentId])) {
                    return false;
                }
                $delivery->addReceivedAttachment($attachmentId, $sha256, $fileId);

                return true;
            });
        } catch (Throwable $e) {
            $this->files->deleteAttachments((string) $delivery->getId(), [$fileId]);
            throw $e;
        }
        if (!$stored) {
            // a concurrent request stored the same attachment first
            $this->files->deleteAttachments((string) $delivery->getId(), [$fileId]);
        }

        return $stored;
    }

    /**
     * @return bool whether the part was stored by this call (false: identical content was already there)
     *
     * @throws OcxProblemException
     * @throws Throwable
     */
    public function storeEntriesPart(OcxDelivery $delivery, int $partIndex, ?string $contentDigest, string $body): bool
    {
        $this->assertOpen($delivery);
        $totalParts = (int) $delivery->getManifest()['counts']['parts'];
        if ($partIndex < 1 || $partIndex > $totalParts) {
            throw OcxProblemException::unprocessable('Part '.$partIndex.' is outside 1..'.$totalParts.' announced in the manifest.');
        }
        $this->payloadValidator->assertBodySize($body);
        $sha256 = hash('sha256', $body);
        $this->payloadValidator->assertContentDigest($contentDigest, $sha256);

        $this->assertPartNotReceivedDifferently($delivery, $partIndex, $sha256);
        if (isset($delivery->getReceivedParts()[$partIndex])) {
            return false;
        }

        $entries = $this->payloadValidator->parseEntriesPart($body, $partIndex, $totalParts);

        // written inside the lock: all uploads of a part share one storage path
        return $this->withLockedDelivery($delivery, function () use ($delivery, $partIndex, $sha256, $body, $entries): bool {
            $this->assertOpen($delivery);
            $this->assertPartNotReceivedDifferently($delivery, $partIndex, $sha256);
            if (isset($delivery->getReceivedParts()[$partIndex])) {
                return false;
            }
            $delivery->addReceivedPart($partIndex, $sha256, $this->files->storePart($delivery, $partIndex, $body), $entries);

            return true;
        });
    }

    /**
     * @return bool whether the delivery was queued by this call (false: it already was)
     *
     * @throws OcxProblemException
     * @throws Throwable
     */
    public function complete(OcxDelivery $delivery): bool
    {
        $receivedEntries = $this->withLockedDelivery($delivery, fn (): ?int => $this->queue($delivery));
        if (null === $receivedEntries) {
            return false;
        }
        // after the commit, so the worker finds the delivery queued
        $this->messageBus->dispatch(new OcxProcessDeliveryMessage((string) $delivery->getId()));

        $this->logger->info('OCX delivery queued', ['deliveryId' => $delivery->getId(), 'entries' => $receivedEntries]);

        return true;
    }

    /**
     * @return int|null the number of entries queued, null if the delivery was queued before
     *
     * @throws OcxProblemException
     */
    private function queue(OcxDelivery $delivery): ?int
    {
        if (OcxDeliveryStatus::ABORTED === $delivery->getStatus()) {
            throw OcxProblemException::conflict('The delivery was aborted.');
        }
        if (OcxDeliveryStatus::OPEN !== $delivery->getStatus()) {
            return null;
        }

        $missingParts = $this->getMissingParts($delivery);
        $missingAttachments = $this->getMissingAttachments($delivery);
        if ([] !== $missingParts || [] !== $missingAttachments) {
            throw OcxProblemException::unprocessable('The delivery is incomplete.', ['missingParts' => $missingParts, 'missingAttachments' => $missingAttachments]);
        }
        $announcedEntries = (int) $delivery->getManifest()['counts']['entries'];
        $receivedEntries = array_sum(array_column($delivery->getReceivedParts(), 'entries'));
        if ($announcedEntries !== $receivedEntries) {
            throw OcxProblemException::unprocessable('The parts contain '.$receivedEntries.' entries, the manifest announces '.$announcedEntries.'.');
        }

        $delivery->setStatus(OcxDeliveryStatus::QUEUED);
        $delivery->setCompletedAt(new DateTime());
        $delivery->setProgress(0, new DateTime());

        return $receivedEntries;
    }

    /**
     * @throws OcxProblemException
     * @throws Throwable
     */
    public function abort(OcxDelivery $delivery): void
    {
        $this->withLockedDelivery($delivery, function () use ($delivery): void {
            if (OcxDeliveryStatus::ABORTED === $delivery->getStatus()) {
                return;
            }
            $this->assertOpen($delivery);

            $this->files->discardReceived($delivery);
            $delivery->setStatus(OcxDeliveryStatus::ABORTED);
        });

        $this->logger->info('OCX delivery aborted', ['deliveryId' => $delivery->getId()]);
    }

    /**
     * Aborts deliveries that stayed open for too long and deletes what was uploaded.
     */
    public function abortStale(DateTime $openedBefore): int
    {
        $deliveries = $this->deliveryRepository->findOpenCreatedBefore($openedBefore);
        foreach ($deliveries as $delivery) {
            $this->files->discardReceived($delivery);
            $delivery->setStatus(OcxDeliveryStatus::ABORTED);
        }
        $this->entityManager->flush();

        return count($deliveries);
    }

    /**
     * Fails queued or processing deliveries no worker finishes any more: the message was lost or
     * the worker stopped. A delivery that still progresses is left alone.
     */
    public function failStalled(DateTime $completedBefore, DateTime $progressBefore): int
    {
        $deliveries = $this->deliveryRepository->findUnfinishedCompletedBefore($completedBefore, $progressBefore);
        foreach ($deliveries as $delivery) {
            $this->logger->warning('OCX delivery failed because its processing stalled', [
                'deliveryId'       => $delivery->getId(),
                'status'           => $delivery->getStatus()->value,
                'processedEntries' => $delivery->getProcessedEntries(),
            ]);
            $this->discardLeftovers($delivery);
            $delivery->fail(OcxDeliveryFailure::STALLED);
            $delivery->setProcessedAt(new DateTime());
        }
        $this->entityManager->flush();

        return count($deliveries);
    }

    /**
     * Deletes the parts and every attachment no statement took over, after the delivery failed as
     * a whole. Statements created before the failure keep their files.
     */
    public function discardLeftovers(OcxDelivery $delivery): void
    {
        $this->files->discardReceived($delivery, true);
    }

    /**
     * The delivery as described by `delivery.schema.json`.
     *
     * @return array<string, mixed>
     */
    public function toStatus(OcxDelivery $delivery): array
    {
        $isOpen = OcxDeliveryStatus::OPEN === $delivery->getStatus();

        return [
            'deliveryId'         => $delivery->getId(),
            'procedureId'        => $delivery->getProcedure()->getId(),
            'status'             => $delivery->getStatus()->value,
            'failure'            => $this->getFailure($delivery),
            'missingParts'       => $isOpen ? $this->getMissingParts($delivery) : [],
            'missingAttachments' => $isOpen ? $this->getMissingAttachments($delivery) : [],
            'progress'           => $this->getProgress($delivery),
            'counts'             => $delivery->getCounts(),
            'results'            => $delivery->getResults(),
        ];
    }

    /**
     * @return array{code: string, detail: string}|null
     */
    private function getFailure(OcxDelivery $delivery): ?array
    {
        $failure = $delivery->getFailure();

        return null === $failure ? null : ['code' => $failure->value, 'detail' => self::FAILURE_DETAILS[$failure->value]];
    }

    /**
     * Whether a manifest sent again under an Idempotency-Key describes the delivery opened with it.
     * Other members differ legitimately, e.g. `createdAt` when a sender resumes.
     *
     * @param array<string, mixed> $known
     */
    private function announcesTheSame(array $known, string $body): bool
    {
        $manifest = json_decode($body, true);
        if (!is_array($manifest)) {
            return false;
        }
        foreach (['counts', 'attachments', 'modules'] as $member) {
            if (($known[$member] ?? null) != ($manifest[$member] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{processedEntries: int, totalEntries: int, updatedAt: string}|null
     */
    private function getProgress(OcxDelivery $delivery): ?array
    {
        $updatedAt = $delivery->getProgressUpdatedAt();
        if (null === $updatedAt || OcxDeliveryStatus::ABORTED === $delivery->getStatus()) {
            return null;
        }

        return [
            'processedEntries' => $delivery->getProcessedEntries(),
            'totalEntries'     => (int) $delivery->getManifest()['counts']['entries'],
            'updatedAt'        => $updatedAt->format(DateTimeInterface::RFC3339),
        ];
    }

    /**
     * @return list<string>
     */
    private function getPermittedKinds(): array
    {
        return array_values(array_map(
            static fn (OcxEntryKind $kind): string => $kind->value,
            array_filter(OcxEntryKind::cases(), fn (OcxEntryKind $kind): bool => $this->permissions->hasPermission($kind->getPermission()))
        ));
    }

    /**
     * Only procedures owned by the organisation of the API user are visible; everything else is
     * indistinguishable from an unknown procedure.
     *
     * @throws OcxProblemException
     */
    private function findAccessibleProcedure(string $procedureId, User $user, string $customerId): Procedure
    {
        $procedure = $this->entityManager->find(Procedure::class, $procedureId);
        $orgaId = $user->getOrga()?->getId();
        if (null === $orgaId
            || !$procedure instanceof Procedure
            || $procedure->getCustomer()?->getId() !== $customerId
            || !$this->isReceivingProcedure($procedure, $orgaId)
        ) {
            throw OcxProblemException::notFound();
        }

        return $procedure;
    }

    /**
     * A procedure receives deliveries from its owning organisation only, and templates receive none.
     */
    private function isReceivingProcedure(Procedure $procedure, string $orgaId): bool
    {
        return !$procedure->isDeleted()
            && !$procedure->getMaster()
            && $procedure->getOrgaId() === $orgaId;
    }

    /**
     * @throws OcxProblemException
     */
    private function assertOpen(OcxDelivery $delivery): void
    {
        if (OcxDeliveryStatus::OPEN !== $delivery->getStatus()) {
            throw OcxProblemException::conflict('The delivery is '.$delivery->getStatus()->value.'.');
        }
    }

    /**
     * @throws OcxProblemException
     */
    private function assertAttachmentNotReceivedDifferently(OcxDelivery $delivery, string $attachmentId, string $sha256): void
    {
        $received = $delivery->getReceivedAttachments()[$attachmentId] ?? null;
        if (null !== $received && $received['sha256'] !== $sha256) {
            throw OcxProblemException::conflict('Attachment '.$attachmentId.' was already uploaded with different content.');
        }
    }

    /**
     * @throws OcxProblemException
     */
    private function assertPartNotReceivedDifferently(OcxDelivery $delivery, int $partIndex, string $sha256): void
    {
        $received = $delivery->getReceivedParts()[$partIndex] ?? null;
        if (null !== $received && $received['sha256'] !== $sha256) {
            throw OcxProblemException::conflict('Part '.$partIndex.' was already uploaded with different content.');
        }
    }

    /**
     * Runs $change on the current state of the delivery while holding its row lock. Requests of one
     * delivery may run in parallel, and each of them rewrites the lists of received parts and
     * attachments as a whole.
     *
     * @template T
     *
     * @param callable(): T $change
     *
     * @return T
     *
     * @throws OcxProblemException
     * @throws Throwable
     */
    private function withLockedDelivery(OcxDelivery $delivery, callable $change): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->entityManager->refresh($delivery, LockMode::PESSIMISTIC_WRITE);
            $result = $change();
            $this->entityManager->flush();
            $connection->commit();
        } catch (Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    /**
     * @return array{id: string, filename: string, mediaType: string, size: int, sha256: string}
     *
     * @throws OcxProblemException
     */
    private function findAnnouncedAttachment(OcxDelivery $delivery, string $attachmentId): array
    {
        foreach ($delivery->getManifest()['attachments'] as $attachment) {
            if ($attachment['id'] === $attachmentId) {
                return $attachment;
            }
        }

        throw OcxProblemException::unprocessable('Attachment '.$attachmentId.' is not announced in the manifest.');
    }

    /**
     * @return list<int>
     */
    private function getMissingParts(OcxDelivery $delivery): array
    {
        $totalParts = (int) $delivery->getManifest()['counts']['parts'];

        return array_values(array_diff(range(1, $totalParts), array_keys($delivery->getReceivedParts())));
    }

    /**
     * @return list<string>
     */
    private function getMissingAttachments(OcxDelivery $delivery): array
    {
        $announced = array_column($delivery->getManifest()['attachments'], 'id');

        return array_values(array_diff($announced, array_keys($delivery->getReceivedAttachments())));
    }
}
