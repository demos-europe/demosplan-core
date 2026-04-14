<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Audit log for changes to personal data entities (User, Orga, Address, Department).
 * Required for GDPR compliance (Art. 5(1)(f)).
 *
 * @ORM\Table(
 *     name="personal_data_audit_log",
 *     indexes={
 *
 *         @ORM\Index(name="idx_pda_entity", columns={"entity_type", "entity_id"}),
 *         @ORM\Index(name="idx_pda_user", columns={"user_id"}),
 *         @ORM\Index(name="idx_pda_created", columns={"created"})
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\PersonalDataAuditRepository")
 */
class PersonalDataAuditLog extends CoreEntity implements UuidEntityInterface
{
    final public const CHANGE_TYPE_CREATE = 'create';
    final public const CHANGE_TYPE_UPDATE = 'update';
    final public const CHANGE_TYPE_DELETE = 'delete';
    final public const CHANGE_TYPE_WIPE = 'wipe';

    final public const CONTEXT_WEB = 'web';
    final public const CONTEXT_CLI = 'cli';

    final public const SENSITIVE_MASK = '***';

    /**
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected ?string $id = null;

    /**
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected DateTime $created;

    /**
     * No relation to avoid difficulties on deleting user.
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected ?string $userId = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected ?string $userName = null;

    /**
     * FQCN of the changed entity.
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected string $entityType;

    /**
     * @ORM\Column(type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected string $entityId;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    protected string $entityField;

    /**
     * One of: create, update, delete, wipe.
     *
     * @ORM\Column(type="string", length=20, nullable=false)
     */
    protected string $changeType;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected ?string $preUpdateValue = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected ?string $postUpdateValue = null;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    protected bool $isSensitiveField = false;

    /**
     * Source context: web, cli.
     *
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    protected ?string $context = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): self
    {
        $this->userName = $userName;

        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getEntityField(): string
    {
        return $this->entityField;
    }

    public function setEntityField(string $entityField): self
    {
        $this->entityField = $entityField;

        return $this;
    }

    public function getChangeType(): string
    {
        return $this->changeType;
    }

    public function setChangeType(string $changeType): self
    {
        $this->changeType = $changeType;

        return $this;
    }

    public function getPreUpdateValue(): ?string
    {
        return $this->preUpdateValue;
    }

    public function setPreUpdateValue(?string $preUpdateValue): self
    {
        $this->preUpdateValue = $preUpdateValue;

        return $this;
    }

    public function getPostUpdateValue(): ?string
    {
        return $this->postUpdateValue;
    }

    public function setPostUpdateValue(?string $postUpdateValue): self
    {
        $this->postUpdateValue = $postUpdateValue;

        return $this;
    }

    public function isSensitiveField(): bool
    {
        return $this->isSensitiveField;
    }

    public function setIsSensitiveField(bool $isSensitiveField): self
    {
        $this->isSensitiveField = $isSensitiveField;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): self
    {
        $this->context = $context;

        return $this;
    }
}
