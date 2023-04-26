<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Forum;

use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\Forum\ForumHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use Symfony\Contracts\Translation\TranslatorInterface;

class DemosPlanForumBaseController extends BaseController
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var ForumHandler
     */
    protected $forumHandler;

    /**
     * @var CurrentUserService
     */
    private $currentUser;

    public function __construct(CurrentUserService $currentUser, ForumHandler $forumHandler, TranslatorInterface $translator)
    {
        $this->currentUser = $currentUser;
        $this->forumHandler = $forumHandler;
        $this->translator = $translator;
    }

    /**
     * Generiere ein Sicherheitstoken.
     *
     * @return string
     */
    protected function generateToken()
    {
        return md5($this->currentUser->getUser()->getId().date('dmy'));
    }

    /**
     * Unterscheide die Dateien nach Bildern und anderen Dateien.
     *
     * @param array $files
     *
     * @return mixed
     */
    protected function generateImagesAndDocuments($files)
    {
        $images = [];
        $documents = [];

        foreach ($files as $file) {
            if (false != stripos($file, 'image')) {
                $images[] = $file;
            } else {
                $documents[] = $file;
            }
        }
        $result['images'] = $images;
        $result['documents'] = $documents;

        return $result;
    }

    protected function getReleasePhases()
    {
        return [
            'configuration'  => [
                'name' => 'Konfiguration',
                'key'  => 'configuration',
            ],
            'voting_online'  => [
                'name' => 'Bepunktung',
                'key'  => 'voting_online',
            ],
            'voting_offline' => [
                'name' => 'Sitzung vor Ort',
                'key'  => 'voting_offline',
            ],
            'closed'         => [
                'name' => 'Abgeschlossen',
                'key'  => 'closed',
            ],
        ];
    }

    public function getReleasePhasePermissions($releasePhase)
    {
        $permissionsForPhases = [
            'configuration'  => [
                'vote_online'     => false,
                'vote_offline'    => false,
                'new_threadEntry' => false,
            ],
            'voting_online'  => [
                'vote_online'     => true,
                'vote_offline'    => false,
                'new_threadEntry' => true,
            ],
            'voting_offline' => [
                'vote_online'     => false,
                'vote_offline'    => true,
                'new_threadEntry' => false,
            ],
            'closed'         => [
                'vote_online'     => false,
                'vote_offline'    => false,
                'new_threadEntry' => false,
            ],
        ];

        return $permissionsForPhases[$releasePhase];
    }
}
