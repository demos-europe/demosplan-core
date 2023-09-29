<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use Exception;

class DuplicatedTagTitleException extends Exception
{
    public function __construct(string $message,
        protected readonly TagTopic $topic,
        protected readonly string $tagTitle,
    ) {
        parent::__construct($message);
    }

    /**
     * @return static
     */
    public static function createFromTitleAndProcedureId(TagTopic $topic, string $tagTitle): self
    {
        return new self("A tag with the title {$tagTitle} already exist in a procedure with the ID {$topic->getProcedure()->getId()}", $topic, $tagTitle);
    }

    public function getTagTitle(): string
    {
        return $this->tagTitle;
    }

    public function getTopic(): TagTopic
    {
        return $this->topic;
    }
}
