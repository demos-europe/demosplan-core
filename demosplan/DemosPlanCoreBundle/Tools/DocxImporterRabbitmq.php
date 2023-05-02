<?php
declare(strict_types=1);

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
     * Importiere ein docx-Dokument mittels RabbitMQ Instanz.
     *
     * @param string $elementId
     * @param string $procedure
     * @param string $category
     *
     * @return array
     *
     * @throws Exception
     */
    public function importDocx(File $file, string $elementId, string $procedure, string $category): array
    {
        try {
            // Generiere Message
            $msg = Json::encode([
                'procedure' => $procedure,
                'category' => $category,
                'elementId' => $elementId,
                'path' => $file->getRealPath(),
            ]);

            $routingKey = $this->globalConfig->getProjectPrefix();
            if ($this->globalConfig->isMessageQueueRoutingDisabled()) {
                $routingKey = '';
            }

            // Füge Message zum Request hinzu
            $this->logger->debug(
                'Import docx with RabbitMQ, with routingKey: ' . $routingKey
            );
            $this->client->addRequest(
                $msg,
                'importDemosPlan',
                'import',
                $routingKey,
                300
            );
            // Anfrage absenden
            $replies = $this->client->getReplies();

            if ('' != $replies['import']) {
                $this->logger->info(
                    'Incoming message size:' . strlen($replies['import'])
                );
            }

            return Json::decodeToArray($replies['import']);
        } catch (AMQPTimeoutException $e) {
            $this->logger->error(
                'Fehler in ImportConsumer:',
                [$e]
            );
            throw new TimeoutException($e->getMessage());
        } catch (Exception $e) {
            $this->logger->error(
                'Fehler in ImportConsumer:',
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
