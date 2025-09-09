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
use Exception;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use Psr\Log\LoggerInterface;

class PdfCreatorRabbitmq implements PdfCreatorInterface
{
    /**
     * @var RpcClient
     */
    protected $client;

    public function __construct(private readonly GlobalConfigInterface $globalConfig, private readonly LoggerInterface $logger)
    {
    }

    /**
     * Erstelle ein PDF aus einer tex-Vorlage.
     *
     * @param string $content  base64 encodierte tex-Datei
     * @param array  $pictures array der Form ['picture0 => base64_encode(''), 'picture1' => ....]
     *
     * @throws Exception
     */
    public function createPdf(string $content, array $pictures = []): string
    {
        $payload = [
            'file' => $content,
        ];
        $payload = array_merge($payload, $pictures);
        $msg = Json::encode($payload);

        $this->logger->debug(
            'Export pdf with RabbitMQ, with routingKey: '.$this->globalConfig->getProjectPrefix()
        );
        $this->logger->debug(
            'Content to send to RabbitMQ: '.DemosPlanTools::varExport(
                base64_decode($content),
                true
            )
        );
        $this->logger->debug(
            'Number of pictures send to RabbitMQ: '.count($pictures)
        );

        try {
            $routingKey = $this->globalConfig->getProjectPrefix();
            if ($this->globalConfig->isMessageQueueRoutingDisabled()) {
                $routingKey = '';
            }
            $this->client->addRequest(
                $msg,
                'pdfDemosPlan',
                'exportPDF',
                $routingKey,
                600
            );
            $replies = $this->client->getReplies();
            $this->logger->debug(
                'Got replies ',
                [DemosPlanTools::varExport($replies, true)]
            );

            $exportResult = Json::decodeToArray($replies['exportPDF']);
            if (null === $exportResult) {
                $this->logger->error(
                    'Reply from RabbitMQ: ',
                    [DemosPlanTools::varExport($replies, true)]
                );
                throw new Exception('Could not decode export result');
            } elseif (!isset($exportResult['file'])) {
                $this->logger->error(
                    'AMPQResult has wrong format ',
                    [DemosPlanTools::varExport($exportResult, true)]
                );
                throw new Exception('AMPQResult has wrong format');
            }

            return $exportResult['file'];
        } catch (AMQPTimeoutException $e) {
            $this->logger->error(
                'Fehler in ImportConsumer:',
                [$e]
            );
            throw new TimeoutException('Timeout ');
        } catch (Exception $e) {
            $this->logger->error(
                'Could not create PDF ',
                [$e]
            );
            throw new Exception('Could not create PDF ');
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
