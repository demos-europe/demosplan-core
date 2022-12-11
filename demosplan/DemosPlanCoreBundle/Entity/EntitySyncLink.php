<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Component\Validator\Constraints as Assert;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use function get_class;

/**
 * @ORM\Table (uniqueConstraints={
 *     @UniqueConstraint(name="unique_source", columns={"class", "source_id"}),
 *     @UniqueConstraint(name="unique_target", columns={"class", "target_id"})
 * })
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\EntitySyncLinkRepository")
 *
 * @template T of \demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface
 */
class EntitySyncLink implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * @var class-string<T>
     *
     * @Assert\NotBlank(allowNull=false, normalizer="trim")
     * @ORM\Column(type="string")
     */
    private $class;

    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=false, normalizer="trim")
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     */
    private $sourceId;

    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=false, normalizer="trim")
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     */
    private $targetId;

    /**
     * @param T $source
     * @param T $target
     */
    public function __construct(UuidEntityInterface $source, UuidEntityInterface $target)
    {
        $this->class = get_class($source);
        if (get_class($target) !== $this->class) {
            throw new InvalidArgumentException('Class of source and target does not match.');
        }

        $this->sourceId = $source->getId();
        $this->targetId = $target->getId();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }
}
