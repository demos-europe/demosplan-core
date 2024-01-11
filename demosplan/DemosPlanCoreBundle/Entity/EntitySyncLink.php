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

use DemosEurope\DemosplanAddon\Contracts\Entities\EntitySyncLinkInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table (uniqueConstraints={
 *
 *     @UniqueConstraint(name="unique_source", columns={"class", "source_id"}),
 *     @UniqueConstraint(name="unique_target", columns={"class", "target_id"})
 * })
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\EntitySyncLinkRepository")
 *
 * @template T of \DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface
 */
class EntitySyncLink implements UuidEntityInterface, EntitySyncLinkInterface
{
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
    private $id;

    /**
     * @var class-string<T>
     *
     * @ORM\Column(type="string")
     */
    #[Assert\NotBlank(allowNull: false, normalizer: 'trim')]
    private $class;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     */
    #[Assert\NotBlank(allowNull: false, normalizer: 'trim')]
    private $sourceId;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     */
    #[Assert\NotBlank(allowNull: false, normalizer: 'trim')]
    private $targetId;

    /**
     * @param T $source
     * @param T $target
     */
    public function __construct(UuidEntityInterface $source, UuidEntityInterface $target)
    {
        $this->class = $source::class;
        if ($target::class !== $this->class) {
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
