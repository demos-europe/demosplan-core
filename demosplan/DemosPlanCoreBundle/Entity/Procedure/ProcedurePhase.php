<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;


use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePhaseInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Stores the information about the current phase of a procedure.
 * Currently there a two phases related to a procedure, therefore this Entity is related to the procedure twice.
 *
 * @ORM\Table
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ProcedurePhaseRepository")
 */
class ProcedurePhase extends CoreEntity implements UuidEntityInterface, ProcedurePhaseInterface
{
    /**
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected ?string $id;

    /**
     * Virtual property
     * Readable Phase name.
     *
     * @var string
     */
    protected string $name;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    protected string $key;

    /**
     * Virtual Property bound on phase configuration in procedurephases.yml.
     *
     * @var string
     */
    protected string $permissionSet;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=25, nullable=false, options={"default":""})
     */
    protected string $step = '';

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    protected string $participationState;

    /**
     * @ORM\Column(name="_p_start_date", type="datetime", nullable=false)
     */
    protected DateTime $startDate;

    /**
     * @ORM\Column(name="_p_end_date", type="datetime", nullable=false)
     */
    protected DateTime $endDate;

    /**
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", mappedBy="phase")
     * @ORM\OrderBy({"name" = "ASC"})
     */
    protected Procedure $procedure;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private DateTime $creationDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $modificationDate;

    /**
     * @ORM\Column(name="_ps_designated_phase", type="string", length=50, nullable=true)
     */
    protected ?string $designatedPhase = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected ?DateTime $designatedSwitchDate;

    /**
     * OnDelete set NULL at this site, will set the userID to null in case of the user will be deleted.
     * Doing this by a doctrine relation is not simply possible because,
     * the user has no defined relation in its class.
     *
     * @var UserInterface|null
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     * @ORM\JoinColumn(referencedColumnName="_u_id", nullable=true, onDelete="SET NULL")
     */
    protected $designatedPhaseChangeUser;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected ?DateTime $designatedEndDate;

    public function __construct(string $key, string $step = '')
    {

        //virtual properties:
        //name
        //Permissionset

        $this->key = $key;
        $this->step = $step;
        $this->endDate = new DateTime();
        $this->startDate = new DateTime();

    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getPermissionSet(): string
    {
        return $this->permissionSet;
    }

    public function setPermissionSet(string $permissionSet): void
    {
        $this->permissionSet = $permissionSet;
    }

    public function getParticipationState(): string
    {
        return $this->participationState;
    }

    public function setParticipationState(string $participationState): void
    {
        $this->participationState = $participationState;
    }

    public function getStartDate(): DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(DateTime $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }

    public function setProcedure(Procedure $procedure): void
    {
        $this->procedure = $procedure;
    }

    public function getCreationDate(): DateTime
    {
        return $this->creationDate;
    }

    public function getModificationDate(): DateTime
    {
        return $this->modificationDate;
    }

    public function getDesignatedPhase(): ?string
    {
        return $this->designatedPhase;
    }

    public function setDesignatedPhase(?string $designatedPhase): void
    {
        $this->designatedPhase = $designatedPhase;
    }

    public function getDesignatedSwitchDate(): ?DateTime
    {
        return $this->designatedSwitchDate;
    }

    public function setDesignatedSwitchDate(?DateTime $designatedSwitchDate): void
    {
        $this->designatedSwitchDate = $designatedSwitchDate;
    }

    public function getDesignatedPhaseChangeUser(): ?UserInterface
    {
        return $this->designatedPhaseChangeUser;
    }

    public function setDesignatedPhaseChangeUser(?UserInterface $designatedPhaseChangeUser): void
    {
        $this->designatedPhaseChangeUser = $designatedPhaseChangeUser;
    }

    public function getDesignatedEndDate(): ?DateTime
    {
        return $this->designatedEndDate;
    }

    public function setDesignatedEndDate(?DateTime $designatedEndDate): void
    {
        $this->designatedEndDate = $designatedEndDate;
    }

    public function __toString(): string
    {
        return $this->getKey();
    }

    public function getStep(): string
    {
        return $this->step;
    }

    public function setStep(string $step): void
    {
        $this->step = $step;
    }

}
