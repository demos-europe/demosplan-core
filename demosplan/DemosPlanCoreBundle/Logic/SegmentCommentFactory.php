<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\SegmentComment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;

class SegmentCommentFactory
{
    public function createSegmentComment(Segment $segment, User $user, Place $place, string $text): SegmentComment
    {
        $comment = new SegmentComment($segment, $user, $place, $text);
        $segment->addComment($comment);

        return $comment;
    }
}
