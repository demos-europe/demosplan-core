<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use EDT\Wrapping\Contracts\Types\ReadableTypeInterface;
use EDT\Wrapping\Contracts\Types\TypeInterface;
use EDT\Wrapping\Utilities\TypeAccessor;

class CacheableTypeAccessor extends TypeAccessor
{
    /**
     * Mapping from the MD5 hash of a resource type FQN to its
     * {@link TypeAccessor::getAccessibleReadableProperties()} result.
     *
     * @var array<string,array<string,ReadableTypeInterface|null>>
     */
    private $cachedProperties = [];

    public function getAccessibleReadableProperties(TypeInterface $type): array
    {
        $typeHash = md5(get_class($type));
        if (!array_key_exists($typeHash, $this->cachedProperties)) {
            $this->cachedProperties[$typeHash] = parent::getAccessibleReadableProperties($type);
        }

        return $this->cachedProperties[$typeHash];
    }
}
