<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Closure;
use demosplan\DemosPlanCoreBundle\Entity;
use demosplan\DemosPlanCoreBundle\Entity\Category;
use demosplan\DemosPlanCoreBundle\Entity\GlobalContent;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\ContentRepository;
use demosplan\DemosPlanCoreBundle\Repository\SettingRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Exception;
use InvalidArgumentException;
use ReflectionException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ContentService extends CoreService
{
    /**
     * Used as one of the keys in {@link Setting}.
     */
    public const LAYER_GROUPS_ALTERNATE_VISIBILITY = 'layerGroupsAlternateVisibility';

    public function __construct(
        private readonly ContentRepository $contentRepository,
        private readonly DateHelper $dateHelper,
        // @improve T13447
        private readonly EntityHelper $entityHelper,
        private readonly ManualListSorter $manualListSorter,
        private readonly SettingRepository $settingRepository,
        private readonly CustomerService $customerService,
    ) {
    }

    /**
     * Ruft alle Inhalte vom Typ 'news' ab
     * Die Inhalte müssen freigeschaltet sein (enable = true).
     *
     * @param int|null $limit
     *
     * @throws ReflectionException
     */
    public function getContentList(User $user, $limit = null): array
    {
        $roles = $user->isPublicUser()
            ? [Role::GUEST]
            : $user->getRoles();

        $globalContentEntries = $this->contentRepository->getNewsListByRoles($roles, $this->customerService->getCurrentCustomer());

        // Legacy Arrays
        // @improve T13447
        $result = array_map($this->convertToLegacy(...), $globalContentEntries);
        $sorted = $this->manualListSorter->orderByManualListSort('global:news', 'global', 'content:news', $result, $this->customerService->getCurrentCustomer());
        $result = $sorted['list'];
        // Is a limit given?
        if (isset($limit) && 0 < $limit) {
            // shorten the list of entries to the given limit
            $result = array_slice($result, 0, $limit);
        }

        return $result;
    }

    /**
     * Ruft alle Inhalte vom Typ 'news' ab
     * Die Inhalte müssen nicht freigeschaltet sein.
     *
     * @param string $categoryName
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getContentAdminList($categoryName = null): array
    {
        // @improve T12886
        $category = $this->getCategoryByName($categoryName ?? 'news');
        $globalContentEntries = $category->getGlobalContentsByCustomer($this->customerService->getCurrentCustomer());

        // Legacy Arrays
        $result = array_map($this->convertToLegacy(...), $globalContentEntries);
        $sorted = $this->manualListSorter->orderByManualListSort('global:news', 'global', 'content:news', $result, $this->customerService->getCurrentCustomer());

        return $sorted['list'];
    }

    /**
     * @param string $name
     *
     * @return Category|null
     */
    public function getCategoryByName($name)
    {
        return $this->contentRepository->getCategoryByName($name);
    }

    // @improve T13447

    /**
     * Ruft einen einzelnen Beitrag auf.
     *
     * @param string $ident
     *
     * @return array
     *
     * @throws Exception
     */
    public function getSingleContent($ident)
    {
        try {
            $singleGlobalContent = $this->contentRepository->get($ident);
            if (!$singleGlobalContent instanceof GlobalContent) {
                $message = 'No Content could be fetched for id: '.$ident;
                throw new InvalidArgumentException($message);
            }
            if ($this->customerService->getCurrentCustomer()->getId() !== $singleGlobalContent->getCustomer()->getId()) {
                throw new CustomerNotFoundException('Content does not belong to current customer');
            }

            return $this->convertToLegacy($singleGlobalContent);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines GlobalContentEntry: ', [$e]);
            throw $e;
        }
    }

    /**
     * Fügt einen globalen Beitrag hinzu.
     *
     * @param array $data
     *
     * @return array|null
     *
     * @throws Exception
     */
    public function addContent($data)
    {
        try {
            // add current customer to $data
            $data['customer'] = $this->customerService->getCurrentCustomer();
            $singleGlobalContent = $this->contentRepository->add($data);

            // convert to Legacy Array
            return $this->convertToLegacy($singleGlobalContent);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Anlegen eines GlobalContents: ', [$e]);
            throw $e;
        }
    }

    /**
     * Speichert die manuelle Listensortierung.
     *
     * @param string $type    Der Bezug unter dem die manuelle Sortierung gespeichert wurde. z.B. orga:{ident} oder user:{ident} / ident = ID ohne Klammer
     * @param string $context
     * @param string $sortIds
     *                        (Komma separierte Liste) / leer zum löschen
     *
     * @throws Exception
     */
    public function setManualSortForGlobalContent($context, $sortIds, $type): bool
    {
        $currentCustomer = $this->customerService->getCurrentCustomer();
        $sortIds = str_replace(' ', '', $sortIds);
        $data = [
            'ident'     => 'global',
            'context'   => $context,
            'namespace' => 'content:'.$type,
            'sortIdent' => $sortIds,
            'customer'  => $currentCustomer,
        ];

        return $this->manualListSorter->setManualSort($data['context'], $data);
    }

    /**
     * Update eines globalen Beitrages.
     *
     * @param array $data
     *
     * @throws Exception
     */
    public function updateContent($data): array
    {
        try {
            if (!isset($data['ident'])) {
                throw new InvalidArgumentException('Ident is missing');
            }
            $singleGlobalContent = $this->contentRepository->update($data['ident'], $data);

            return $this->convertToLegacy($singleGlobalContent);
        } catch (Exception $e) {
            $this->logger->warning('Update SingleGlobalContent failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get all global Settings.
     *
     * @throws Exception
     */
    public function getAllSettings()
    {
        try {
            $settings = $this->settingRepository->getAllSettings();

            // Expected return value
            $result = [];

            // if there are no entries, return empty array
            if (0 === count($settings)) {
                return $result;
            }
            // transform each entry to the expected array format
            foreach ($settings as $setting) {
                $setting = $this->entityHelper->toArray($setting);
                $setting['created'] = $this->dateHelper->convertDateToString($setting['created']);
                $setting['modified'] = $this->dateHelper->convertDateToString($setting['modified']);
                $result[] = $setting;
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('GetSettingList failed, Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Returns all Settings, related to a specific procedure.
     * This method does not return any ProcedureSettings!
     *
     * @param string $procedureId - identified related procedure
     *
     * @return Setting[] - settings, of given procedure
     */
    public function getSettingsByProcedureId($procedureId)
    {
        $settingsAsArray = [];
        $settings = $this->settingRepository->getSettingsByProcedureId($procedureId);
        $settings = collect($settings);

        // casting string value of Settings into boolean
        foreach ($settings as $setting) {
            if ('true' === $setting->getContent()) {
                $settingsAsArray[$setting->getKey()] = true;
            }
        }

        return $settingsAsArray;
    }

    /**
     * Get Settings for a given key.
     *
     * @param string $key
     * @param bool   $legacy
     *
     * @return Setting[]|array|null
     *
     * @throws Exception
     */
    public function getSettings($key, ?SettingsFilter $filter = null, $legacy = true)
    {
        try {
            // Wurde ein Filter übergeben
            if (null === $filter) {
                $settings = $this->settingRepository->get($key);
            } else {
                $settings = $this->settingRepository->getSettingsByKeyAndSetting($key, $filter->asArray());
            }

            // Expected return value
            $result = [];

            // if there are no entries, return empty array
            if (is_null($settings) || 0 === count($settings)) {
                return $result;
            }
            if (false === $legacy) {
                return $settings;
            }
            // transform each entry to the expected array format
            foreach ($settings as $setting) {
                $setting = $this->entityHelper->toArray($setting);
                $setting['created'] = $this->dateHelper->convertDateToString($setting['created']);
                $setting['modified'] = $this->dateHelper->convertDateToString($setting['modified']);
                $result[] = $setting;
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('GetSettingByKey failed, Key: {key} ', ['key' => $key, $e]);
            throw $e;
        }
    }

    /**
     * Get SettingsContent for a given key.
     *
     * @param string $key
     *
     * @return mixed (array/string)
     *
     * @throws Exception
     */
    public function getSettingContent($key)
    {
        try {
            $settings = $this->settingRepository->get($key);

            // Expected return value
            $result = [];

            // if there are no entries, return empty array
            if (0 === count($settings)) {
                return $result;
            }
            // transform each entry to the expected array format
            foreach ($settings as $setting) {
                $setting = $this->entityHelper->toArray($setting);
                $setting['created'] = $this->dateHelper->convertDateToString($setting['created']);
                $setting['modified'] = $this->dateHelper->convertDateToString($setting['modified']);
                $result[] = $setting;
            }

            if (is_array($result) && 1 === count($result)) {
                return $result[0]['content'] ?? '';
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('GetSettingByKey failed, Key: {key} ', ['key' => $key, $e]);
            throw $e;
        }
    }

    /**
     * This method will set the given settings in $fieldCompletionsToEnable to 'true', if there are a part of
     * the allowed $fieldCompletions. This will avoid undefined settings in the DB.
     * The other settings in $fieldCompletions will set to false.
     *
     * @param string   $procedureId              - identified the related Procedure
     * @param string[] $fieldCompletionsToEnable - settings which will be set to true
     *
     * @return bool - true if all allowed $fieldCompletions are set (to true or false), otherwise false
     */
    public function setProcedureFieldCompletions($procedureId, $fieldCompletionsToEnable)
    {
        $numberOfSuccessfulUpdated = 0;
        // allowed fields to mark as complete:
        $fieldCompletions = collect([
            'internalComplete',
            'phaseInternalComplete',
            'phaseExternalComplete',
            'nameUrlComplete',
            'infoComplete',
            'locationComplete',
            'additionalComplete',
            'exportSettingsComplete',
        ]);

        // convert array to collection:
        if (is_array($fieldCompletionsToEnable)) {
            $fieldCompletionsToEnable = collect($fieldCompletionsToEnable);
        }

        foreach ($fieldCompletions as $key) {
            $value = $fieldCompletionsToEnable->contains($key) ? 'true' : 'false';
            $this->putSetting($key, ['procedureId' => $procedureId, 'content' => $value]);
            ++$numberOfSuccessfulUpdated;
        }

        return $numberOfSuccessfulUpdated === count($fieldCompletions);
    }

    /**
     * Saving of Setting(s), documented within the code below.
     *
     * @param string $key
     *
     * @throws Exception
     */
    public function setSetting($key, array $data): Setting
    {
        $settingExists = false;
        $putData = [];
        /*
         * Hier müssen alle Anwändungsfälle, in denen Settings genutzt werden explizit
         * aufgeführt und dokumentiert werden, was die Settings tun und wie sie
         * genutzt werden müssen
         */
        switch ($key) {
            /*
             * Globale Sachdatenabfragenurl. Wird Nutzer- und Verfahrensunabhängig gesetzt
             */
            case 'globalFeatureInfoUrl':
                $putData = [
                    'content' => $data['content'],
                ];
                $settingExists = true;
                break;
                /*
                 * Bestimmt, ob die globale Sachdatenabfragenurl über einen Proxy
                 * angesprochen werden soll. Wird Nutzer- und Verfahrensunabhängig gesetzt
                 */
            case 'globalFeatureInfoUrlProxyEnabled':
                $putData = [
                    'content' => (string) $data['content'],
                ];
                $settingExists = true;
                break;
                /*
                 * Timestamp wann das Protokoll der Änderungen der Mastertöbliste von
                 * dem User das letzte mal gesehen wurde
                 */
            case 'reportMastertoebRead':
                $putData = [
                    'content' => (string) $data['content'],
                    'userId'  => $data['userId'],
                ];
                $settingExists = true;
                break;
                /*
                 * Notification-Flag, wenn Verfahrensträger(Organisation) per Email
                 * über neue Statements informiert werden will
                 */
            case 'emailNotificationNewStatement':
                $putData = [
                    'orgaId'  => $data['orgaId'],
                    'content' => $data['content'],
                ];
                $settingExists = true;
                break;
                /*
                 * Notification-Flag, when user wants to get notified by email
                 * about new released statements
                 */
            case 'emailNotificationReleasedStatement':
                $putData = [
                    'userId'  => $data['userId'],
                    'content' => $data['content'],
                ];
                $settingExists = true;
                break;
                /*
                 * Notification-Flag, wenn Institution über bald endende Beteiligungsphasen informiert werden will
                 */
            case 'emailNotificationEndingPhase':
                /*
                 * Which kind of statement submit process is used for this organisation
                 */
            case 'submissionType':
                $putData = [
                    'orgaId'  => $data['orgaId'],
                    'content' => $data['content'],
                ];
                $settingExists = true;
                break;
                /*
                 * Flag, dass das Planungsbüro und die interne Notiz gesetzt wurde
                 */
            case 'internalComplete':
                /*
                 * Flag, dass der Verfahrensschritt des Verfahrens als "erledigt" markiert wurde.
                 */
            case 'phaseInternalComplete':
                /*
                 * Flag, dass der öffentliche Verfahrensschritt des Verfahrens als "erledigt" markiert wurde.
                 */
            case 'phaseExternalComplete':
                /*
                 * Flag, dass der Name und die Web-Adresse des Verfahrens als "erledigt" markiert wurde.
                 */
            case 'nameUrlComplete':
                /*
                 * Flag, dass die Informationen zum Verfahren des Verfahrens als "erledigt" markiert wurde.
                 */
            case 'infoComplete':
                /*
                 * Flag, dass das die Verortung des Verfahrens als "erledigt" markiert wurde.
                 */
            case 'locationComplete':
                /*
                 * Flag, dass das die erweiterten Einstellungen zum Verfahren als "erledigt" markiert wurden.
                 */
            case 'additionalComplete':
                $putData = [
                    'procedureId' => $data['procedureId'],
                    'content'     => $data['content'],
                ];
                $settingExists = true;
                break;
                /*
                 * Flag, dass das das Verfahren noch durch den MaintenanceService verortet werden muss
                 */
            case 'needLocalization':
                $putData = [
                    'procedureId' => $data['procedureId'],
                    'content'     => '',
                ];
                $settingExists = true;
                break;
                /*
                 * Flag, dass das pro Verfahren und User speichert ob sich beteiligt wurde.
                 * User setzen diese flag selbstständig.
                 */
            case 'markedParticipated':
                $putData = [
                    'procedureId' => $data['procedureId'],
                    'userId'      => $data['userId'],
                    'content'     => '',
                ];
                $settingExists = true;
                break;
                /*
                 * Flag, dass sich Kartenlayer-Kategorien in dem Verfahren gegeseitig ausblenden.
                 */
            case 'layerGroupsAlternateVisibility':
                $putData = [
                    'procedureId' => $data['procedureId'],
                    'content'     => $data['content'],
                ];
                $settingExists = true;
                break;
                /*
                 * User want to change his E-Mail-Address.
                 * Before actually changing email of user, the new E-Mail-Address has to be verified by the user.
                 * Therefore, the new E-Mail-Address will to be stored as setting, and changed if verified by user.
                 */
            case 'changeEmail':
                $putData = [
                    'userId'  => $data['userId'],
                    'content' => $data['email'],
                ];
                $settingExists = true;
                break;
                /*
                 * Globale Sachdatenabfragenurl. Wird Nutzer- und Verfahrensunabhängig gesetzt
                 */
            case 'globalFeatureInfoUrlFhhnet':
                $putData = [
                    'content' => $data['content'],
                ];
                $settingExists = true;

                break;
                /*
                 * Bestimmt, ob die globale Sachdatenabfragenurl über einen Proxy
                 * angesprochen werden soll. Wird Nutzer- und Verfahrensunabhängig gesetzt
                 */
            case 'globalFeatureInfoUrlFhhnetProxyEnabled':
                $putData = [
                    'content' => (string) $data['content'],
                ];
                $settingExists = true;
                break;

            default:
                break;
        }

        if (true === $settingExists) {
            return $this->putSetting($key, $putData);
        }

        $this->logger->warning('Versuch, eine nicht definierte Setting zu speichern: ', [$key]);
        throw new Exception("Versuch, eine nicht definierte Setting zu speichern: $key");
    }

    /**
     * Delete a settings entry with a given $id.
     *
     * @param string $settingsId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteSetting($settingsId)
    {
        try {
            $successful = $this->settingRepository->delete($settingsId);

            if ($successful) {
                $this->getLogger()->info('Successfully deleted setting.');
            } else {
                $this->getLogger()->error('Setting could not be deleted.');
            }

            return $successful;
        } catch (Exception $e) {
            $this->logger->error('DeleteSettingByIdfailed, Id: {id} ', ['id' => $settingsId, $e]);
            throw $e;
        }
    }

    /**
     * Speichern eines Settings.
     *
     * @param string $key
     * @param array  $data
     *
     * @throws HttpException
     * @throws OptimisticLockException
     */
    protected function putSetting($key, $data): Setting
    {
        // Exception wird von aufrufender Methode abgefangen (putSetting())
        return $this->settingRepository->update($key, $data);
    }

    /**
     * Convert Doctrine Result into legacyformat as pure array without Classes and right names.
     *
     * @param GlobalContent|null $singleGlobalContent
     *
     * @throws ReflectionException
     */
    protected function convertToLegacy($singleGlobalContent): array
    {
        // returnValue, if globalContent doesn't exist
        if (!$singleGlobalContent instanceof GlobalContent) {
            // Legacy returnvalues if no globalContent found
            return [];
        }

        // convert each role and each category entity into an array of fields
        $toArrayClosure = Closure::fromCallable($this->entityHelper->toArray(...));
        $rolesAsArray = $singleGlobalContent->getRoles()->map($toArrayClosure)->getValues();
        $categoriesAsArray = $singleGlobalContent->getCategories()->map($toArrayClosure)->getValues();

        // Transform News into an array
        $singleGlobalContent = $this->entityHelper->toArray($singleGlobalContent);
        $singleGlobalContent['roles'] = $rolesAsArray;
        $singleGlobalContent['categories'] = $categoriesAsArray;

        // won't return null as $singleGlobalContent is not null
        return $this->dateHelper->convertDatesToLegacy($singleGlobalContent);
    }

    /**
     * Delete all Settings of the given user.
     *
     * @param string $userId identifies the User, whose settings will be deleted
     *
     * @return bool - true, if settings was successful deleted, otherwise false
     */
    public function removeSettingsOfUser($userId)
    {
        try {
            $settings = $this->getSettingsOfUser($userId);

            foreach ($settings as $setting) {
                $this->settingRepository->delete($setting);
            }
        } catch (Exception) {
            $this->logger->warning('Remove settings of user failed');

            return false;
        }

        return true;
    }

    /**
     * Delete all Settings of the given organisation.
     *
     * @param string $organisationId - Identifies the organisation, whose settings will be deleted
     *
     * @return bool - true, if settings was successful deleted, otherwise false
     */
    public function deleteSettingsOfOrga($organisationId)
    {
        try {
            $settings = $this->settingRepository->getSettingByOrganisationId($organisationId);
            foreach ($settings as $setting) {
                $this->settingRepository->delete($setting);
            }
        } catch (Exception) {
            $this->logger->warning('Remove settings of organisation failed');

            return false;
        }

        return true;
    }

    public function createEmptySetting(Procedure $procedure, string $key): Setting
    {
        $setting = new Setting();
        $setting->setProcedure($procedure);
        $setting->setKey($key);
        $this->settingRepository->persistEntities([$setting]);

        return $setting;
    }

    /**
     * Returns Settings of a specific user.
     *
     * @param string $userId identifies the User, whose settings will be returned
     *
     * @return Entity\Setting[]
     */
    public function getSettingsOfUser($userId)
    {
        return $this->settingRepository->getSettingByUserId($userId);
    }
}
