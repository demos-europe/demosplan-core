<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer;

class TransformerLoader
{
    protected $transformers = [];

    /**
     * @param iterable<BaseTransformer> $transformers
     */
    public function __construct(iterable $transformers)
    {
        foreach ($transformers as $transformer) {
            // for some reason, this didn't work when inlined into the array brackets
            /** @var BaseTransformer $transformer */
            $transformerClass = $transformer->getClass();

            $this->transformers[$transformerClass] = $transformer;
        }
    }

    public function get(string $fullyQualifiedTransformerName): BaseTransformer
    {
        return $this->transformers[$fullyQualifiedTransformerName];
    }
}
