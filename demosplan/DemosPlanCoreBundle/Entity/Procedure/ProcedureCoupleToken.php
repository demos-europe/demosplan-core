<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureCoupleTokenInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\ProcedureInCoupleAlreadyUsedConstraint;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ProcedureCoupleTokenRepository")
 *
 * @ProcedureInCoupleAlreadyUsedConstraint()
 */
class ProcedureCoupleToken implements UuidEntityInterface, ProcedureCoupleTokenInterface
{
    final public const TOKEN_LENGTH = 12;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * The procedure the {@link ProcedureCoupleToken::token} was generated for.
     *
     * @var Procedure
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     *
     * @ORM\JoinColumn(referencedColumnName="_p_id", nullable=false, unique=true)
     */
    #[Assert\NotNull(message: 'procedureCoupleToken.sourceProceudre.not.null')]
    protected $sourceProcedure;

    /**
     * The procedure the {@link ProcedureCoupleToken::token} is used for to couple it with the
     * {@link ProcedureCoupleToken::$sourceProcedure}.
     *
     * @var Procedure|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     *
     * @ORM\JoinColumn(referencedColumnName="_p_id", nullable=true, unique=true)
     */
    protected $targetProcedure;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=12, nullable=false, unique=true, options={"fixed":true})
     */
    #[Assert\Length(max: ProcedureCoupleToken::TOKEN_LENGTH, min: ProcedureCoupleToken::TOKEN_LENGTH, normalizer: 'trim')]
    #[Assert\NotBlank(message: 'procedureCoupleToken.token.invalid', allowNull: false, normalizer: 'trim')]
    protected $token;

    public function __construct(Procedure $sourceProcedure, string $token)
    {
        $this->sourceProcedure = $sourceProcedure;
        $this->token = $token;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSourceProcedure(): Procedure
    {
        return $this->sourceProcedure;
    }

    /**
     * @throws InvalidDataException
     */
    public function setTargetProcedure(Procedure $targetProcedure): self
    {
        if (0 !== $targetProcedure->getStatements()->count()) {
            throw new InvalidDataException('Target procedure has to be empty');
        }

        if ($this->sourceProcedure === $targetProcedure
            || $this->sourceProcedure->getId() === $targetProcedure->getId()) {
            throw new InvalidDataException('Target procedure must not be the same as the source procedure');
        }

        $this->targetProcedure = $targetProcedure;

        return $this;
    }

    public function getTargetProcedure(): ?Procedure
    {
        return $this->targetProcedure;
    }

    public function isSourceProcedure(Procedure $procedure): bool
    {
        return $this->sourceProcedure->getId() === $procedure->getId()
            && null !== $procedure->getId();
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
