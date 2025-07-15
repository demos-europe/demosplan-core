<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatementFilterHandler extends CoreHandler
{
    /** @var Permissions */
    protected $permissions;

    public function __construct(MessageBagInterface $messageBag, PermissionsInterface $permissions, private readonly TranslatorInterface $translator)
    {
        parent::__construct($messageBag);
        $this->permissions = $permissions;
    }

    /**
     * Get the translated label of a filter.
     *
     * @param string $filterName
     *
     * @return string
     */
    public function getTranslatedFilterLabel($filterName)
    {
        $labelMap = $this->getFilterLabelMap();

        return array_key_exists($filterName, $labelMap) ? $labelMap[$filterName] : $filterName;
    }

    /**
     * Get translated options of the Elasticsearch Filter options.
     *
     * @param string $filterName
     * @param array  $options
     *
     * @return array
     */
    public function getTranslatedFilterOptions($filterName, $options, string $filterType = '')
    {
        $options = collect($options)->map(
            static function (array $option) use ($filterType) {
                $option['type'] = $filterType;

                return $option;
            }
        )->values()->all();

        // handle special cases
        if ('phase' === $filterName) {
            return $this->getTranslatedPhaseOptions($options);
        }

        if ('publicStatement' === $filterName) {
            return $this->getTranslatedPublicStatementOptions($options);
        }

        if ('status' === $filterName) {
            return $this->getTranslatedStatusOptions($options);
        }

        $untranslatedFilterOptions = [
            'publicCheck',
            'fragments_status',
            'fragments_element',
        ];

        if (!in_array($filterName, $untranslatedFilterOptions)) {
            return $options;
        }

        $translator = $this->translator;

        foreach ($options as $key => $value) {
            $options[$key]['label'] = $translator->trans($value['label']);
        }
        // sort alphabetically
        $options = collect($options)->sortBy('label')->values()->all();

        return $options;
    }

    /**
     * Get translated options of the phase filter.
     *
     * @param array $options
     *
     * @return array
     */
    protected function getTranslatedPhaseOptions($options)
    {
        $translator = $this->translator;
        $internalPhases = $this->getDemosplanConfig()->getInternalPhasesAssoc();
        $externalPhases = $this->getDemosplanConfig()->getExternalPhasesAssoc();

        foreach ($options as $key => $phase) {
            $transKey = 'filter.phase.'.$phase['value'];
            $filterTrans = $translator->trans($transKey);
            if ($filterTrans != $transKey) {
                $options[$key]['label'] = $filterTrans;
                continue;
            }
            // Wenn es keine besonderen Übersetzungen gibt, nimm die konfigurierten Bezeichner
            if (array_key_exists($phase['value'], $internalPhases)) {
                $options[$key]['label'] = $this->getDemosplanConfig()->getPhaseNameWithPriorityInternal(
                    $phase['value']
                );
            }
            if (array_key_exists($phase['value'], $externalPhases)) {
                $options[$key]['label'] = $this->getDemosplanConfig()->getPhaseNameWithPriorityExternal(
                    $phase['value']
                );
            }
        }
        // sort alphabetically
        $options = collect($options)->sortBy('label')->values()->all();

        return $options;
    }

    /**
     * Get translated options of the publicStatement filter.
     *
     * @param array $options
     *
     * @return array
     */
    protected function getTranslatedPublicStatementOptions($options)
    {
        $translator = $this->translator;
        $publicStatementStatus = [
            'external' => $translator->trans('public'),
            'internal' => $translator->trans('invitable_institution'),
        ];

        return $this->getTranslatedLabelMapOptions($options, $publicStatementStatus);
    }

    /**
     * Get translated options of the status filter.
     *
     * @param array $options
     *
     * @return array
     */
    protected function getTranslatedStatusOptions($options)
    {
        $translator = $this->translator;
        $statusLabels = collect($this->getFormParameter('statement_status'))
            ->transform(fn ($transkey) => $translator->trans($transkey))
            ->toArray();

        return $this->getTranslatedLabelMapOptions($options, $statusLabels);
    }

    /**
     * Get translated options of the filter by a label map.
     *
     * @param array $options
     * @param array $labelMap
     *
     * @return array
     */
    protected function getTranslatedLabelMapOptions($options, $labelMap)
    {
        $translator = $this->translator;
        foreach ($options as $key => $value) {
            $options[$key]['label'] = array_key_exists($value['label'], $labelMap) ?
                $labelMap[$value['label']] : $translator->trans($value['label']);
        }
        // sort alphabetically
        $options = collect($options)->sortBy('label')->values()->all();

        return $options;
    }

    /**
     * Returns labels for filters.
     *
     * @return array get translated labels for all possible filters
     */
    public function getFilterLabelMap()
    {
        $translator = $this->translator;

        return [
            'assignee_id'                 => $translator->trans('assignee'),
            'categories'                  => $translator->trans('categories'),
            'countyNames'                 => $translator->trans('county'),
            'department'                  => $translator->trans('department'),
            'documentParentId'            => $translator->trans('file'),
            'externId'                    => 'Id',
            'fragments.priorityAreaKeys'  => $translator->trans('priorityArea'),
            'fragments_countyNames'       => $translator->trans('county'),
            'fragments_documentParentId'  => $translator->trans('file'),
            'fragments_element'           => $translator->trans('document'),
            'fragments_lastClaimed_id'    => $translator->trans('fragment.lastClaimed'),
            'fragments_municipalityNames' => $translator->trans('municipality'),
            'fragments_paragraphParentId' => $translator->trans('paragraph'),
            'fragments_reviewerName'      => $translator->trans('reviewer'),
            'fragments_status'            => $translator->trans('processing.status'),
            'fragments_tagNames'          => $translator->trans('tag'),
            'fragments_vote'              => $translator->trans('fragment.vote.short'),
            'fragments_voteAdvice'        => $translator->trans('fragment.voteAdvice.short'),
            'institution'                 => $translator->trans('invitable_institution'),
            'movedFromProcedureId'        => $translator->trans('statement.movedFrom.filter.label'),
            'movedToProcedureId'          => $translator->trans('statement.movedTo.filter.label'),
            'municipalityNames'           => $translator->trans('municipality'),
            'name'                        => $translator->trans('statement.cluster.name'),
            'phase'                       => $translator->trans('procedure.public.phase'),
            'planningDocument'            => $translator->trans('document'),
            'priority'                    => $translator->trans('priority'),
            'priorityAreaKeys'            => $translator->trans('priorityArea'),
            'publicCheck'                 => $translator->trans('publication'),
            'publicStatement'             => $translator->trans('public.or.invitable_institution'),
            'reasonParagraph'             => $translator->trans('paragraph'),
            'status'                      => $translator->trans('processing.status'),
            'tagNames'                    => $translator->trans('tag'),
            'tags'                        => $translator->trans('tags'),
            'topicNames'                  => $translator->trans('topic'),
            'type'                        => $translator->trans('statement.type'),
            'userGroup'                   => $translator->trans('organisation'),
            'userOrganisation'            => $translator->trans('organisation.name'),
            'userPosition'                => $translator->trans('position'),
            'userState'                   => $translator->trans('state'),
            'votePla'                     => $translator->trans('fragment.vote.short'),
            'voteStk'                     => $translator->trans('fragment.voteAdvice.short'),
        ];
    }

    /**
     * get Structure of assessmentTable filters.
     *
     * @param bool $originalStatements
     *
     * @return array
     *
     * @throws Exception
     */
    public function getAvailableFilters($originalStatements = false)
    {
        if ($originalStatements) {
            return $this->getAvailableFiltersOriginalStatement();
        }

        return $this->getAvailableFiltersAssessmentTable();
    }

    /**
     * Get available Assessmenttable filters.
     *
     * The defined filters need a key for matching with the es request, a permission check to actually show them
     * in the modal and the section name of the modal where they will appear. The position in this array determines
     * the order in which the filters appear on the website.
     *
     * <code>
     * [
     *           'key' => 'ES_KEY',
     *           'hasPermission' => $permissions->hasPermission(
     *               permissionCheck
     *           ),
     *           'type' => 'section'
     * ],
     * </code>
     *
     * @return array
     *
     * @throws Exception
     */
    protected function getAvailableFiltersAssessmentTable()
    {
        // Fachplaner-Admin (Abwägungstabelle Filter) → $permissions->hasPermission('area_admin_assessmenttable'))
        // Fachplaner-Planungsbehörde (Datensatzliste Filter) → ($permissions->hasPermission('feature_statements_fragment_list')
        $definedFilters = [
            [
                'key'           => 'userState',
                'hasPermission' => $this->permissions->hasPermission('field_statement_user_state'),
                'type'          => 'submission',
            ],
            [
                'key'           => 'userGroup',
                'hasPermission' => $this->permissions->hasPermission('field_statement_user_group'),
                'type'          => 'submission',
            ],
            [
                'key'           => 'userOrganisation',
                'hasPermission' => $this->permissions->hasPermission('field_statement_user_organisation'),
                'type'          => 'submission',
            ],
            [
                'key'           => 'userPosition',
                'hasPermission' => $this->permissions->hasPermission('field_statement_user_position'),
                'type'          => 'submission',
            ],
            /********************************** EINREICHUNG *********************************************/

            // Öffentlichkeit/Institution - publicStatement - publicStatement
            [
                'key'           => 'publicStatement',
                'hasPermission' => $this->permissions->hasPermissions(
                    [
                        'area_admin_assessmenttable',
                        'feature_institution_participation',
                    ]
                ),
                'type'          => 'submission',
            ],
            // Institution/Name - institution - oName.raw
            [
                'key'           => 'institution',
                'hasPermission' => $this->permissions->hasPermissions(
                    [
                        'area_admin_assessmenttable',
                        'feature_institution_participation',
                    ]
                ),
                'type'          => 'submission',
            ],
            // Abteilung - department - dName.raw
            [
                'key'           => 'department',
                'hasPermission' => $this->permissions->hasPermissions([
                    'area_admin_assessmenttable',
                    'feature_institution_participation',
                ]),
                'type'          => 'submission',
            ],
            // Verfahrensschritt - phase - phase
            [
                'key'           => 'phase',
                'hasPermission' => $this->permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'submission',
            ],
            // Verschobene Stellungnahmen in dieses Verfahren - movedFromProcedureId - movedFromProcedureId
            [
                'key'           => 'movedFromProcedureId',
                'hasPermission' => $this->permissions->hasPermissions(
                    ['area_admin_assessmenttable', 'feature_statement_move_to_procedure']
                ),
                'type'          => 'submission',
            ],
            // Verschobene Stellungnahmen aus diesem Verfahren - movedToProcedureId - movedToProcedureId
            [
                'key'           => 'movedToProcedureId',
                'hasPermission' => $this->permissions->hasPermissions(
                    ['area_admin_assessmenttable', 'feature_statement_move_to_procedure']
                ),
                'type'          => 'submission',
            ],
            // Veröffentlichung - publicCheck - publicCheck
            [
                'key'           => 'publicCheck',
                'hasPermission' => $this->permissions->hasPermission(
                    'field_statement_public_allowed'
                ),
                'type'          => 'submission',
            ],

            /********************************** STELLUNGNAHME *********************************************/

            // Sachbearbeiter - assignee_id - assignee.id
            [
                'key'           => 'assignee_id',
                'hasPermission' => $this->permissions->hasPermission(
                    'feature_statement_assignment'
                ),
                'type'          => 'statement',
            ],
            // Bearbeitungsstatus - status - status
            [
                'key'           => 'status',
                'hasPermission' => $this->permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'statement',
            ],
            // Votum - votePla - votePla
            [
                'key'           => 'votePla',
                'hasPermission' => $this->permissions->hasPermission(
                    'field_statement_vote_pla'
                ),
                'type'          => 'statement',
            ],
            // Kreis - countyNames - countyNames.raw
            [
                'key'           => 'countyNames',
                'hasPermission' => $this->permissions->hasPermission(
                    'field_statement_county'
                ),
                'type'          => 'statement',
            ],
            // Gemeinde - municipalityNames - municipalityNames.raw
            [
                'key'           => 'municipalityNames',
                'hasPermission' => $this->permissions->hasPermission(
                    'field_statement_municipality'
                ),
                'type'          => 'statement',
            ],
            // Schlagwort - tagNames - tagNames.raw
            [
                'key'           => 'tagNames',
                'hasPermission' => $this->permissions->hasPermission(
                    'feature_statements_tag'
                ),
                'type'          => 'statement',
            ],
            // Potenzialflächen - priorityAreaKeys - priorityAreaKeys
            [
                'key'           => 'priorityAreaKeys',
                'hasPermission' => $this->permissions->hasPermission(
                    'field_statement_priority_area'
                ),
                'type'          => 'statement',
            ],
            // Dokument - planningDocument - elementId
            [
                'key'           => 'planningDocument',
                'hasPermission' => $this->permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'statement',
            ],
            // Kapitel - reasonParagraph - paragraphParentId
            [
                'key'           => 'reasonParagraph',
                // FB or FPA
                'hasPermission' => $this->permissions->hasPermissions(
                    [
                        'area_admin_assessmenttable',
                        'feature_documents_category_use_paragraph',
                    ]),
                'type'          => 'statement',
            ],
            // Datei - documentParentId - documentParentId
            [
                'key'           => 'documentParentId',
                'hasPermission' => $this->permissions->hasPermissions([
                    'area_admin_assessmenttable',
                    'feature_statement_file_filter_set',
                ]
                ),
                'type'          => 'statement',
            ],
            // Thema - topicNames - topicNames.raw
            [
                'key'           => 'topicNames',
                'hasPermission' => $this->permissions->hasPermission('feature_statements_tag')
                    || $this->permissions->hasPermission('feature_statement_fragments_tag'),
                'type'          => 'statement',
            ],
            // ID - externId - externId
            [
                'key'           => 'externId',
                'hasPermission' => $this->permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'statement',
            ],
            // Gruppenname - name - name.raw
            [
                'key'           => 'name',
                'hasPermission' => $this->permissions->hasPermission(
                    'feature_statement_cluster'
                ),
                'type'          => 'statement',
            ],
            // Art der Stellungnahme - type - type
            [
                'key'           => 'type',
                'hasPermission' => $this->permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'statement',
            ],
            // Priorität - priority - priority
            [
                'key'           => 'priority',
                'hasPermission' => $this->permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'statement',
            ],
            // Empfehlung - voteStk - voteStk
            [
                'key'           => 'voteStk',
                'hasPermission' => $this->permissions->hasPermission(
                    'field_statement_vote_stk'
                ),
                'type'          => 'statement',
            ],

            /********************************** DATENSATZ *********************************************/

            // Sachbearbeiter - fragments_lastClaimed_id - fragments.lastClaimedUserId
            [
                'key'           => 'fragments_lastClaimed_id',
                'hasPermission' => $this->permissions->hasPermission(
                    'feature_statement_assignment'
                ),
                'type'          => 'fragment',
            ],
            // Bearbeitungsstatus - fragments_status - fragments.status
            [
                'key'           => 'fragments_status',
                'hasPermission' => $this->permissions->hasPermission(
                    'field_fragment_status'
                ),
                'type'          => 'fragment',
            ],
            // Votum - fragments_vote - fragments.vote
            [
                'key'           => 'fragments_vote',
                'hasPermission' => $this->permissions->hasPermission(
                    'feature_statements_fragment_vote'
                ),
                'type'          => 'fragment',
            ],
            // Kreis - fragments_countyNames - fragments.countyNames
            [
                'key'           => 'fragments_countyNames',
                'hasPermission' => $this->permissions->hasPermission(
                    'field_statement_county'
                ),
                'type'          => 'fragment',
            ],
            // Gemeinde - fragments_municipalityNames - fragments.municipalityNames
            [
                'key'           => 'fragments_municipalityNames',
                'hasPermission' => $this->permissions->hasPermission(
                    'field_statement_municipality'
                ),
                'type'          => 'fragment',
            ],
            // Schlagwort - fragments_tagNames - fragments.tags.name
            [
                'key'           => 'fragments_tagNames',
                'hasPermission' => $this->permissions->hasPermission(
                    'feature_statements_tag'
                ),
                'type'          => 'fragment',
            ],
            // Potenzialflächen - fragments.priorityAreaKeys - fragments.priorityAreaKeys
            [
                'key'           => 'fragments.priorityAreaKeys',
                'hasPermission' => $this->permissions->hasPermission(
                    'field_statement_priority_area'
                ),
                'type'          => 'fragment',
            ],
            // Dokument - fragments_element - fragments.elementId
            [
                'key'           => 'fragments_element',
                'hasPermission' => $this->permissions->hasPermission(
                    'feature_statements_fragment_add'
                ),
                'type'          => 'fragment',
            ],
            // Kapitel - fragments_paragraphParentId - fragments.paragraphParentId
            [
                'key'           => 'fragments_paragraphParentId',
                'hasPermission' => $this->permissions->hasPermission(
                    'feature_statements_fragment_add'
                ),
                'type'          => 'fragment',
            ],
            // Datei - fragments_documentParentId - fragments.documentParentId
            [
                'key'           => 'fragments_documentParentId',
                'hasPermission' => $this->permissions->hasPermission(
                    'feature_single_document_fragment'
                ),
                'type'          => 'fragment',
            ],
            // Fachbehörde - fragments_reviewerName - fragments.departmentId
            [
                'key'           => 'fragments_reviewerName',
                'hasPermission' => $this->permissions->hasPermissions(
                    ['feature_statements_fragment_list', 'feature_statements_fragment_add_reviewer']
                ),
                'type'          => 'fragment',
            ],
            /********************************** END *********************************************/
        ];

        return $definedFilters;
    }

    /**
     * get available original statement filters.
     *
     * @return array
     *
     * @throws Exception
     */
    protected function getAvailableFiltersOriginalStatement()
    {
        $permissions = $this->getPermissions();
        // Fachplaner-Admin (Abwägungstabelle Filter) → $permissions->hasPermission('area_admin_assessmenttable'))
        // Fachplaner-Planungsbehörde (Datensatzliste Filter) → ($permissions->hasPermission('feature_statements_fragment_list')
        $definedFilters = [
            [
                'key'           => 'publicStatement',
                'hasPermission' => $permissions->hasPermissions(
                    [
                        'area_admin_assessmenttable',
                        'feature_institution_participation',
                    ]
                ),
                'type'          => 'submission',
            ],
            [
                'key'           => 'institution',
                'hasPermission' => $permissions->hasPermissions(
                    [
                        'area_admin_assessmenttable',
                        'feature_institution_participation',
                    ]
                ),
                'type'          => 'submission',
            ],
            [
                'key'           => 'department',
                'hasPermission' => $permissions->hasPermissions(
                    [
                        'area_admin_assessmenttable',
                        'feature_institution_participation',
                    ]
                ),
                'type'          => 'submission',
            ],
            [
                'key'           => 'phase',
                'hasPermission' => $permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'submission',
            ],
            // Dokument - planningDocument - elementId
            [
                'key'           => 'planningDocument',
                'hasPermission' => $permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'statement',
            ],
            // Kapitel - reasonParagraph - paragraphParentId
            [
                'key'           => 'reasonParagraph',
                // FB or FPA
                'hasPermission' => $permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'statement',
            ],
            // Datei - documentParentId - documentParentId
            [
                'key'           => 'documentParentId',
                'hasPermission' => $permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'statement',
            ],
            // Art der Stellungnahme - type - type
            [
                'key'           => 'type',
                'hasPermission' => $permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'statement',
            ],
            // ID - externId - externId
            [
                'key'           => 'externId',
                'hasPermission' => $permissions->hasPermission(
                    'area_admin_assessmenttable'
                ),
                'type'          => 'statement',
            ],
        ];

        return $definedFilters;
    }

    /**
     * @return Permissions
     *
     * @throws Exception
     */
    public function getPermissions()
    {
        if (!$this->permissions instanceof Permissions) {
            throw new Exception('Inject permissions object into StatementFilterHandler first');
        }

        return $this->permissions;
    }

    /**
     * Given the requested filters and the ES result for such filters, builds the filters info required by frontend.
     *
     * @throws Exception
     */
    public function getRequestedFiltersInfo(array $requestedFilters, array $esFilters): array
    {
        // We need the available filters to get the filtertype/tab
        $availableFilters = $this->getAvailableFilters();
        $requestedfiltersInfo = [];
        foreach ($requestedFilters as $requestedFilterKey => $requestedFilterValues) {
            $esFilterValues = $esFilters[$requestedFilterKey] ?? [];
            foreach ($esFilterValues as $esFilterValue) {
                if (in_array($esFilterValue['value'], $requestedFilterValues, true)) {
                    $filterType = '';
                    foreach ($availableFilters as $availableFilter) {
                        if ($availableFilter['key'] === $requestedFilterKey) {
                            $filterType = $availableFilter['type'];
                            break;
                        }
                    }
                    $translatedEsFilter = $this->getTranslatedFilterOptions($requestedFilterKey, [$esFilterValue], $filterType);
                    if (is_array($translatedEsFilter) && 0 < count($translatedEsFilter)) {
                        $esFilterValue = $translatedEsFilter[0];
                    }
                    $esFilterValue['filterId'] = hash('sha256', 'filter_'.$requestedFilterKey.$filterType);
                    $esFilterValue['type'] = $filterType;
                    $requestedfiltersInfo[] = $esFilterValue;
                }
            }
        }

        return $requestedfiltersInfo;
    }
}
