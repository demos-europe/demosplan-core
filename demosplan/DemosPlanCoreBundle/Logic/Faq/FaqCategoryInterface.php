<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Faq;

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DateTime;

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
