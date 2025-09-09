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
use Exception;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;

class DocxImporterRabbitmq implements DocxImporterInterface
{
    /**
     * @var RpcClient
     */
    protected $client;

    public function __construct(private readonly LoggerInterface $logger, private readonly GlobalConfigInterface $globalConfig)
    {
    }

    /**
     * Import docx via RabbitMQ.
     *
     * @throws Exception
     */
    public function importDocx(File $file, string $elementId, string $procedure, string $category): array
    {
        try {
            $msg = Json::encode([
                'procedure' => $procedure,
                'category'  => $category,
                'elementId' => $elementId,
                'path'      => $file->getRealPath(),
            ]);

            $routingKey = $this->globalConfig->getProjectPrefix();
            if ($this->globalConfig->isMessageQueueRoutingDisabled()) {
                $routingKey = '';
            }

            $this->logger->debug(
                'Import docx with RabbitMQ, with routingKey: '.$routingKey
            );
            $this->client->addRequest(
                $msg,
                'importDemosPlan',
                'import',
                $routingKey,
                300
            );
            $replies = $this->client->getReplies();

            if ('' != $replies['import']) {
                $this->logger->info(
                    'Incoming message size:'.strlen((string) $replies['import'])
                );
            }

            return Json::decodeToArray($replies['import']);
        } catch (AMQPTimeoutException $e) {
            $this->logger->error(
                'Error in ImportConsumer:',
                [$e]
            );
            throw new TimeoutException($e->getMessage());
        } catch (Exception $e) {
            $this->logger->error(
                'Error in ImportConsumer:',
                [$e]
            );
            throw $e;
        }
    }

    /**
     * @param RpcClient $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }
}
