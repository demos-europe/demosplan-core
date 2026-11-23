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

    public function hasParts(): bool
    {
        return null !== $this->messageText;
    }

    /**
     * The body that is sent. With separate parts it is built from them, so the sent content always
     * matches what is written to the version history. Without parts the given body is used.
     */
    public function getMailBody(): ?string
    {
        if (!$this->hasParts()) {
            return $this->body;
        }

        $parts = array_filter(
            [$this->messageText, $this->segmentText, $this->recommendationText],
            static fn (?string $part): bool => null !== $part && '' !== $part
        );

        return implode("\n\n", $parts);
    }
}
