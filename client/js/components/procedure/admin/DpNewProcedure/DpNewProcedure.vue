<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { DpInput, DpLabel } from '@demos-europe/demosplan-ui/components'
import CoupleTokenInput from './CoupleTokenInput'
import { dpApi } from '@demos-europe/demosplan-utils'
import { DpDateRangePicker, DpFormRow, DpInlineNotification, DpMultiselect, DpSelect, DpTextArea, DpUploadFiles } from '@demos-europe/demosplan-ui/components/core'

export default {
  name: 'DpNewProcedure',
  components: {
    CoupleTokenInput,
    DpDateRangePicker,
    DpFormRow,
    DpInput,
    DpLabel,
    DpInlineNotification,
    DpMultiselect,
    DpSelect,
    DpTextArea,
    DpUploadFiles
  },

  props: {
    blueprintOptions: {
      type: Array,
      required: false,
      default: () => ([])
    },

    masterBlueprintId: {
      type: String,
      required: false,
      default: () => ''
    },

    procedureTypes: {
      type: Array,
      required: true,
      default: () => []
    }
  },

  data () {
    return {
      currentProcedureType: '',
      description: '',
      emptyBlueprintData: {
        description: '',
        agencyMainEmailAddress: ''
      },
      mainEmail: ''
    }
  },

  computed: {
    currentProcedureTypeId () {
      return this.currentProcedureType.id || ''
    }
  },

  methods: {
    async setBlueprintData (payload) {
      // Do not copy mail from master blueprint otherwise fetch mail from selected blueprint
      const blueprintData = payload.value === this.masterBlueprintId ? this.emptyBlueprintData : await this.fetchBlueprintData(payload)
      this.description = blueprintData.description
      this.mainEmail = blueprintData.agencyMainEmailAddress
    },

    fetchBlueprintData (blueprintId) {
      return dpApi.get(
        Routing.generate('api_resource_get', {
          resourceType: 'ProcedureTemplate',
          resourceId: blueprintId,
          fields: {
            ProcedureTemplate: [
              'agencyMainEmailAddress',
              'description'
            ].join()
          }
        })
      )
        .then(({ data }) => data.data.attributes)
        .catch(() => this.emptyBlueprintData) // When the request fails planners will have to fill in an address manually
    }
  }
}
</script>
