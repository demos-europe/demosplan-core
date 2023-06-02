<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;
use demosplan\DemosPlanCoreBundle\ValueObject\SlugDraftValueObject;

class SlugDraftTransformer extends BaseTransformer
{
    /** @var string */
    protected $type = 'slug-draft';

    public function transform(SlugDraftValueObject $slugDraft): array
    {
        return [
            'id'             => $slugDraft->getId(),
            'originalValue'  => $slugDraft->getOriginalValue(),
            'slugifiedValue' => $slugDraft->getSlugifiedValue(),
        ];
    }
}
