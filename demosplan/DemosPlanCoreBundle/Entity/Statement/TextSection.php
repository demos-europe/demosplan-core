<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\TextSectionRepository")
 *
 * @ORM\Table(name="text_section")
 */
class TextSection extends CoreEntity
{
    /**
     * @var string
     *
     * @ORM\Column(name="id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var Statement
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="textSections")
     *
     * @ORM\JoinColumn(name="statement_id", referencedColumnName="_st_id", nullable=false, onDelete="CASCADE")
     */
    #[Assert\NotNull]
    protected $statement;

    /**
     * @var int
     *
     * @ORM\Column(name="order_in_statement", type="integer", nullable=false)
     */
    #[Assert\NotNull]
    #[Assert\GreaterThan(0)]
    protected $orderInStatement;

    /**
     * @var string
     *
     * @ORM\Column(name="text_raw", type="text", nullable=false)
     */
    #[Assert\NotBlank]
    protected $textRaw;

    /**
     * @var string
     *
     * @ORM\Column(name="text", type="text", nullable=false)
     */
    #[Assert\NotBlank]
    protected $text;

    public function getId()
    {
        return $this->id;
    }

    public function getStatement()
    {
        return $this->statement;
    }

    public function setStatement(Statement $statement): self
    {
        $this->statement = $statement;

        return $this;
    }

    public function getOrderInStatement(): int
    {
        return $this->orderInStatement;
    }

    public function setOrderInStatement(int $orderInStatement): self
    {
        $this->orderInStatement = $orderInStatement;

        return $this;
    }

    public function getTextRaw(): string
    {
        return $this->textRaw;
    }

    public function setTextRaw(string $textRaw): self
    {
        $this->textRaw = $textRaw;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }
}
