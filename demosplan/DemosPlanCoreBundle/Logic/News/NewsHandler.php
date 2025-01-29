<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\News;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\GlobalContent;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ArrayHelper;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\FlashMessageHandler;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Repository\ContentRepository;
use demosplan\DemosPlanCoreBundle\Repository\NewsRepository;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewsHandler extends CoreHandler
{
    /** @var int Do not increase over 65535 as this is the maximum in the database */
    final public const NEWS_DESCRIPTION_MAX_LENGTH = 65535;
    /** @var int Do not increase over 65535 as this is the maximum in the database */
    final public const NEWS_TEXT_MAX_LENGTH = 65535;

    /** @var ContentService */
    protected $contentService;
    /** @var ProcedureNewsService */
    protected $procedureNewsService;
    /** @var GlobalNewsHandler */
    protected $globalNewsHandler;

    /** @var ManagerRegistry */
    protected $doctrine;

    /** @var Permissions */
    protected $permissions;

    public function __construct(
        private readonly ArrayHelper $arrayHelper,
        ContentService $contentService,
        private readonly FlashMessageHandler $flashMessageHandler,
        GlobalNewsHandler $globalNewsHandler,
        ManagerRegistry $doctrine,
        MessageBagInterface $messageBag,
        PermissionsInterface $permissions,
        ProcedureNewsService $procedureNewsService,
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct($messageBag);
        $this->contentService = $contentService;
        $this->doctrine = $doctrine;
        $this->globalNewsHandler = $globalNewsHandler;
        $this->permissions = $permissions;
        $this->procedureNewsService = $procedureNewsService;
    }

    /**
     * Validate required fields.
     *
     * @param array $data
     */
    public function validateNews($data): array
    {
        $errors = [];

        if (!array_key_exists('r_enable', $data) || '' === trim((string) $data['r_enable'])) {
            $errors[] = $this->createMandatoryErrorMessage('status');
        }

        if (!array_key_exists('r_title', $data) || '' === trim((string) $data['r_title'])) {
            $errors[] = $this->createMandatoryErrorMessage('heading');
        }

        if (!array_key_exists('r_description', $data) || '' === trim((string) $data['r_description'])) {
            $errors[] = $this->createMandatoryErrorMessage('teaser');
        }

        if (!array_key_exists('r_group_code', $data) || 0 === (is_countable($data['r_group_code']) ? count($data['r_group_code']) : 0)) {
            $errors[] = $this->createMandatoryErrorMessage('visibility');
        }

        if (array_key_exists('r_description', $data)) {
            $description = $data['r_description'];
            if (!is_string($description)) {
                throw new InvalidArgumentException('If provided r_description must be a string');
            }
            if (self::NEWS_DESCRIPTION_MAX_LENGTH < strlen($description)) {
                $errors[] = [
                    'type'    => 'error',
                    'message' => $this->translator->trans('error.news.description.toolong'),
                ];
            }
        }

        if (array_key_exists('r_text', $data)) {
            $text = $data['r_text'];
            if (!is_string($text)) {
                throw new InvalidArgumentException('If provided r_text must be a string');
            }
            if (self::NEWS_TEXT_MAX_LENGTH < strlen($text)) {
                $errors[] = [
                    'type'    => 'error',
                    'message' => $this->translator->trans('error.news.text.toolong'),
                ];
            }
        }

        // @improve T16723: reduce duplication of validation of incoming date:
        if (
            array_key_exists('r_designatedSwitchDate', $data)
            && array_key_exists('r_determinedToSwitch', $data)
            && '1' === $data['r_determinedToSwitch']) {
            if ('' === $data['r_designatedSwitchDate']) {
                $errors[] = [
                    'type'    => 'error',
                    'message' => $this->translator->trans('error.designated.date.not.set'),
                ];
            } else {
                $designatedSwitchDate = Carbon::createFromFormat('d.m.Y', $data['r_designatedSwitchDate']);
                if (!$designatedSwitchDate->isFuture()) {
                    $errors[] = [
                        'type'    => 'error',
                        'message' => $this->translator->trans('error.designated.date.in.past'),
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * @param string|null $procedure
     * @param array       $data
     */
    public function copyDataFields($procedure, $data): array
    {
        $news = [];
        $keyMapping = ['title', 'description', 'text', 'pictitle', 'pdftitle'];

        foreach ($keyMapping as $key) {
            $news = $this->arrayHelper->addToArrayIfKeyExists($news, $data, $key);
        }

        if (array_key_exists('r_enable', $data)) {
            $news['enabled'] = '1' == $data['r_enable'];
        }

        if (array_key_exists('delete_pdf', $data)) {
            $news['pdf'] = '';
            $news['pdftitle'] = '';
        } elseif (array_key_exists('r_pdf', $data)) {
            if (null != $data['r_pdf']) {
                $news['pdf'] = $data['r_pdf'];
            }
        }

        // Sind es Verfahrensnews?
        // Dann sollen Fachplaner und Planugnsbüros die News immer sehen
        $news['group_code'] = null !== $procedure ? [Role::GLAUTH] : [];
        if (array_key_exists('r_group_code', $data)) {
            $news['group_code'] = array_merge($news['group_code'], $data['r_group_code']);
        }

        if (array_key_exists('r_category_name', $data)) {
            $news['category_name'] = [$data['r_category_name']];
            // get categoryId to make them available in refactored ContentRepository::add()
            $category = $this->contentService->getCategoryByName($data['r_category_name']);
            $news['category_id'] = $category->getId();
        }

        // @improve T16723: reduce duplication of validation of incoming date:
        $news['determinedToSwitch'] = false;
        if ($this->permissions->hasPermission('feature_auto_switch_procedure_news')) {
            $news = $this->arrayHelper->addToArrayIfKeyExists($news, $data, 'determinedToSwitch');
            // reset designated switch date when auto switch is unselected
            $news['designatedSwitchDate'] = null;
            if ('1' === $news['determinedToSwitch']
                && array_key_exists('r_designatedSwitchDate', $data)
                && '' !== $data['r_designatedSwitchDate']
            ) {
                $news = $this->arrayHelper->addToArrayIfKeyExists($news, $data, 'designatedState');
                $designatedSwitchDate = Carbon::createFromFormat('d.m.Y', $data['r_designatedSwitchDate']);
                if ($designatedSwitchDate->isFuture()) {
                    $news['designatedSwitchDate'] = $designatedSwitchDate;
                }
            }
        }

        return $news;
    }

    /**
     * @return array|bool|null
     *
     * @throws Exception
     */
    public function handleNewGlobalNews(array $data)
    {
        $noProblemsCheckResult = $this->checkNewNewsForNoProblems($data);
        if (true !== $noProblemsCheckResult) {
            return $noProblemsCheckResult;
        }

        $news = $this->copyDataFieldsForNewNews(null, $data);

        return $this->globalNewsHandler->addNews($news);
    }

    /**
     * @return array|bool|null
     *
     * @throws Exception
     */
    public function handleNewProcedureNews(string $procedureId, array $data)
    {
        $problemsCheckResult = $this->checkNewNewsForNoProblems($data);
        if (true !== $problemsCheckResult) {
            return $problemsCheckResult;
        }

        $news = $this->copyDataFieldsForNewNews($procedureId, $data);
        $news['pId'] = $procedureId;

        return $this->procedureNewsService->addNews($news);
    }

    /**
     * @return array|bool
     */
    public function checkNewNewsForNoProblems(array $data)
    {
        if (!array_key_exists('action', $data)) {
            return false;
        }

        if ('newsnew' !== $data['action']) {
            return false;
        }

        $errors = $this->validateNews($data);

        if (0 < count($errors)) {
            $this->flashMessageHandler->setFlashMessages($errors);

            return [
                'fieldWarnings' => $errors,
            ];
        }

        return true;
    }

    /**
     * @param string|null $procedureId
     */
    protected function copyDataFieldsForNewNews($procedureId, array $data): array
    {
        $news = $this->copyDataFields($procedureId, $data);

        if (array_key_exists('r_picture', $data) && null != $data['r_picture']) {
            $news['picture'] = $data['r_picture'];
        }

        return $news;
    }

    /**
     * @param string|null                            $procedure
     * @param array                                  $data
     * @param GlobalNewsHandler|ProcedureNewsService $updater
     *
     * @return array|false never null
     *
     * @throws Exception
     */
    public function handleEditNews($procedure, $data, $updater)
    {
        if (!array_key_exists('action', $data)) {
            return false;
        }

        if ('newsedit' !== $data['action']) {
            return false;
        }

        $errors = $this->validateNews($data);

        if (0 < count($errors)) {
            $this->flashMessageHandler->setFlashMessages($errors);

            return [
                'fieldWarnings' => $errors,
            ];
        }

        $news = $this->copyDataFields($procedure, $data);

        // Array auf
        if (array_key_exists('r_ident', $data)) {
            $news['ident'] = $data['r_ident'];
        }

        if (array_key_exists('delete_picture', $data)) {
            $news['picture'] = '';
            $news['pictitle'] = '';
        } elseif (array_key_exists('r_picture', $data)) {
            if (null != $data['r_picture']) {
                $news['picture'] = $data['r_picture'];
            }
        }

        return $updater->updateNews($news);
    }

    /**
     * Will update the enabled state of all news or globalContent entities which IDs are given
     * in the first parameter.
     * <p>
     * News entities which IDs are in the second parameter will be enabled. News entities which
     * IDs are not in the second parameter will be disabled. IDs in the second parameter that are
     * not in the first parameter will be ignored.
     * <p>
     * In case of failure no changes to the database are made at all.
     * <p>
     * Either GlobalContent entity IDs or News entity IDs must be submitted. No support for mixed IDs.
     *
     * @param string[] $allAffectedIds the IDs of all entities that shall be updated
     * @param string[] $enabledIdsOnly the IDs of the entities in the first parameter that shall be enabled
     * @param bool     $isGlobalNews   Determines if the type of the content is a global news (true) or a procedure news (false)
     *
     * @return bool true if all the new states were successfully saved, false otherwise
     */
    public function changeGlobalContentOrNewsEnabledProperties($allAffectedIds, $enabledIdsOnly, bool $isGlobalNews)
    {
        try {
            /** @var NewsRepository|ContentRepository $repository */
            $repository = $this->doctrine->getRepository(
                $isGlobalNews
                    ? GlobalContent::class
                    : News::class
            );
            /** @var GlobalContent[]|News[] $entities */
            // NOTE: findByIdent is automatically generated by doctrine using the News|GlobalContent ident property
            $entities = $repository->findByIdent($allAffectedIds);
            foreach ($entities as $entity) {
                $entity->setEnabled(in_array($entity->getId(), $enabledIdsOnly, true));
            }
            $repository->updateObjects($entities);

            return true;
        } catch (ORMException $e) {
            $this->getLogger()->error('failed to update enabled state of entities', ['exception' => $e]);

            return false;
        }
    }

    /**
     * Generiere einen Eintrag für die notwendigen Felder.
     *
     * @param string $translatorLabel
     */
    public function createMandatoryErrorMessage($translatorLabel): array
    {
        return [
            'type'    => 'error',
            'message' => $this->translator->trans(
                'error.mandatoryfield',
                [
                    'name' => $this->translator->trans($translatorLabel),
                ]
            ),
        ];
    }
}
