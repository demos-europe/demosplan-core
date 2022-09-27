<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { DpButton, DpInput } from 'demosplan-ui/components'
import { dpApi } from '@DemosPlanCoreBundle/plugins/DpApi'
import DpDateRangePicker from '@DpJs/components/core/form/DpDateRangePicker'
import DpDatetimePicker from '@DpJs/components/core/form/DpDatetimePicker'
import DpEmailList from './DpEmailList'
import DpInlineNotification from '@DemosPlanCoreBundle/components/DpInlineNotification'
import DpMultiselect from '@DpJs/components/core/form/DpMultiselect'
import DpEditor from '@DpJs/components/core/DpEditor'
import ExportSettings from './ExportSettings'
import sortAlphabetically from '@DpJs/lib/utils/sortAlphabetically'

export default {
  name: 'DpBasicSettings',

  components: {
    AutoSwitchProcedurePhaseForm: () => import(/* webpackChunkName: "auto-switch-procedure-phase-form" */ '@DemosPlanProcedureBundle/components/basicSettings/AutoSwitchProcedurePhaseForm'),
    DpButton,
    DpDateRangePicker,
    DpDatetimePicker,
    DpEmailList,
    DpInlineNotification,
    DpInput,
    DpMultiselect,
    DpProcedureCoordinate: () => import(/* webpackChunkName: "dp-procedure-coordinate" */ './DpProcedureCoordinate'),
    DpEditor,
    DpUploadFiles: () => import(/* webpackChunkName: "dp-upload-files" */ '@DpJs/components/core/DpUpload/DpUploadFiles'),
    ExportSettings
  },

  props: {
    authorizedUsersOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    initAgencies: {
      required: false,
      type: Array,
      default: () => []
    },

    initDataInputOrgas: {
      required: false,
      type: Array,
      default: () => []
    },

    initAuthUsers: {
      required: false,
      type: Array,
      default: () => []
    },

    initProcedureCategories: {
      required: false,
      type: Array,
      default: () => []
    },

    initProcedureName: {
      required: false,
      type: String,
      default: ''
    },

    initProcedurePhaseInternal: {
      required: false,
      type: String,
      default: ''
    },

    initProcedurePhasePublic: {
      required: false,
      type: String,
      default: ''
    },

    initSimilarRecommendationProcedures: {
      required: false,
      type: Array,
      default: () => []
    },

    participationPhases: {
      required: false,
      type: Array,
      default: () => []
    },

    plisId: {
      required: false,
      type: String,
      default: ''
    },

    procedureExternalDesc: {
      required: false,
      type: String,
      default: ''
    }
  },

  data () {
    return {
      isLoadingPlisData: false,
      selectedAgencies: this.initAgencies,
      selectedDataInputOrgas: this.initDataInputOrgas,
      selectedAuthUsers: this.initAuthUsers,
      selectedProcedureCategories: this.initProcedureCategories,
      selectedInternalPhase: this.initProcedurePhaseInternal,
      selectedPublicPhase: this.initProcedurePhasePublic,
      selectedSimilarRecommendationProcedures: this.initSimilarRecommendationProcedures,
      procedureDescription: this.procedureExternalDesc,
      procedureName: this.initProcedureName
    }
  },

  computed: {
    authUsersOptions () {
      const users = JSON.parse(JSON.stringify(this.authorizedUsersOptions))
      return sortAlphabetically(users, 'name')
    },

    publicPhaseIsInParticipation () {
      return this.participationPhases.includes(this.selectedPublicPhase)
    },

    internalPhaseIsInParticipation () {
      return this.participationPhases.includes(this.selectedInternalPhase)
    }
  },

  methods: {
    getDataPlis (plisId, routeName) {
      return dpApi({
        method: 'get',
        responseType: 'json',
        url: Routing.generate(routeName, { uuid: plisId })
      })
        .then(data => {
          return data.data
        })
    },

    selectAllAuthUsers () {
      this.selectedAuthUsers = this.authorizedUsersOptions
    },

    unselectAllAuthUsers () {
      this.selectedAuthUsers = []
    },

    // This method is only in use for bobHH.
    updateParticipationDescriptionAndName (plisId) {
      this.isLoadingPlisData = true
      this.getDataPlis(plisId, 'DemosPlan_plis_get_procedure')
        .then(data => {
          if (data.code === 100 && data.success === true) {
            const procedureDescriptionText = data.procedure.planungsanlass
            let msg = Translator.trans('statement.save.notification')
            if (procedureDescriptionText === this.procedureDescription) {
              msg = Translator.trans('warning.plis.no.changes')
            }
            this.procedureDescription = procedureDescriptionText
            dplan.notify.notify('confirm', Translator.trans(Translator.trans('information.short.imported.successfully') + ' ' + msg))
          } else {
            dplan.notify.notify('error', Translator.trans('error.plis.getplanningcause'))
          }
          this.isLoadingPlisData = false
        })

      this.getDataPlis(plisId, 'DemosPlan_plis_get_procedure_name')
        .then(data => {
          if (data.code === 100 && data.success === true) {
            const procedureNameNew = data.procedureName
            let msg = Translator.trans('statement.save.notification')
            if (procedureNameNew === this.procedureName) {
              msg = Translator.trans('warning.plis.no.changes')
            }
            this.procedureName = procedureNameNew
            dplan.notify.notify('confirm', Translator.trans(Translator.trans('information.short.imported.successfully') + ' ' + msg))
          } else {
            dplan.notify.notify('error', Translator.trans('error.procedure.name.not.imported'))
          }
        })
    }
  },

  mounted () {
    const users = JSON.parse(JSON.stringify(this.initAuthUsers))
    this.selectedAuthUsers = sortAlphabetically(users, 'name')
  }
}
</script>
