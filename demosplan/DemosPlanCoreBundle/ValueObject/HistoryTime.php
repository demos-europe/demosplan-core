<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;

/**
 * @method string getId()
 * @method string getCreated()
 * @method bool   getDisplayChange()
 * @method string getUserId()
 * @method string getUserName()
 * @method string getEntityType()
 * @method array  getFieldNames()
 * @method string getTime()
 */
class HistoryTime extends ValueObject
{
    /**
     * Any {@link EntityContentChange} ID of the merged instances.
     *
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $created;

    /**
     * @var bool
     */
    protected $displayChange;

    /**
     * @var string
     */
    protected $userId;

    /**
     * @var string
     */
    protected $userName;

    /**
     * @var array<int, string>
     */
    protected $fieldNames = [];

    /**
     * @var string
     */
    protected $entityType;

    /**
     * @var string
     */
    protected $time;

    /**
     * @param array<int, EntityContentChange> $changes
     */
    public function __construct(array $changes, string $time)
    {
        // except for the fieldNames only the values of the last entity are kept
        foreach ($changes as $change) {
            $this->id = $change->getId();
            $this->created = $change->getCreated()->format('c');
            $this->displayChange = '' !== $change->getContentChange();
            $this->userId = $change->getUserId();
            $this->userName = $change->getUserName();
            $this->entityType = $change->getEntityType();
            $this->fieldNames[] = $change->getEntityField();
        }
        $this->time = $time;
        $this->lock();
    }

    /**
     * @param array<int, EntityContentChange> $changes
     */
    public static function create(array $changes, string $time): self
    {
        return new self($changes, $time);
    }
}
