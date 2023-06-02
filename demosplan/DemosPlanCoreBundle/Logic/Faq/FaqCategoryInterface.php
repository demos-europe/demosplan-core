<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Faq;

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DateTime;

interface FaqCategoryInterface extends UuidEntityInterface
{
    /**
     * These are allowed types, independent of the role.
     */
    public const FAQ_CATEGORY_TYPES_MANDATORY = [
        'system',
        'technische_voraussetzung',
        'bedienung',
        'oeb_bauleitplanung',
        'oeb_bob',
    ];

    /**
     * These are role-dependent types.
     */
    public const FAQ_CATEGORY_TYPES_OPTIONAL = 'custom_category';
    /**
     * @param string $title
     */
    public function setTitle($title): self;

    public function getTitle(): string;

    public function getType(): string;

    public function setType(string $type): void;

    public function isCustom(): bool;

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
