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

use demosplan\DemosPlanCoreBundle\Exception\ValueObjectException;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use EDT\Querying\Contracts\PaginationException;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\SortException;
use EDT\Wrapping\WrapperFactories\WrapperObject;

use function strlen;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

class TwigableWrapperObject extends WrapperObject
{
    /**
     * @return mixed|void|null
     *
     * @throws PathException
     * @throws PaginationException
     * @throws SortException
     */
    public function __call(string $methodName, array $arguments = [])
    {
        preg_match(ValueObject::ACCESSOR_REGEX, $methodName, $match);

        // First item is complete $name, second set|get, third the property name
        if (3 !== count($match)) {
            throw ValueObjectException::failedToParseAccessor($methodName);
        }

        $backTrace = debug_backtrace(
            DEBUG_BACKTRACE_IGNORE_ARGS,
            1
        );

        // if we're called from inside a twig template, only allow getting values
        if (0 < strpos($backTrace[0]['file'], ValueObject::TWIG_LOCATION)) {
            if (0 < strlen($match[1])) {
                throw ValueObjectException::noAccessorAllowedFromTwig();
            }

            return $this->__get($match[2]);
        }

        return parent::__call($methodName, $arguments);
    }
}
