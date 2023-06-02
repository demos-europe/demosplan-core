<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Faq;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use Doctrine\Common\Collections\Collection;

interface FaqInterface extends UuidEntityInterface
{
    public function setTitle($title): self;

    /**
     * Get title.
     */
    public function getTitle(): string;

    /**
     * Set text.
     *
     * @param string $text
     */
    public function setText($text): self;

    /**
     * Get text.
     */
    public function getText(): string;

    /**
     * Set enabled.
     *
     * @param bool $enabled
     */
    public function setEnabled($enabled): self;

    /**
     * Get enabled.
     */
    public function getEnabled(): bool;

    /**
     * Set createDate.
     *
     * @param DateTime $createDate
     */
    public function setCreateDate($createDate): self;

    /**
     * Get createDate.
     */
    public function getCreateDate(): DateTime;

    /**
     * Set modifyDate.
     *
     * @param DateTime $modifyDate
     */
    public function setModifyDate($modifyDate): self;

    /**
     * Get modifyDate.
     */
    public function getModifyDate(): DateTime;

    /**
     * Set Roles.
     *
     * @param array $roles
     */
    public function setRoles($roles): self;

    /**
     * Add Role.
     */
    public function addRole(Role $role): self;

    /**
     * Get Roles.
     *
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection;

    /**
     * Set Category.
     *
     * @param FaqCategoryInterface $faqCategory
     */
    public function setCategory($faqCategory): self;

    /**
     * Get Category.
     */
    public function getCategory(): FaqCategoryInterface;

    public function hasRoleGroupCode(string $code): bool;
}
