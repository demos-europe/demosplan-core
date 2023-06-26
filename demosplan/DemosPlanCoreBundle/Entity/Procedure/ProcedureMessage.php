<?php

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;



use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * ProcedureMessage - Defines a specific message of Procedure
 * @ORM\Entity()
 */
class ProcedureMessage
{

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, nullable=false, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * Every message belongs to an (actual, non-template) procedure. But every procedure can have many relation or doesn't have any
     * with {@link ProcedureMessage}.
     * It fully depends on permissions and availability of the external ProcedureMessage service.
     *
     * @var Procedure
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     * @ORM\JoinColumn(nullable = false, referencedColumnName="_p_id", unique=true)
     */
    private $procedure;

    /**
     * @var string
     *
     * @ORM\Column(name="message", type="text", nullable=false)
     */
    private $message = '';

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false, options={"default":"CURRENT_TIMESTAMP"})
     */
    private $createdDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false, options={"default":"CURRENT_TIMESTAMP"})
     */
    private $modificationDate;

    /**
     * @var bool
     *
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":"false"})
     */
    private $error;

    /**
     * @var bool
     *
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":"false"})
     */
    private bool $deleted;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"default":"1"})
     */
    private int $requestCount;

    public function __construct(
        string $message,
        bool $deleted,
        bool $error,
        bool $requestCount,
        ProcedureInterface $procedure
    ) {
        $this->message = $message;
        $this->deleted = $deleted;
        $this->error = $error;
        $this->requestCount = $requestCount;
        $this->procedure = $procedure;
    }

    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return bool
     */
    public function getError(): bool
    {
        return $this->error;
    }

    /**
     * @param bool $error
     * @return void
     */
    public function setError(bool $error): void
    {
        $this->error = $error;
    }

    /**
     * @return bool
     */
    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @return bool
     */
    public function setDeleted(): bool
    {
        return $this->deleted = true;
    }

    /**
     * @return int
     */
    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    /**
     * @return int
     */
    public function setRequestCount(): int
    {
        return $this->requestCount ++;
    }

    public function getProcedure(): ProcedureInterface
    {
        return $this->procedure;
    }
}
