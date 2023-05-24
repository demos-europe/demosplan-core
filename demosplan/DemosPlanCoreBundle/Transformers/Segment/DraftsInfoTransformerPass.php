<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers\Segment;

use DemosEurope\DemosplanAddon\Contracts\DraftsInfoTransformerInterface;
use Symfony\Component\Form\Exception\RuntimeException;

/**
 * Finds the proper transformer for a given input format and does the transformation.
 *
 * Class DraftsInfoTransformerPass
 */
class DraftsInfoTransformerPass
{
    /**
     * @var iterable<DraftsInfoTransformerInterface>
     */
    private $transformers;

    /**
     * @param iterable<DraftsInfoTransformerInterface> $transformers
     */
    public function __construct(iterable $transformers)
    {
        $this->transformers = $transformers;
    }

    /**
     * @param mixed $data
     *
     * @return mixed
     */
    public function transform($data, string $format)
    {
        /** @var DraftsInfoTransformerInterface $transformer */
        foreach ($this->transformers as $transformer) {
            if ($transformer->supports($format)) {
                return $transformer->transform($data);
            }
        }

        throw new RuntimeException('No support for format '.$format);
    }
}
