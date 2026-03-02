<template>
  <div>
    <h1>{{ Translator.trans('phases.currently.defined') }}</h1>

    <div class="space-stack-m mt-4">
      <dp-accordion
        v-if="!isInitiallyLoading"
        :is-open="true"
        :title="Translator.trans('audience.internal')"
      >
        <dp-data-table
          :header-fields="headerFields"
          :items="internalPhases"
          track-by="id"
        >
        </dp-data-table>
      </dp-accordion>

      <dp-accordion
        v-if="!isInitiallyLoading"
        :title="Translator.trans('audience.external')"
      >
        <dp-data-table
          :header-fields="headerFields"
          :items="externalPhases"
          track-by="id"
        >
        </dp-data-table>
      </dp-accordion>

      <dp-loading v-if="isInitiallyLoading" />
    </div>
  </div>
</template>

<script>
import {
  DpAccordion,
  dpApi,
  DpDataTable,
  DpLoading,
} from '@demos-europe/demosplan-ui'

export default {
  name: 'ProcedurePhasesDefinition',

  components: {
    DpAccordion,
    DpDataTable,
    DpLoading,
  },

  data () {
    return {
      isInitiallyLoading: true,
      phases: [],
    }
  },

  computed: {
    externalPhases () {
      return this.phases
        .filter(phase => phase.audience === 'external')
        .map(phase => this.mapPhaseForDisplay(phase))
    },

    headerFields () {
      return [
        { field: 'name', label: Translator.trans('phase.name') },
        { field: 'permissionSetLabel', label: Translator.trans('status') },
      ]
    },

    internalPhases () {
      return this.phases
        .filter(phase => phase.audience === 'internal')
        .map(phase => this.mapPhaseForDisplay(phase))
    },
  },

  methods: {
    fetchPhases () {
      this.isInitiallyLoading = true

      dpApi.get(Routing.generate('api_resource_list', {
        resourceType: 'ProcedurePhaseDefinition',
        fields: {
          ProcedurePhaseDefinition: [
            'name',
            'audience',
            'permissionSet',
            'participationState',
            'orderInAudience',
          ].join(),
        },
        sort: 'orderInAudience',
      }))
        .then(({ data }) => {
          this.phases = data.data.map(item => ({
            audience: item.attributes.audience,
            id: item.id,
            name: item.attributes.name,
            orderInAudience: item.attributes.orderInAudience,
            participationState: item.attributes.participationState,
            permissionSet: item.attributes.permissionSet,
          }))
        })
        .catch(err => {
          console.error(err)
          dplan.notify.error(Translator.trans('error.generic'))
        })
        .finally(() => {
          this.isInitiallyLoading = false
        })
    },

    mapPhaseForDisplay (phase) {
      const permissionSetLabels = {
        hidden: Translator.trans('permissionset.hidden'),
        read: Translator.trans('permissionset.read'),
        write: Translator.trans('permissionset.write'),
      }
      return {
        audience: phase.audience,
        id: phase.id,
        name: phase.name,
        orderInAudience: phase.orderInAudience,
        permissionSetLabel: permissionSetLabels[phase.permissionSet] || phase.permissionSet,
      }
    },
  },

  mounted () {
    this.fetchPhases()
  },
}
</script>
