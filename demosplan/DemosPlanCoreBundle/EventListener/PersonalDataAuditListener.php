<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DateTime;
use DateTimeInterface;
use demosplan\DemosPlanCoreBundle\Entity\PersonalDataAuditLog;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Exception;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Doctrine event listener that automatically captures changes to personal data entities
 * (User, Orga, Address, StatementMeta, DraftStatement, ProcedurePerson)
 * for GDPR-compliant audit logging.
 *
 * Uses onFlush to collect pending changes and postFlush to write them via DBAL
 * to avoid recursion from persisting new entities during flush.
 */
class PersonalDataAuditListener
{
    /** @var array<int, array<string, mixed>> */
    private array $pendingAuditEntries = [];

    private bool $enabled = true;

    /**
     * @param array<string, array<string, array<string, mixed>>> $fieldMapping
     */
    public function __construct(
        private readonly array $fieldMapping,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->collectUpdateEntries($entity, $uow);
        }

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->collectInsertEntries($entity);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->collectDeleteEntries($entity);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pendingAuditEntries) {
            return;
        }

        $entries = $this->pendingAuditEntries;
        $this->pendingAuditEntries = [];

        $connection = $args->getObjectManager()->getConnection();

        try {
            $this->writePendingEntries($connection, $entries);
        } catch (Exception $e) {
            $this->logger->critical('GDPR audit trail write failure - entries lost', [
                'exception'  => $e,
                'entryCount' => count($entries),
            ]);
        }
    }

    private function collectUpdateEntries(object $entity, UnitOfWork $uow): void
    {
        $entityClass = ClassUtils::getClass($entity);
        if (!isset($this->fieldMapping[$entityClass])) {
            return;
        }

        $changeSet = $uow->getEntityChangeSet($entity);
        $trackedFields = $this->fieldMapping[$entityClass];
        $entityId = $this->getEntityId($entity);
        if (null === $entityId) {
            return;
        }

        $procedureId = $this->resolveProcedureId($entity);
        $orgaId = $this->resolveOrgaId($entity);

        foreach ($changeSet as $field => [$oldValue, $newValue]) {
            if (!array_key_exists($field, $trackedFields)) {
                continue;
            }

            // Skip changes between null and empty string -- no meaningful data change
            if ($this->isBlankChange($oldValue, $newValue)) {
                continue;
            }

            $isSensitive = $this->isSensitiveField($trackedFields[$field]);

            $this->pendingAuditEntries[] = [
                'entityType'       => $entityClass,
                'entityId'         => $entityId,
                'entityField'      => $field,
                'changeType'       => PersonalDataAuditLog::CHANGE_TYPE_UPDATE,
                'preUpdateValue'   => $isSensitive ? PersonalDataAuditLog::SENSITIVE_MASK : $this->serializeValue($oldValue),
                'postUpdateValue'  => $isSensitive ? PersonalDataAuditLog::SENSITIVE_MASK : $this->serializeValue($newValue),
                'isSensitiveField' => $isSensitive,
                'procedureId'      => $procedureId,
                'orgaId'           => $orgaId,
            ];
        }
    }

    private function collectInsertEntries(object $entity): void
    {
        $entityClass = ClassUtils::getClass($entity);
        if (!isset($this->fieldMapping[$entityClass])) {
            return;
        }

        $trackedFields = $this->fieldMapping[$entityClass];
        $entityId = $this->getEntityId($entity);
        if (null === $entityId) {
            return;
        }

        $procedureId = $this->resolveProcedureId($entity);
        $orgaId = $this->resolveOrgaId($entity);

        foreach ($trackedFields as $field => $config) {
            $value = $this->getFieldValue($entity, $field);
            if (null === $value) {
                continue;
            }

            $isSensitive = $this->isSensitiveField($config);

            $this->pendingAuditEntries[] = [
                'entityType'       => $entityClass,
                'entityId'         => $entityId,
                'entityField'      => $field,
                'changeType'       => PersonalDataAuditLog::CHANGE_TYPE_CREATE,
                'preUpdateValue'   => null,
                'postUpdateValue'  => $isSensitive ? PersonalDataAuditLog::SENSITIVE_MASK : $this->serializeValue($value),
                'isSensitiveField' => $isSensitive,
                'procedureId'      => $procedureId,
                'orgaId'           => $orgaId,
            ];
        }
    }

    private function collectDeleteEntries(object $entity): void
    {
        $entityClass = ClassUtils::getClass($entity);
        if (!isset($this->fieldMapping[$entityClass])) {
            return;
        }

        $trackedFields = $this->fieldMapping[$entityClass];
        $entityId = $this->getEntityId($entity);
        if (null === $entityId) {
            return;
        }

        $procedureId = $this->resolveProcedureId($entity);
        $orgaId = $this->resolveOrgaId($entity);

        foreach ($trackedFields as $field => $config) {
            $value = $this->getFieldValue($entity, $field);
            if (null === $value) {
                continue;
            }

            $isSensitive = $this->isSensitiveField($config);

            $this->pendingAuditEntries[] = [
                'entityType'       => $entityClass,
                'entityId'         => $entityId,
                'entityField'      => $field,
                'changeType'       => PersonalDataAuditLog::CHANGE_TYPE_DELETE,
                'preUpdateValue'   => $isSensitive ? PersonalDataAuditLog::SENSITIVE_MASK : $this->serializeValue($value),
                'postUpdateValue'  => null,
                'isSensitiveField' => $isSensitive,
                'procedureId'      => $procedureId,
                'orgaId'           => $orgaId,
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function writePendingEntries(Connection $connection, array $entries): void
    {
        [$userId, $userName] = $this->resolveCurrentUser();
        $context = $this->resolveContext();
        $now = new DateTime();

        $connection->beginTransaction();
        try {
            foreach ($entries as $entry) {
                $connection->insert('personal_data_audit_log', [
                    'id'                 => Uuid::uuid4()->toString(),
                    'user_id'            => $userId,
                    'user_name'          => $userName,
                    'entity_type'        => $entry['entityType'],
                    'entity_id'          => $entry['entityId'],
                    'entity_field'       => $entry['entityField'],
                    'change_type'        => $entry['changeType'],
                    'pre_update_value'   => $entry['preUpdateValue'],
                    'post_update_value'  => $entry['postUpdateValue'],
                    'is_sensitive_field' => $entry['isSensitiveField'] ? 1 : 0,
                    'procedure_id'       => $entry['procedureId'],
                    'orga_id'            => $entry['orgaId'],
                    'context'            => $context,
                    'created'            => $now->format('Y-m-d H:i:s'),
                ]);
            }
            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function resolveCurrentUser(): array
    {
        try {
            $token = $this->tokenStorage->getToken();
            if (null !== $token) {
                $user = $token->getUser();
                if (is_object($user) && method_exists($user, 'getId') && method_exists($user, 'getLogin')) {
                    return [$user->getId(), $user->getLogin()];
                }
            }
        } catch (Exception) {
            // Token storage may not be available in CLI context
        }

        return [null, 'SYSTEM'];
    }

    private function resolveContext(): string
    {
        if ('cli' === \PHP_SAPI) {
            return PersonalDataAuditLog::CONTEXT_CLI;
        }

        return PersonalDataAuditLog::CONTEXT_WEB;
    }

    private function resolveProcedureId(object $entity): ?string
    {
        try {
            if ($entity instanceof StatementMeta) {
                return $entity->getStatement()?->getProcedure()?->getId();
            }
            if ($entity instanceof DraftStatement) {
                return $entity->getProcedureId();
            }
            if ($entity instanceof ProcedurePerson) {
                return $entity->getProcedure()?->getId();
            }
        } catch (Exception) {
            // Lazy-loaded relations may not be available during flush
        }

        return null;
    }

    private function resolveOrgaId(object $entity): ?string
    {
        try {
            if ($entity instanceof User) {
                return $entity->getOrganisationId();
            }
            if ($entity instanceof Orga) {
                return $entity->getId();
            }
        } catch (Exception) {
            // Lazy-loaded relations may not be available during flush
        }

        return null;
    }

    private function isSensitiveField(mixed $config): bool
    {
        return is_array($config) && ($config['sensitive'] ?? false);
    }

    private function isBlankChange(mixed $oldValue, mixed $newValue): bool
    {
        $oldBlank = null === $oldValue || '' === $oldValue;
        $newBlank = null === $newValue || '' === $newValue;

        return $oldBlank && $newBlank;
    }

    private function getEntityId(object $entity): ?string
    {
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();

            return null !== $id ? (string) $id : null;
        }

        return null;
    }

    private function getFieldValue(object $entity, string $field): mixed
    {
        $getter = 'get'.ucfirst($field);
        if (method_exists($entity, $getter)) {
            return $entity->$getter();
        }

        $isser = 'is'.ucfirst($field);
        if (method_exists($entity, $isser)) {
            return $entity->$isser();
        }

        return null;
    }

    private function serializeValue(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value) && method_exists($value, 'getId')) {
            return (string) $value->getId();
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return null;
    }
}
