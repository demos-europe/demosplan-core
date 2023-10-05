<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Interfaces;

/**
 * Interface setting the methods to handle transformation from the input data
 * to Segment Entities.
 *
 * Interface SegmentTransformerInterface
 */
interface SegmentTransformerInterface
{
    public const DRAFTS_INFO = 'draftsInfo';

    /**
     * Transform $data to Segment Entites.
     */
    public function transform($data);

    /**
     * Returns true if the format is transformable by each specific
     * implementation.
     */
    public function supports(string $format): bool;
}
