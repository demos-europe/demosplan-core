<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

/**
 * Content of a mail that sends segments to an external recipient.
 *
 * The separate parts are only used to label them in the version history. If they are
 * missing, the whole body is logged as the message.
 */
final class SegmentMailContent
{
    public function __construct(
        public readonly ?string $subject,
        public readonly ?string $body,
        public readonly ?string $messageText = null,
        public readonly ?string $segmentText = null,
        public readonly ?string $recommendationText = null,
    ) {
    }
}
