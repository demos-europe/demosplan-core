<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Exception\TimeoutException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;

class VirusCheckRabbitmq implements VirusCheckInterface
{
    protected RpcClient $client;

    public function __construct(
        private readonly GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    public function hasVirus(File $file): bool
    {
        $payload = [
            'path' => $file->getRealPath(),
        ];

        $msg = Json::encode($payload);

        // Add message to request
        try {
            $routingKey = $this->globalConfig->getProjectPrefix();
            if ($this->globalConfig->isMessageQueueRoutingDisabled()) {
                $routingKey = '';
            }

            // send request
            $this->logger->info('Path of file for virusCheck: '.$file->getRealPath().', with routingKey: '.$routingKey);
            $this->client->addRequest($msg, 'virusCheckDemosPlanLocal', 'virusCheck', $routingKey, 10);

            $replies = $this->client->getReplies();

            if ('' !== (string) $replies['virusCheck']) {
                $this->logger->info('Incoming message size:'.strlen((string) $replies['virusCheck']));
            }
            $vCheckResult = Json::decodeToArray($replies['virusCheck']);
            // VirusCheckService returns true if checked successfully and no virus was found
            // this method checks whether the file contains a virus so the result is inverted
            // Use only == here, because the result can be a string or a boolean
            if (true == $vCheckResult['result']) {
                return false;
            }

            $this->logger->warning('File could not be checked. Response: '.DemosPlanTools::varExport($replies, true));
        } catch (AMQPTimeoutException $e) {
            $this->logger->error('Error in virusCheck:', [$e]);
            throw new TimeoutException($e->getMessage());
        }

        return true;
    }

    /**
     * @param RpcClient $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }
}
