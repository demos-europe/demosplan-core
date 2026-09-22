<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Entities\BookmarkInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\BookmarkRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A view of a procedure saved by a single user under a name of their choosing. The referenced
 * {@see HashedQuery} holds the stored query itself; this entity only adds the name and the owner.
 *
 * The relationship is still called `filterSet` because it maps to the `filter_set_id` column and to
 * the published JSON:API property of the same name, even though the stored query it points at
 * carries more than filters.
 */
#[ORM\Table(name: 'bookmark')]
#[ORM\Entity(repositoryClass: BookmarkRepository::class)]
class Bookmark extends CoreEntity implements UuidEntityInterface, BookmarkInterface
{
    private const ON_DELETE_NO_ACTION = 'NO ACTION';

    /**
     * Unique identification of the Bookmark entry.
     *
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 36, nullable: false, options: ['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    protected $id;

    /**
     * @var User
     */
    #[ORM\JoinColumn(referencedColumnName: '_u_id', nullable: false, onDelete: self::ON_DELETE_NO_ACTION)]
    #[ORM\ManyToOne(targetEntity: User::class, cascade: ['persist'])]
    protected $user;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string', nullable: false)]
    protected $name;

    /**
     * @var HashedQuery
     */
    #[ORM\JoinColumn(nullable: false, onDelete: self::ON_DELETE_NO_ACTION)]
    #[ORM\ManyToOne(targetEntity: HashedQuery::class)]
    protected $filterSet;

    /**
     * @var Procedure
     */
    #[ORM\JoinColumn(referencedColumnName: '_p_id', nullable: false, onDelete: self::ON_DELETE_NO_ACTION)]
    #[ORM\ManyToOne(targetEntity: Procedure::class, cascade: ['persist'])]
    protected $procedure;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return HashedQuery
     */
    public function getFilterSet()
    {
        return $this->filterSet;
    }

    /**
     * @param HashedQuery $filterSet
     */
    public function setFilterSet($filterSet)
    {
        $this->filterSet = $filterSet;
    }

    /**
     * @return Procedure
     */
    public function getProcedure()
    {
        return $this->procedure;
    }

    /**
     * @param Procedure $procedure
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;
    }
}
