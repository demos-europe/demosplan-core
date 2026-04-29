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
 * Captures log statements that contain personal data: the full record (incl. PII)
 * is stored here; only a UUID + content hash + non-PII context is emitted to the
 * filesystem log via {@see \demosplan\DemosPlanCoreBundle\Logger\PiiAwareLogger}.
 *
 * Sister mechanism to {@see PersonalDataAuditLog}, which captures *changes to*
 * personal-data entities; this captures *log lines about* personal data.
 *
 * @ORM\Table(
 *     name="pii_log",
 *     indexes={
 *
 *         @ORM\Index(name="idx_pii_created", columns={"created"}),
 *         @ORM\Index(name="idx_pii_hash", columns={"content_hash"}),
 *         @ORM\Index(name="idx_pii_procedure", columns={"procedure_id"}),
 *         @ORM\Index(name="idx_pii_orga", columns={"orga_id"}),
 *         @ORM\Index(name="idx_pii_request", columns={"request_id"})
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\PersonalDataLogEntryRepository")
 */
class PersonalDataLogEntry extends CoreEntity implements UuidEntityInterface
{
    final public const CONTEXT_WEB = 'web';
    final public const CONTEXT_CLI = 'cli';

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
     * PSR-3 / Monolog level integer.
     *
     * @ORM\Column(type="smallint", nullable=false)
     */
    protected int $level;

    /**
     * Lower-case PSR-3 level name (e.g. 'info', 'warning'); duplicated for query convenience.
     *
     * @ORM\Column(type="string", length=16, nullable=false)
     */
    protected string $levelName;

    /**
     * @ORM\Column(type="string", length=64, nullable=false)
     */
    protected string $channel;

    /**
     * Log message with PSR-3 placeholders interpolated.
     *
     * @ORM\Column(type="text", nullable=false)
     */
    protected string $message;

    /**
     * Confidential payload (anything the caller passed under context['pii']),
     * JSON-encoded. NEVER written to the file log.
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected ?string $piiContext = null;

    /**
     * Non-PII context (everything else from the call's $context array, minus
     * the keys consumed by the logger), JSON-encoded. Mirrors what the file log
     * line carries — useful when reconstructing what the user/operator saw.
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected ?string $nonPiiContext = null;

    /**
     * SHA-256 hex over canonical JSON of (channel + level + message + piiContext).
     * Indexed but not unique — identical messages legitimately recur.
     *
     * @ORM\Column(type="string", length=64, nullable=false, options={"fixed":true})
     */
    protected string $contentHash;

    /**
     * Correlation id from {@see \demosplan\DemosPlanCoreBundle\Monolog\Processor\RequestIdProcessor}.
     *
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    protected ?string $requestId = null;

    /**
     * @ORM\Column(type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected ?string $procedureId = null;

    /**
     * @ORM\Column(type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected ?string $orgaId = null;

    /**
     * Source context: web, cli.
     *
     * @ORM\Column(type="string", length=8, nullable=false)
     */
    protected string $sourceContext;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getLevelName(): string
    {
        return $this->levelName;
    }

    public function setLevelName(string $levelName): self
    {
        $this->levelName = $levelName;

        return $this;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getPiiContext(): ?string
    {
        return $this->piiContext;
    }

    public function setPiiContext(?string $piiContext): self
    {
        $this->piiContext = $piiContext;

        return $this;
    }

    public function getNonPiiContext(): ?string
    {
        return $this->nonPiiContext;
    }

    public function setNonPiiContext(?string $nonPiiContext): self
    {
        $this->nonPiiContext = $nonPiiContext;

        return $this;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function setContentHash(string $contentHash): self
    {
        $this->contentHash = $contentHash;

        return $this;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): self
    {
        $this->requestId = $requestId;

        return $this;
    }

    public function getProcedureId(): ?string
    {
        return $this->procedureId;
    }

    public function setProcedureId(?string $procedureId): self
    {
        $this->procedureId = $procedureId;

        return $this;
    }

    public function getOrgaId(): ?string
    {
        return $this->orgaId;
    }

    public function setOrgaId(?string $orgaId): self
    {
        $this->orgaId = $orgaId;

        return $this;
    }

    public function getSourceContext(): string
    {
        return $this->sourceContext;
    }

    public function setSourceContext(string $sourceContext): self
    {
        $this->sourceContext = $sourceContext;

        return $this;
    }
}
