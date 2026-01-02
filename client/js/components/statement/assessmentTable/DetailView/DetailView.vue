<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This component is used as a wrapper for statement and cluster detail view -->
</documentation>

<template>
  <div>
    <dp-map-modal
      ref="mapModal"
      :procedure-id="procedureId"
    />

    <slot
      :add-tag-boilerplate="addTagBoilerplate"
      :busy-copy-from-fragments="busyCopyFromFragments"
      :copy-recommendation-from-fragments="copyRecommendationFromFragments"
      :counties="counties"
      :current-recommendation="currentRecommendation"
      :handle-counties-input="handleCountiesInput"
      :handle-municipalities-input="handleMunicipalitiesInput"
      :handle-priority-areas-input="handlePriorityAreasInput"
      :handle-tags-input="handleTagsInput"
      :municipalities="municipalities"
      :open-map-modal="openMapModal"
      :open-statement-publish="openStatementPublish"
      :open-statement-voters="openStatementVoters"
      :priority-areas="priorityAreas"
      :selected-counties="selectedCounties"
      :selected-municipalities="selectedMunicipalities"
      :selected-priority-areas="selectedPriorityAreas"
      :selected-tags="selectedTags"
      :sort-selected="sortSelected"
      :tags="tags"
      :update-current-recommendation="updateCurrentRecommendation"
      :update-selected-counties="updateSelectedCounties"
      :update-selected-municipalities="updateSelectedMunicipalities"
      :update-selected-priority-areas="updateSelectedPriorityAreas"
      :update-selected-tags="updateSelectedTags"
    />
  </div>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import { dpApi } from '@demos-europe/demosplan-ui'
import DpMapModal from '@DpJs/components/statement/assessmentTable/DpMapModal'

export default {
  name: 'DpDetailView',

  components: {
    DpMapModal,
  },

  props: {
    // Statement or cluster
    entity: {
      required: true,
      type: String,
    },

    externId: {
      required: false,
      type: String,
      default: '',
    },

    initCounties: {
      required: false,
      type: Array,
      default: () => ([]),
    },

    initMunicipalities: {
      required: false,
      type: Array,
      default: () => ([]),
    },

    initPriorityAreas: {
      required: false,
      type: Array,
      default: () => ([]),
    },

    initRecommendation: {
      required: false,
      type: String,
      default: '',
    },

    initTags: {
      required: false,
      type: Array,
      default: () => ([]),
    },

    // Only needed in statement detail view
    isCopy: {
      required: false,
      type: Boolean,
      default: false,
    },

    procedureId: {
      required: true,
      type: String,
    },

    readonly: {
      required: true,
      type: Boolean,
    },

    statementId: {
      required: true,
      type: String,
    },
  },

  emits: [
    'show-slidebar',
    'version:history',
  ],

  data () {
    return {
      busyCopyFromFragments: false,
      currentRecommendation: '',
      selectedCounties: [],
      selectedMunicipalities: [],
      selectedPriorityAreas: [],
      selectedTags: [],
    }
  },

  computed: {
    ...mapGetters('AssessmentTable', ['counties', 'municipalities', 'priorityAreas', 'tags']),
  },

  methods: {
    ...mapActions('AssessmentTable', ['applyBaseData']),

    addTagBoilerplate (value) {
      if (hasPermission('area_admin_boilerplates')) {
        const url = Routing.generate('dm_plan_assessment_get_boilerplates_ajax', { tag: value.id, procedure: this.procedureId })

        dpApi.get(url)
          .then(response => {
            if (response.status === 200 && response.data.body !== '') {
              this.currentRecommendation = this.currentRecommendation + '<p>' + response.data.body + '</p>'
            }
          })
      }
    },

    copyRecommendationFromFragments () {
      if (hasPermission('feature_statements_fragment_consideration') && this.readonly === false) {
        const getConsiderationPath = Routing.generate('DemosPlan_statement_fragment_considerations_get_ajax', { procedure: this.procedureId, statementId: this.statementId })

        this.busyCopyFromFragments = true

        return dpApi.post(getConsiderationPath)
          .then(response => {
            if (response.data.code === 200 && response.data.success === true && response.data.body.considerations.length > 0) {
              const newText = response.data.body.considerations.join('')

              this.currentRecommendation += newText

              dplan.notify.notify('confirm', Translator.trans('confirm.statement.considerations.attached', { count: response.data.body.considerations.length }))
            }
          })
          .catch(() => {
            dplan.notify.notify('error', Translator.trans('error.results.loading'))
          })
          .finally(() => {
            this.busyCopyFromFragments = false
          })
      }
    },

    handleCountiesInput (value) {
      this.updateSelectedCounties(value)
      this.sortSelected('Counties')
    },

    handleMunicipalitiesInput (value) {
      this.updateSelectedMunicipalities(value)
      this.sortSelected('Municipalities')
    },

    handlePriorityAreasInput (value) {
      this.updateSelectedPriorityAreas(value)
      this.sortSelected('PriorityAreas')
    },

    handleTagsInput (value) {
      this.updateSelectedTags(value)
      this.sortSelected('Tags')
    },

    openMapModal (polygon) {
      this.$refs.mapModal.toggleModal(polygon)
    },

    openStatementPublish () {
      this.$refs.statementPublish.toggle(true)
    },

    openStatementVoters () {
      this.$refs.statementVoters.toggle(true)
    },

    sortSelected (type) {
      const area = `selected${type}`
      this[area].sort((a, b) => (a.name > b.name) ? 1 : ((b.name > a.name) ? -1 : 0))
    },

    setupSaveAndReturnHandler () {
      const saveAndReturnButton = document.querySelector('input[name="submit_item_return_button"]')

      if (saveAndReturnButton) {
        saveAndReturnButton.addEventListener('click', () => {
          globalThis.sessionStorage.setItem('saveAndReturn', true)
        })
      }
    },

    updateCurrentRecommendation (value) {
      this.currentRecommendation = value
    },

    updateSelectedCounties (value) {
      this.selectedCounties = value
    },

    updateSelectedMunicipalities (value) {
      this.selectedMunicipalities = value
    },

    updateSelectedPriorityAreas (value) {
      this.selectedPriorityAreas = value
    },

    updateSelectedTags (value) {
      this.selectedTags = value
    },
  },

  mounted () {
    this.selectedCounties = this.initCounties
    this.selectedMunicipalities = this.initMunicipalities
    this.selectedPriorityAreas = this.initPriorityAreas
    this.selectedTags = this.initTags
    this.currentRecommendation = this.initRecommendation

    this.applyBaseData([this.procedureId])

    const statementHistoryButton = document.getElementById('statementHistory')
    const clusterHistoryButton = document.getElementById('groupHistory')

    if (this.entity === 'statement' && hasPermission('feature_statement_content_changes_save') && statementHistoryButton) {
      statementHistoryButton.addEventListener('click', (event) => {
        event.preventDefault()
        const externalId = this.isCopy ? Translator.trans('copyof') + ' ' + this.externId : this.externId
        this.$root.$emit('version:history', this.statementId, 'statement', externalId)
        this.$root.$emit('show-slidebar')
      })
    }

    if (this.entity === 'cluster' && hasPermission('feature_statement_content_changes_save') && clusterHistoryButton) {
      clusterHistoryButton.addEventListener('click', (event) => {
        event.preventDefault()
        this.$root.$emit('version:history', this.statementId, 'statement', this.externId)
        this.$root.$emit('show-slidebar')
      })
    }

    this.setupSaveAndReturnHandler()
  },
}
</script>
