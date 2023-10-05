<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Monolog;

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\HandlerInterface;
use Sentry\State\Scope;
use Throwable;

use function Sentry\withScope;

/**
 * This Monolog handler logs every message to a Sentry's server using the given
 * hub instance.
 *
 * If extra information is added to the simple textual message (through an array
 * of data along the record), this custom Sentry handler iterate the array to set
 * data in the extra Sentry context.
 */
class SentryHandler implements HandlerInterface
{
    public function __construct(private readonly HandlerInterface $decoratedHandler)
    {
    }

    public function isHandling(array $record): bool
    {
        return $this->decoratedHandler->isHandling($record);
    }

    public function handle(array $record): bool
    {
        $result = false;

        withScope(function (Scope $scope) use ($record, &$result): void {
            if (isset($record['context']) && is_array($record['context'])) {
                foreach ($record['context'] as $key => $value) {
                    if ('tags' === $key) {
                        // Handled natively by Sentry monolog handler
                        continue;
                    }
                    // only add extra infos on app messages
                    $channels = ['app', 'dplan'];
                    if (in_array($record['channel'], $channels, true)) {
                        $decodedValue = $value;
                        try {
                            if (is_string($value)) {
                                $decodedValue = Json::decodeToMatchingType($value);
                            } elseif ($value instanceof Throwable) {
                                $decodedValue = $value->getMessage().' '.$value->getTraceAsString();
                            }
                        } catch (JsonException) {
                            $decodedValue = $value;
                        }
                        $scope->setExtra($key, $decodedValue);
                    }
                }
            }

            $result = $this->decoratedHandler->handle($record);
        });

        return $result;
    }

    public function handleBatch(array $records): void
    {
        $this->decoratedHandler->handleBatch($records);
    }

    public function close(): void
    {
        $this->decoratedHandler->close();
    }

    public function pushProcessor($callback)
    {
        $this->decoratedHandler->pushProcessor($callback);

        return $this;
    }

    public function popProcessor()
    {
        return $this->decoratedHandler->popProcessor();
    }

    public function setFormatter(FormatterInterface $formatter)
    {
        $this->decoratedHandler->setFormatter($formatter);

        return $this;
    }

    public function getFormatter()
    {
        return $this->decoratedHandler->getFormatter();
    }
}
