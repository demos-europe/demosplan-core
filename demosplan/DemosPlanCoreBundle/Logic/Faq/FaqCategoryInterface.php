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
    public function setTitle(string $title): self;

    public function getTitle(): string;

    public function setCreateDate(DateTime $createDate): self;

    public function getCreateDate(): DateTime;

    public function setModifyDate(DateTime $modifyDate): self;

    public function getModifyDate(): DateTime;
}
