<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Traits\CanTransformRequestVariablesTrait;
use demosplan\DemosPlanCoreBundle\Traits\IsProfilableTrait;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Enthält die Handlerfunktionalitäten, die alle Handler nutzen können
 * Class CoreHandler.
 */
class CoreHandler
{
    use IsProfilableTrait;
    use CanTransformRequestVariablesTrait;

    /**
     * @var array
     */
    protected $requestValues = [];

    /**
     * @var FileBag|array()
     */
    protected $symfonyFileBag = [];

    /**
     * @var GlobalConfigInterface|GlobalConfig
     */
    protected $demosplanConfig;

    /**
     * Nur spezifisch genutzte Services, für die sich keine DependencyInjection lohnt.
     *
     * @var array
     */
    protected $helperServices = [];

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var MessageBagInterface
     */
    protected $messageBag;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    public function __construct(MessageBagInterface $messageBag)
    {
        $this->messageBag = $messageBag;
    }

    /**
     * Filter incoming Datafields.
     *
     * @param string $action
     */
    protected function prepareIncomingData($action): array
    {
        $result = [];

        $incomingFields = $this->incomingDataDefinition();

        $request = $this->getRequestValues();

        foreach ($incomingFields[$action] as $key) {
            if (array_key_exists($key, $request)) {
                $result[$key] = $request[$key];
            }
        }

        return $result;
    }

    /**
     * Get form option from globally defined parameter.
     *
     * @param string $key
     *
     * @return mixed|null
     */
    protected function getFormParameter($key)
    {
        $formOptions = $this->getDemosplanConfig()->getFormOptions();

        return $formOptions[$key] ?? null;
    }

    /**
     * Normalisiert einen String zur Nutzung z.B. als Dateinamen als reine ASCII-Zeichen.
     *
     * @param string $string
     *
     * @return string
     */
    public function normalizeString($string)
    {
        return (new UnicodeString($string))->ascii()->toString();
    }

    /**
     * @deprecated
     *
     * @param array $data
     */
    public function createTemplateVars($data): array
    {
        return ['list' => $data];
    }

    /**
     * Definition der incoming Data.
     */
    protected function incomingDataDefinition()
    {
        return [];
    }

    /**
     * @param array $request
     */
    public function setRequestValues($request)
    {
        $this->requestValues = $request;
    }

    /**
     * @return array
     */
    public function getRequestValues()
    {
        return $this->requestValues;
    }

    /**
     * @param FileBag $files
     */
    public function setSymfonyFileBag($files)
    {
        $this->symfonyFileBag = $files;
    }

    /**
     * @return FileBag|array()
     */
    public function getSymfonyFileBag()
    {
        return $this->symfonyFileBag;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }

    public function getSession(): Session
    {
        return $this->requestStack->getSession();
    }

    public function getMessageBag(): MessageBagInterface
    {
        return $this->messageBag;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setStopwatch(Stopwatch $stopwatch)
    {
        $this->stopwatch = $stopwatch;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setDemosplanConfig(GlobalConfigInterface $demosplanConfig)
    {
        $this->demosplanConfig = $demosplanConfig;
    }

    /**
     * @return GlobalConfigInterface
     */
    public function getDemosplanConfig()
    {
        return $this->demosplanConfig;
    }

    /**
     * @return array
     */
    public function getHelperServices()
    {
        return $this->helperServices;
    }

    /**
     * @param array $helperServices
     */
    public function setHelperServices($helperServices)
    {
        $this->helperServices = $helperServices;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $className
     *
     * @return string
     */
    public function getRelativeClassName($className)
    {
        $absolutePathAsArray = explode('\\', $className);

        return end($absolutePathAsArray);
    }
}
