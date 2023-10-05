<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utilities;

use Sentry\State\Scope;

use function Sentry\captureMessage;
use function Sentry\withScope;

class ManualSentrySender
{
    /**
     * This will send a message with the given information to Sentry. It will include a trace stack.
     *
     * IMPORTANT NOTE: $parameters MUST NOT include the index 'type'. That is reserved by Sentry for internal use.
     */
    public static function sendSentryMessage(string $context, string $message, array $parameters): void
    {
        withScope(function (Scope $scope) use ($context, $message, $parameters): void {
            $scope->setContext($context, $parameters);

            captureMessage($message);
        });
    }
}
