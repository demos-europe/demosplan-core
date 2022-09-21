<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { DpInput, DpLabel } from 'demosplan-ui/components'
import CoupleTokenInput from './CoupleTokenInput'
import { dpApi } from '@DemosPlanCoreBundle/plugins/DpApi'
import DpDateRangePicker from '@DpJs/components/core/form/DpDateRangePicker'
import DpFormRow from '@DpJs/components/core/form/DpFormRow'
import DpInlineNotification from '@DemosPlanCoreBundle/components/DpInlineNotification'
import DpMultiselect from '@DpJs/components/core/form/DpMultiselect'
import DpSelect from '@DpJs/components/core/form/DpSelect'
import DpTextArea from '@DpJs/components/core/form/DpTextArea'
import DpUploadFiles from '@DemosPlanCoreBundle/components/DpUpload/DpUploadFiles'

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
