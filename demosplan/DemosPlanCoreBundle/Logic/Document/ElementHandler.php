<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Document;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Logic\ArrayHelper;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\FlashMessageHandler;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;

class ElementHandler extends CoreHandler
{
    /**
     * Die rekursive Funktion ist leichter mit einem Klassenspeicher umzusetzen.
     *
     * @var array
     */
    protected $elementsWitchChildrenFlat;

    public function __construct(private readonly ArrayHelper $arrayHelper, private readonly ElementsService $elementService, private readonly FlashMessageHandler $flashMessageHandler, MessageBagInterface $messageBag, private readonly PermissionsInterface $permissions, private readonly TranslatorInterface $translator)
    {
        parent::__construct($messageBag);
    }

    /**
     * @param string $elementId
     *
     * @return Elements|null
     *
     * @throws Exception
     */
    public function getElement($elementId)
    {
        return $this->elementService->getElementObject($elementId);
    }

    /**
     * Returns the Elements in the procedure with the given enabled status.
     *
     * @param string $procedureId
     *
     * @return Elements[]
     */
    public function getElementsByEnabledStatus($procedureId, bool $enabled): array
    {
        return $this->elementService->getElementsByEnabledStatus($procedureId, $enabled);
    }

    /**
     * Returns the ids for the Elements in the procedure with he given enabled status.
     *
     * @return string[]
     */
    public function getElementIdsByEnabledStatus(string $procedureId, bool $enabled): array
    {
        $elements = $this->elementService->getElementsByEnabledStatus($procedureId, $enabled);

        return array_map(
            static fn (Elements $element) => $element->getId(),
            $elements
        );
    }

    /**
     * Given an array of Element ids and a filename, builds a full path with the elements' titles (following the
     * array's order) + $fileName.
     *
     * @param string[] $elementIds
     */
    public function getFileNamedPath(array $elementIds, string $fileName): string
    {
        $elements = $this->elementService->getElementsByIds($elementIds, ['order' => 'asc']);
        $fileNamedPathArray = array_map(fn (Elements $element) => $element->getTitle(), $elements);

        return implode('/', $fileNamedPathArray).'/'.$fileName;
    }

    /**
     * @param string $procedure
     * @param array  $data
     *
     * @return Elements|array the entity on success and the array in case of an error
     *
     * @throws Exception
     */
    public function administrationElementEditHandler($procedure, $data)
    {
        $element = [];
        $mandatoryErrors = [];

        if (!array_key_exists('r_action', $data)) {
            return false;
        }

        if ('elementedit' !== $data['r_action']) {
            return false;
        }

        $element = $this->arrayHelper->addToArrayIfKeyExists($element, $data, 'ident');
        $element = $this->arrayHelper->addToArrayIfKeyExists($element, $data, 'text');
        $element = $this->arrayHelper->addToArrayIfKeyExists($element, $data, 'title');
        $element = $this->arrayHelper->addToArrayIfKeyExists($element, $data, 'file');
        $element = $this->arrayHelper->addToArrayIfKeyExists($element, $data, 'group');
        $element = $this->arrayHelper->addToArrayIfKeyExists($element, $data, 'permission');

        $validPermissionStrings = [
            '',
            'feature_admin_element_public_access',
            'feature_admin_element_invitable_institution_access',
        ];
        if (array_key_exists('permission', $element)
            && !in_array($element['permission'], $validPermissionStrings, true)) {
            // InvalidDataException ?!
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator->trans('error.invalid.permission'),
            ];
        }

        if (!array_key_exists('title', $element) || '' === trim((string) $element['title'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator->trans('error.mandatoryfield', ['name' => 'title']),
            ];
        }

        if ($this->permissions->hasPermission('feature_auto_switch_element_state')) {
            if (array_key_exists('r_autoSwitchState', $data)) {
                if (array_key_exists('r_designatedSwitchDate', $data)) {
                    $hasMandatoryAutoSwitchError = false;
                    $designatedSwitchDate = Carbon::createFromFormat(Carbon::ATOM, $data['r_designatedSwitchDate']);

                    if (!$designatedSwitchDate->isFuture()) {
                        $mandatoryErrors[] = [
                            'type'    => 'error',
                            'message' => $this->translator->trans('error.designated.switchdate.in.past'),
                        ];
                        $hasMandatoryAutoSwitchError = true;
                    }

                    if (!$hasMandatoryAutoSwitchError) {
                        $element['designatedSwitchDate'] = $designatedSwitchDate;
                    }
                }
            } else {
                // is autoSwitchState disabled, remove all Fields
                $element['designatedSwitchDate'] = null;
            }
        }

        if (0 < count($mandatoryErrors)) {
            $this->flashMessageHandler->setFlashMessages($mandatoryErrors);

            return ['mandatoryfieldwarning' => $mandatoryErrors];
        }

        // Überprüfe das Eingabefeld zu den Berechtigungen
        $this->adminAuthorisationsForElements($data);

        return $this->elementService->updateElementArray($element);
    }

