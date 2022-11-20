<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Interfaces;

/**
 * Interface setting the methods to handle transformation from the input data
 * to DraftsList.
 *
 * Interface DraftsInfoTransformerInterface
 */
interface DraftsInfoTransformerInterface
{
    public const STATEMENT = 'statement';
    public const PROPOSALS = 'proposals';

    /**
     * Transform $data to DraftsList.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public function transform($data);

    /**
     * Returns true if the format is transformable by each specific
     * implementation.
     */
    public function supports(string $format): bool;
}
