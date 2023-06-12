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
    public function setTitle(string $title): self;

    public function getTitle(): string;

    public function setText(string $text): self;

    public function getText(): string;

    public function setEnabled(bool $enabled): self;

    public function getEnabled(): bool;

    public function setCreateDate(DateTime $createDate): self;

    public function getCreateDate(): DateTime;

    public function setModifyDate(DateTime $modifyDate): self;

    public function getModifyDate(): DateTime;

    /**
     * @param array<int, Role> $roles
     */
    public function setRoles(array $roles): self;

    public function addRole(Role $role): self;

    /**
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection;

    public function setCategory(FaqCategoryInterface $faqCategory): self;

    public function getCategory(): FaqCategoryInterface;

    public function hasRoleGroupCode(string $code): bool;
}
