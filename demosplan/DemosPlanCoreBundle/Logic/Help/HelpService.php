<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Help;

use demosplan\DemosPlanCoreBundle\Entity\Help\ContextualHelp;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\ContextualHelpRepository;
use Doctrine\ORM\NoResultException;
use Exception;
use InvalidArgumentException;

class HelpService extends CoreService
{
    public function __construct(private readonly ContextualHelpRepository $contextualHelpRepository)
    {
    }

    /**
     * Get all help items.
     *
     * @return ContextualHelp[]|null
     *
     * @throws Exception
     */
    public function getHelpAll()
    {
        try {
            return $this->contextualHelpRepository->getAllContextualHelp();
        } catch (Exception $e) {
            $this->logger->error('GetContextualHelpList failed', [$e]);
            throw $e;
        }
    }

    /**
     * Get all help items that are not gis layer related.
     *
     * @return ContextualHelp[]|null
     *
     * @throws Exception
     */
    public function getHelpAllNonGisLayer()
    {
        try {
            return $this->contextualHelpRepository->getNonGisLayerRelatedContextualHelp();
        } catch (Exception $e) {
            $this->logger->error('GetContextualHelpList failed', [$e]);
            throw $e;
        }
    }

    /**
     * Get Help by Id.
     *
     * @param string $id
     *
     * @return array|ContextualHelp|null
     *
     * @throws Exception
     */
    public function getHelp($id)
    {
        return $this->contextualHelpRepository->get($id);
    }

    /**
     * Get Help by key.
     *
     * @param string $key
     *
     * @return ContextualHelp|null
     *
     * @throws Exception
     */
    public function getHelpByKey($key)
    {
        return $this->contextualHelpRepository->getByKey($key);
    }

    /**
     * Update a help.
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws Exception
     */
    public function updateHelp($id, array $data)
    {
        try {
            $response = $this->contextualHelpRepository->update($id, $data);
            // Prüfe den Rückgabewert
            if (true === $response) {
                return true;
            } else {
                $this->logger->error('UpdateSingleContextualHelp failed, Ident: '.$id.'Message: null given, array expected.');
                throw new Exception();
            }
        } catch (NoResultException $e) {
            $this->logger->error('UpdateSingleContextualHelp failed, Ident: '.$id.' ExceptionMessage: ', [$e]);
            throw new InvalidArgumentException();
        } catch (Exception $e) {
            $type = $e::class;
            $this->logger->error('UpdateSingleContextualHelp failed Ident: '.$id.', ExceptionClass: '.$type.' ExceptionMessage: ', [$e]);
            throw $e;
        }
    }

    /**
     * Create a new help.
     *
     * @return string $id
     *
     * @throws Exception
     */
    public function createHelp(array $data)
    {
        try {
            $help = new ContextualHelp();
            if (array_key_exists('text', $data)) {
                $help->setText($data['text']);
            }
            if (array_key_exists('key', $data)) {
                $help->setKey($data['key']);
            }
            $this->contextualHelpRepository->addObject($help);

            return $help;
        } catch (Exception $e) {
            $this->getLogger()->error('Create ContextualHelp failed: ', [$e]);
            throw $e;
        }
    }

    /**
     * Deletes the ContextualHelp with the given Id.
     *
     * @param string $ident
     *
     * @throws Exception
     */
    public function deleteHelp($ident)
    {
        $repo = $this->contextualHelpRepository;
        $help = $repo->get($ident);
        $repo->delete($help);
    }
}
