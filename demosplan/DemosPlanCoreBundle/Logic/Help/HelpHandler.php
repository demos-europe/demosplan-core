<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Help;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Help\ContextualHelp;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MissingPostParameterException;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use Exception;

class HelpHandler extends CoreHandler
{
    public function __construct(MessageBagInterface $messageBag, private readonly HelpService $helpService)
    {
        parent::__construct($messageBag);
    }

    /**
     * Deletes all ContextualHelp objects that have matching ids
     * with those contained in r_delete.
     *
     * @return int amount of deleted contextual helps
     *
     * @throws Exception
     */
    public function deleteHelpItems(array $data): int
    {
        $i = 0;
        if (array_key_exists('r_delete', $data)) {
            foreach ($data['r_delete'] as $ident) {
                $this->helpService->deleteHelp($ident);
                ++$i;
            }
        }

        return $i;
    }

    /**
     * @param array $data
     *
     * @return bool|string
     *
     * @throws MissingPostParameterException
     * @throws Exception
     */
    public function updateContextualHelp($data)
    {
        if (!$this->isArrayKeyFilled('r_text', $data)) {
            throw new MissingPostParameterException();
        }
        if (!$this->isArrayKeyFilled('r_ident', $data)) {
            throw new InvalidArgumentException();
        }

        $contextualHelp = [];
        $contextualHelp['text'] = $data['r_text'];

        return $this->helpService->updateHelp($data['r_ident'], $contextualHelp);
    }

    /**
     * @param array $data
     *
     * @return bool|string
     *
     * @throws MissingPostParameterException
     * @throws Exception
     */
    public function createContextualHelp($data)
    {
        if (!$this->isArrayKeyFilled('r_key', $data) || !$this->isArrayKeyFilled('r_text', $data)) {
            throw new MissingPostParameterException();
        }

        $contextualHelp = [];
        $contextualHelp['key'] = $data['r_key'];
        $contextualHelp['text'] = $data['r_text'];

        return $this->helpService->createHelp($contextualHelp);
    }

    /**
     * Returns true if $key exists in $array and has a not empty string.
     *
     * @return bool
     */
    private function isArrayKeyFilled(string $key, array $array)
    {
        return array_key_exists($key, $array)
            && null !== $array[$key]
            && '' !== trim((string) $array[$key]);
    }

    /**
     * Get contextualHelp by Id.
     *
     * @return array|ContextualHelp|null
     *
     * @throws Exception
     */
    public function getHelp(string $id)
    {
        return $this->helpService->getHelp($id);
    }

    /**
     * Get all help items that are not gis layer related.
     *
     * @return ContextualHelp[]|null
     *
     * @throws Exception
     */
    public function getHelpNonGisLayer(): ?array
    {
        return $this->helpService->getHelpAllNonGisLayer();
    }
}
