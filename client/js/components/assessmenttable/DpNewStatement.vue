<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import {
  DpAccordion,
  DpButton,
  DpDatepicker,
  DpEditor,
  DpInput,
  DpLabel,
  DpMultiselect,
  DpSelect,
  DpUploadFiles,
  dpValidateMixin,
  hasOwnProp
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters } from 'vuex'
import DpAutofillSubmitterData from '@DpJs/components/statement/statement/DpAutofillSubmitterData'
import DpSelectStatementCluster from '@DpJs/components/statement/statement/SelectStatementCluster'
import StatementPublish from '@DpJs/components/statement/statement/StatementPublish'
import StatementVoter from '@DpJs/components/statement/voter/StatementVoter'

export default {
  name: 'DpNewStatement',

  components: {
    DpAccordion,
    DpAutofillSubmitterData,
    DpButton,
    DpDatepicker,
    DpInput,
    DpLabel,
    DpMultiselect,
    DpSelect,
    DpSelectStatementCluster,
    StatementPublish,
    DpEditor,
    DpUploadFiles,
    StatementVoter
  },

  mixins: [dpValidateMixin],

  props: {
    procedureId: {
      required: true,
      type: String
    },

    currentExternalPhase: {
      type: String,
      required: true
    },

    currentInternalPhase: {
      type: String,
      required: true
    },

    /**
     * Default value for procedure phase select, is set to current publicParticipationPhase
     * keys for external and internal phases are identical, only translation strings differ
     */
    defaultPhase: {
      required: false,
      type: String,
      default: ''
    },

    defaultSubmitType: {
      required: false,
      type: String,
      default: ''
    },

    /**
     * Needed for procedure phase select
     */
    externalPhases: {
      required: false,
      type: Object,
      default: () => ({})
    },

    /**
     * Needed for procedure phase select
     */
    internalPhases: {
      required: false,
      type: Object,
      default: () => ({})
    },

    requestText: {
      required: false,
      type: String,
      default: ''
    },

    requestHeadStatement: {
      required: false,
      type: String,
      default: ''
    },

    requestCounties: {
      required: false,
      type: Array,
      default: () => []
    },

    requestMunicipalities: {
      required: false,
      type: Array,
      default: () => []
    },

    requestPriorityAreas: {
      required: false,
      type: Array,
      default: () => []
    },

    usedInternIdsPattern: {
      required: false,
      type: Array,
      default: () => []
    }
  },

  data () {
    return {
      countiesPromptAdded: false,
      municipalitiesPromptAdded: false,
      values: {
        submitter: {},
        submitType: this.defaultSubmitType,
        phase: {},
        headStatement: this.requestHeadStatement,
        element: '',
        paragraph: '',
        document: '',
        counties: this.requestCounties,
        municipalities: this.requestMunicipalities,
        priorityAreas: this.requestPriorityAreas,
        tags: [],
        text: this.requestText,
        submittedDate: '',
        authoredDate: ''
      },
      elementHasParagraphs: false,
      elementHasFiles: false,
      internalPhaseOptions: this.internalPhases,
      externalPhaseOptions: this.externalPhases,
      institutionSelected: false
    }
  },

  computed: {
    ...mapGetters('AssessmentTable', [
      'counties',
      'municipalities',
      'priorityAreas',
      'tags',
      'elements',
      'paragraph',
      'documents',
      'procedurePhases'
    ]),

    phases () {
      if (this.institutionSelected) {
        return this.procedurePhases({
          internal: true,
          external: false
        }).map(el => {
          return {
            ...el,
            value: el.key,
            label: el.name
          }
        })
      } else {
        return this.procedurePhases({
          internal: false,
          external: true
        }).map(el => {
          return {
            ...el,
            value: el.key,
            label: el.name
          }
        })
      }
    }
  },

  methods: {
    ...mapActions('AssessmentTable', ['applyBaseData']),

    addLocationPrompt (data) {
      if (data && data.counties && data.counties.length > 0) {
        this.values.counties = data.counties.map(id => this.counties.find(county => county.id === id))
        this.sortSelected('counties')
        this.countiesPromptAdded = true
      } else if (data && data.counties && data.counties.length === 0) {
        this.values.counties = []
        this.countiesPromptAdded = false
      }
      if (data && data.municipalities && data.municipalities.length > 0) {
        this.values.municipalities = data.municipalities.map(id => this.municipalities.find(municipality => municipality.id === id))
        this.sortSelected('municipalities')
        this.municipalitiesPromptAdded = true
      } else if (data && data.municipalities && data.municipalities.length === 0) {
        this.values.municipalities = []
        this.municipalitiesPromptAdded = false
      }
    },

    checkForParagraphsAndFiles () {
      this.values.paragraph = { id: '', title: '-' }
      this.values.document = { id: '', title: '-' }
      this.elementHasParagraphs = hasOwnProp(this.paragraph, this.values.element.id)
      this.elementHasFiles = hasOwnProp(this.documents, this.values.element.id)
    },

    handlePhaseSelect () {
      this.values.phase = document.querySelector('select[name="r_phase"]').value
    },

    /**
     * Sets the preselected phase to the phase that the procedure is currently in. External phase is for citizen and
     * internal phase applies to institutions.
     */
    setDefaultPhase (isInstitution) {
      if (isInstitution) {
        this.values.phase = Object.values(this.internalPhases).find(el => el.key === this.currentInternalPhase) || Object.values(this.internalPhases)[0]
      } else {
        this.values.phase = Object.values(this.externalPhases).find(el => el.key === this.currentExternalPhase) || Object.values(this.externalPhases)[0]
      }
    },

    setPhaseValue (value) {
      if (value) {
        this.values.phase = this.phases.find(el => el.value === value)
      }
    },

    sortSelected (type) {
      this.values[type].sort((a, b) => {
        if (a.name > b.name) {
          return 1
        } else if (b.name > a.name) {
          return -1
        } else {
          return 0
        }
      })
    },

    submit () {
      if (this.dpValidate.newStatementForm) {
        document.querySelector('[data-dp-validate="newStatementForm"]').submit()
      }
    },

    handleRoleChange (newValue) {
      const isInstitution = newValue === '1'
      this.setDefaultPhase(isInstitution)
      this.institutionSelected = isInstitution
    }
  },

  mounted () {
    this.applyBaseData([this.procedureId])

    // Set initial options for phase select
    const initialRole = this.$refs.submitter.currentRole
    this.handleRoleChange(initialRole)
  }
}
</script>
