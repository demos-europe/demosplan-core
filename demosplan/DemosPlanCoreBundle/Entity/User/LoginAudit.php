<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Audit record of an authentication attempt (success or failure), including 2FA outcomes.
 * Rows are written by LoginAuditSubscriber via LoginAuditWriter.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\LoginAuditRepository")
 *
 * @ORM\Table(
 *     name="login_audit",
 *     indexes={
 *         @ORM\Index(name="IDX_LOGIN_AUDIT_USER_DATE", columns={"user_id","created_date"}),
 *         @ORM\Index(name="IDX_LOGIN_AUDIT_DATE", columns={"created_date"}),
 *         @ORM\Index(name="IDX_LOGIN_AUDIT_SESSION_AUTH", columns={"session_id_hash","authenticator"})
 *     }
 * )
 */
class LoginAudit implements EntityInterface
{
    public const RESULT_SUCCESS = 'success';
    public const RESULT_FAILURE = 'failure';

    /**
     * @ORM\Id
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private string $id;

    /**
     * Stored as a plain UUID string instead of a ManyToOne association so that
     * audit rows survive user deletion
     *
     * @ORM\Column(name="user_id", type="string", length=36, nullable=true, options={"fixed":true})
     */
    private ?string $userId = null;

    /**
     * @ORM\Column(type="string", length=16)
     */
    private string $result;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $failureReason = null;

    /**
     * Stored as the authenticator's fully-qualified class name (FQN) to avoid
     * collisions between core and addon authenticators that share a short name.
     *
     * @ORM\Column(type="string", length=191)
     */
    private string $authenticator;

    /**
     * @ORM\Column(type="string", length=512, nullable=true)
     */
    private ?string $userAgent = null;

    /**
     * @ORM\Column(type="string", length=64, nullable=true, options={"fixed":true})
     */
    private ?string $sessionIdHash = null;

    /**
     * @ORM\Column(type="datetime")
     *
     * @Gedmo\Timestampable(on="create")
     */
    private DateTime $createdDate;

    public function __construct(string $result, string $authenticator)
    {
        $this->result = $result;
        $this->authenticator = $authenticator;
        // Belt-and-braces default. Gedmo's Timestampable listener overwrites this on
        // persist; the explicit init guards against code paths that read the property
        // (or persist) before the listener has fired.
        $this->createdDate = new DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): void
    {
        $this->failureReason = $failureReason;
    }

    public function getAuthenticator(): string
    {
        return $this->authenticator;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getSessionIdHash(): ?string
    {
        return $this->sessionIdHash;
    }

    public function setSessionIdHash(?string $sessionIdHash): void
    {
        $this->sessionIdHash = $sessionIdHash;
    }

    public function getCreatedDate(): DateTime
    {
        return $this->createdDate;
    }
}
