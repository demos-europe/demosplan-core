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
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\LogRecord;
use Monolog\ResettableInterface;
use Sentry\State\Scope;

use function Sentry\withScope;

use Throwable;

/**
 * This Monolog handler logs every message to a Sentry's server using the given
 * hub instance.
 *
 * If extra information is added to the simple textual message (through an array
 * of data along the record), this custom Sentry handler iterate the array to set
 * data in the extra Sentry context.
 */
class SentryHandler implements HandlerInterface, ProcessableHandlerInterface, FormattableHandlerInterface, ResettableInterface
{
    public function __construct(private readonly HandlerInterface $decoratedHandler)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function isHandling(LogRecord $record): bool
    {
        return $this->decoratedHandler->isHandling($record);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(LogRecord $record): bool
    {
        $result = false;

        withScope(function (Scope $scope) use ($record, &$result): void {
            $context = $record->context;
            if (is_array($context)) {
                foreach ($context as $key => $value) {
                    if ('tags' === $key) {
                        // Handled natively by Sentry monolog handler
                        continue;
                    }
                    // only add extra infos on app messages
                    $channels = ['app', 'dplan'];
                    if (in_array($record->channel, $channels, true)) {
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

    /**
     * {@inheritDoc}
     */
    public function handleBatch(array $records): void
    {
        $this->decoratedHandler->handleBatch($records);
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        $this->decoratedHandler->close();
    }

    /**
     * {@inheritDoc}
     */
    public function pushProcessor(callable $callback): HandlerInterface
    {
        if ($this->decoratedHandler instanceof ProcessableHandlerInterface) {
            $this->decoratedHandler->pushProcessor($callback);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function popProcessor(): callable
    {
        if ($this->decoratedHandler instanceof ProcessableHandlerInterface) {
            return $this->decoratedHandler->popProcessor();
        }
        
        throw new \LogicException('The wrapped handler does not implement ProcessableHandlerInterface');
    }

    /**
     * {@inheritDoc}
     */
    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        if ($this->decoratedHandler instanceof FormattableHandlerInterface) {
            $this->decoratedHandler->setFormatter($formatter);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getFormatter(): FormatterInterface
    {
        if ($this->decoratedHandler instanceof FormattableHandlerInterface) {
            return $this->decoratedHandler->getFormatter();
        }
        
        throw new \LogicException('The wrapped handler does not implement FormattableHandlerInterface');
    }
    
    /**
     * {@inheritDoc}
     */
    public function reset(): void
    {
        if ($this->decoratedHandler instanceof ResettableInterface) {
            $this->decoratedHandler->reset();
        }
    }
}
