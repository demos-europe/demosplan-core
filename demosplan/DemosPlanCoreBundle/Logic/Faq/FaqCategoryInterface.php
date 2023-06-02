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

interface FaqCategoryInterface extends UuidEntityInterface
{
    /**
     * @param string $title
     */
    public function setTitle($title): self;

    public function getTitle(): string;

    /**
     * @param DateTime $createDate
     */
    public function setCreateDate($createDate): self;

    public function getCreateDate(): DateTime;

    /**
     * @param DateTime $modifyDate
     */
    public function setModifyDate($modifyDate): self;

    public function getModifyDate(): DateTime;
}
