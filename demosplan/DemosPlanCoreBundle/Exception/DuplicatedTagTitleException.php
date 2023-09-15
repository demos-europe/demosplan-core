<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Exception;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;

class DuplicatedTagTitleException extends Exception
{
    public function __construct(string                       $message,
                                protected readonly Procedure $procedure,
                                protected readonly TagTopic  $topic,
                                protected readonly string    $tagTitle,
    )
    {
        parent::__construct($message, 0, null);
    }

    /**
     * @return static
     */
    public static function createFromTitleAndProcedureId(Procedure $procedure, TagTopic $topic, string $tagTitle): self
    {
        return new self("A tag with the title {$tagTitle} already exist in a procedure with the ID {$procedure->getId()}", $procedure, $topic, $tagTitle);
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
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
