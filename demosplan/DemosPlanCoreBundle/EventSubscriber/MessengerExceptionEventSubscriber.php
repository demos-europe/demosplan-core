<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Enhanced error reporting for Symfony Messenger consumers.
 * This subscriber catches messenger worker failures and displays detailed error information.
 */
class MessengerExceptionEventSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $error = $event->getThrowable();
        $message = $event->getEnvelope()->getMessage();
        $messageClass = get_class($message);

        // Error message container
        $errorMessages = [];
        $errorMessages[] = "\n\033[31m[MESSENGER ERROR] Failed handling message {$messageClass}\033[0m";

        // Handle HandlerFailedException specially to extract nested exceptions
        if ($error instanceof HandlerFailedException) {
            $exceptions = $error->getWrappedExceptions();
            $num = 1;
            foreach ($exceptions as $exception) {
                $errorMessages[] = sprintf(
                    "\033[31m[Error %d/%d] %s: %s\033[0m",
                    $num++,
                    count($exceptions),
                    get_class($exception),
                    $exception->getMessage()
                );

                // Add file and line info
                $errorMessages[] = sprintf(
                    "\033[31mIn %s line %d\033[0m",
                    $exception->getFile(),
                    $exception->getLine()
                );

                // Add stack trace for better debugging
                $errorMessages[] = "\033[33mStack trace (first 10 levels):\033[0m";
                $trace = $exception->getTraceAsString();
                $traceLines = explode("\n", $trace);
                // Only include first 10 lines of trace to avoid flooding the console
                foreach (array_slice($traceLines, 0, 10) as $traceLine) {
                    $errorMessages[] = "\033[33m{$traceLine}\033[0m";
                }
            }
        } else {
            // Single error
            $errorMessages[] = sprintf(
                "\033[31m[Error] %s: %s\033[0m",
                get_class($error),
                $error->getMessage()
            );

            // Add file and line info
            $errorMessages[] = sprintf(
                "\033[31mIn %s line %d\033[0m",
                $error->getFile(),
                $error->getLine()
            );

            // Add stack trace for better debugging
            $errorMessages[] = "\033[33mStack trace (first 10 levels):\033[0m";
            $trace = $error->getTraceAsString();
            $traceLines = explode("\n", $trace);
            // Only include first 10 lines of trace to avoid flooding the console
            foreach (array_slice($traceLines, 0, 10) as $traceLine) {
                $errorMessages[] = "\033[33m{$traceLine}\033[0m";
            }
        }

        // Show receiver name
        $errorMessages[] = sprintf(
            "\033[31mMessage was received from \"%s\" transport\033[0m",
            $event->getReceiverName()
        );

        // Display if the message will be retried or not
        if ($event->willRetry()) {
            $errorMessages[] = "\033[36mMessage will be retried\033[0m";
        } else {
            $errorMessages[] = "\033[31mMessage will NOT be retried\033[0m";
        }

        // Debug message contents
        $errorMessages[] = "\033[33mMessage contents:\033[0m";
        $errorMessages[] = "\033[33m" . print_r($message, true) . "\033[0m";

        // Output combined error message
        $formattedError = implode("\n", $errorMessages);
        echo $formattedError . "\n";

        // Also log to file
        $this->logger->error('Messenger worker error:', [
            'message_class' => $messageClass,
            'error' => $error->getMessage(),
            'trace' => $error->getTraceAsString(),
        ]);
    }
}
