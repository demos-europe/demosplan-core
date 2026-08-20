<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Turns the exception that broke an export into the reason shown to the user.
 *
 * The status endpoint hands this string to the browser, so it must be translated and must not carry
 * internals such as SQL fragments or server paths. {@link DemosException} already separates the two:
 * its user message is a translation key, while the technical detail goes to the log.
 */
class ExportJobFailureReason
{
    private const GENERIC_REASON = 'error.export';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function forThrowable(Throwable $exception): string
    {
        $reason = $exception instanceof DemosException ? $exception->getUserMsg() : '';

        return $this->translator->trans('' !== $reason ? $reason : self::GENERIC_REASON);
    }
}