    /**
     * Verarbeitet die Zuordnung von Organisationen zu Kategorien(Berechtigungen).
     */
    protected function adminAuthorisationsForElements(array $data)
    {
        $elementId = $data['r_ident'];
        $orgasForElement = [];
        // Hole alle Orgas, die bisher der Kategorie zugewiesen sind.
        $outputResult = $this->elementService->getElement($elementId);
        if (isset($outputResult['organisation'])) {
            foreach ($outputResult['organisation'] as $orga) {
                $orgasForElement[] = $orga['ident'];
            }
        }
        // wenn das Eingabefeld leer, dann lösche alle bisherigen Orgas
        if ((!array_key_exists('r_orga', $data)) && !empty($orgasForElement)) {
            $this->elementService->deleteAuthorisationOfOrga($elementId, $orgasForElement);
        }
        // wenn das Eingabefeld nicht leer, dann
        if (array_key_exists('r_orga', $data)) {
            // Überprüfe, ob Orgas zu den Berechtigungen dazugefügt wurden
            $orgasToAdd = array_diff($data['r_orga'], $orgasForElement);
            // Wenn ja, speicher sie ab
            if (is_array($orgasToAdd) && 0 < count($orgasToAdd)) {
                $this->elementService->addAuthorisationToOrga($elementId, $orgasToAdd);
            }
            // Überprüfe, ob einzelne Orgas aus den Berechtigungen gelöscht werden sollen
            $orgasToDelete = array_diff($orgasForElement, $data['r_orga']);
            // wenn ja, lösche sie
            if (is_array($orgasToDelete) && 0 < count($orgasToDelete)) {
                $this->elementService->deleteAuthorisationOfOrga($elementId, $orgasToDelete);
            }
        }
    }

    /**
     * neue Kategorie für Plandokumente erstellen.
     *
     * @param string $procedureId
     * @param array  $data
     *
     * @return array|false
     *
     * @throws Exception
     */
    public function administrationElementNewHandler($procedureId, $data)
    {
        $element = [];
        $mandatoryErrors = [];

        // Service braucht Action-Merkmal
        $data['action'] = 'elementnew';

        if (array_key_exists('r_text', $data)) {
            $element['text'] = $data['r_text'];
        }

        if (array_key_exists('r_title', $data)) {
            $element['title'] = $data['r_title'];
        }

        if (array_key_exists('r_parent', $data)) {
            $element['parent'] = $data['r_parent'];
        }

        if (array_key_exists('r_category', $data)) {
            $element['category'] = $data['r_category'];
        } else {
            $element['category'] = 'file';
        }

        $element['enabled'] = false;
        if (array_key_exists('r_publish_categories', $data)) {
            $element['enabled'] = $data['r_publish_categories'];
        }

        $element['pId'] = $procedureId;

        if ($this->permissions->hasPermission('feature_auto_switch_element_state')) {
            if (array_key_exists('r_autoSwitchState', $data)) {
                if (array_key_exists('r_designatedSwitchDate', $data)) {
                    // @improve T16723: reduce duplication of validation of incoming date:
                    $hasMandatoryAutoSwitchError = false;
                    $designatedSwitchDate = null;
                    if ('' === $data['r_designatedSwitchDate']) {
                        $mandatoryErrors[] = [
                            'type'    => 'error',
                            'message' => $this->translator->trans('error.designated.date.not.set'),
                        ];
                        $hasMandatoryAutoSwitchError = true;
                    } else {
                        $designatedSwitchDate = Carbon::createFromFormat(Carbon::ATOM, $data['r_designatedSwitchDate']);
                        if (!$designatedSwitchDate->isFuture()) {
                            $mandatoryErrors[] = [
                                'type'    => 'error',
                                'message' => $this->translator->trans('error.designated.switchdate.in.past'),
                            ];
                            $hasMandatoryAutoSwitchError = true;
                        }
                    }

                    if (!$hasMandatoryAutoSwitchError) {
                        $element['enabled'] = false; // in order to activate automatically: invert current state
                        $element['designatedSwitchDate'] = $designatedSwitchDate;
                    }
                }
            } else {
                // is autoSwitchState disabled, remove all Fields
                $element['designatedSwitchDate'] = null;
            }
        }

        if (0 < count($mandatoryErrors)) {
            $this->flashMessageHandler->setFlashMessages($mandatoryErrors);

            return ['mandatoryfieldwarning' => $mandatoryErrors];
        }

        $result = $this->elementService->addElement($element);
        $data['r_ident'] = $result['ident'];
        $this->adminAuthorisationsForElements($data);

        return $result;
    }

    /**
     * Kategorie löschen.
     *
     * @param array|string $idents
     */
    public function administrationElementDeleteHandler($idents)
    {
        return $this->elementService->deleteElement($idents);
    }

    /**
     * Verarbeitet alle Anfragen aus der Listenansicht.
     * Liefert das Elemente der Kategorie/Typs 'map' eines bestimmten Verfahrens.
     *
     * @param string $procedure
     *
     * @return Elements|null
     *
     * @throws Exception
     */
    public function mapHandler($procedure)
    {
        return $this->elementService->getMapElements($procedure);
    }

    /**
     * Das Feld 'file' wird aktualisiert.
     *
     * @param string $elementId
     * @param array  $data
     *
     * @return array|false
     *
     * @throws Exception
     */
    public function updateParagraphElementFile($elementId, $data)
    {
        $toUpdate = [];

        if (!array_key_exists('r_action', $data)) {
            return false;
        }

        if ('updateParagraphPDF' !== $data['r_action']) {
            return false;
        }

        if (array_key_exists('PDF', $data)) {
            if (isset($data['PDF'])) {
                $toUpdate['file'] = $data['PDF'];
            }
            // check, ob eine neue Datei hochgeladen werden soll
            if (array_key_exists('r_planDelete', $data) && array_key_exists('PDF', $data) && !isset($data['PDF'])) {
                $toUpdate['file'] = '';
            }
        }
        $toUpdate['ident'] = $elementId;

        return $this->elementService->updateElementArray($toUpdate);
    }
}
