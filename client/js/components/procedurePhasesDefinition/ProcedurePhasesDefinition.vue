<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    <h1>{{ Translator.trans('phases.currently.defined') }}</h1>

    <div class="space-stack-m mt-4">
      <div
        v-if="!isCreating"
        class="text-right"
      >
        <dp-button
          :text="Translator.trans('phase.create')"
          @click="isCreating = true"
        />
      </div>

      <div
        v-else
        class="border rounded-sm space-stack-m space-inset-m relative"
        data-dp-validate="phaseForm"
      >
        <dp-loading
          v-if="isLoading"
          overlay
        />

        <dp-input
          id="phaseName"
          v-model="newPhase.name"
          :class="{ '[&_input]:border-status-failed': showErrorInputStyle }"
          :label="{ text: Translator.trans('phase.name') }"
          required
        />

        <dp-select
          id="phaseAudience"
          v-model="newPhase.audience"
          :label="{ text: Translator.trans('audience') }"
          :options="audienceOptions"
          required
        />

        <dp-select
          id="phasePermissionSet"
          v-model="newPhase.permissionSet"
          :label="{ text: Translator.trans('permissionset.label') }"
          :options="permissionSetOptions"
          required
        />

        <fieldset>
          <legend class="sr-only">
            {{ Translator.trans('participation.state.radio.label') }}
          </legend>

          <div class="flex gap-4">
            <dp-radio
              id="phaseParticipationStateNotFinished"
              :checked="newPhase.participationState !== 'finished'"
              :label="{ text: Translator.trans('participation.state.not.finished') }"
              name="phaseParticipationState"
              value=""
              @change="setParticipationState(null)"
            />

            <dp-radio
              id="phaseParticipationStateFinished"
              :checked="newPhase.participationState === 'finished'"
              :label="{ text: Translator.trans('participation.state.finished') }"
              name="phaseParticipationState"
              value="finished"
              @change="setParticipationState('finished')"
            />
          </div>
        </fieldset>

        <dp-button-row
          :busy="isLoading"
          primary
          secondary
          @primary-action="submitForm()"
          @secondary-action="resetForm()"
        />
      </div>

      <dp-accordion
        v-if="!isInitiallyLoading"
        :title="Translator.trans('audience.internal')"
        is-open
      >
        <dp-data-table
          :header-fields="headerFields"
          :items="internalPhases"
          track-by="id"
        />
      </dp-accordion>

      <dp-accordion
        v-if="!isInitiallyLoading"
        :title="Translator.trans('audience.external')"
        is-open
      >
        <dp-data-table
          :header-fields="headerFields"
          :items="externalPhases"
          track-by="id"
        />
      </dp-accordion>

      <dp-loading v-if="isInitiallyLoading" />
    </div>
  </div>
</template>

<script>
import {
  DpAccordion,
  dpApi,
  DpButton,
  DpButtonRow,
  DpDataTable,
  DpInput,
  DpLoading,
  DpRadio,
  DpSelect,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'

export default {
  name: 'ProcedurePhasesDefinition',

  components: {
    DpAccordion,
    DpButton,
    DpButtonRow,
    DpDataTable,
    DpInput,
    DpLoading,
    DpRadio,
    DpSelect,
  },

  mixins: [dpValidateMixin],

  data () {
    return {
      hasAttemptedSubmit: false,
      headerFields: [
        { field: 'name', label: Translator.trans('phase.name') },
        { field: 'permissionSetLabel', label: Translator.trans('permissionset.label') },
        { field: 'participationStateLabel', label: Translator.trans('participation.state.finished') },
      ],
      isCreating: false,
      isInitiallyLoading: true,
      isLoading: false,
      newPhase: {
        name: '',
        audience: '',
        permissionSet: '',
        participationState: null,
      },
      phases: [],
    }
  },

  computed: {
    audienceOptions () {
      return [
        { label: Translator.trans('audience.external'), value: 'external' },
        { label: Translator.trans('audience.internal'), value: 'internal' },
      ]
    },

    externalPhases () {
      return this.phases
        .filter(phase => phase.audience === 'external')
        .map(phase => this.mapPhaseForDisplay(phase))
    },

    internalPhases () {
      return this.phases
        .filter(phase => phase.audience === 'internal')
        .map(phase => this.mapPhaseForDisplay(phase))
    },

    isDuplicateName () {
      const trimmedName = this.newPhase.name.trim()

      if (trimmedName.length === 0) {
        return false
      }

      return this.phases.some(phase =>
        phase.audience === this.newPhase.audience &&
        phase.name.trim().toLowerCase() === trimmedName.toLowerCase(),
      )
    },

    permissionSetOptions () {
      return [
        { label: Translator.trans('permissionset.hidden'), value: 'hidden' },
        { label: Translator.trans('permissionset.read'), value: 'read' },
        { label: Translator.trans('permissionset.write'), value: 'write' },
      ]
    },

    showErrorInputStyle () {
      return this.hasAttemptedSubmit && this.isDuplicateName
    },
  },

  methods: {
    createPhase () {
      this.isLoading = true

      dpApi.post(Routing.generate('api_resource_create', {
        resourceType: 'ProcedurePhaseDefinition',
      }), {}, {
        data: {
          type: 'ProcedurePhaseDefinition',
          attributes: {
            audience: this.newPhase.audience,
            name: this.newPhase.name.trim(),
            participationState: this.newPhase.participationState,
            permissionSet: this.newPhase.permissionSet,
          },
        },
      })
        .then(() => {
          dplan.notify.confirm(Translator.trans('phase.created.success'))

          this.fetchPhases()
          this.resetForm()
        })
        .catch(err => {
          console.error(err)
          dplan.notify.error(Translator.trans('error.generic'))
        })
        .finally(() => {
          this.isLoading = false
        })
    },

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
          ].join(','),
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
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
        .finally(() => {
          this.isInitiallyLoading = false
        })
    },

    mapPhaseForDisplay (phase) {
      return {
        id: phase.id,
        name: phase.name,
        orderInAudience: phase.orderInAudience,
        permissionSetLabel: this.permissionSetOptions.find(option => option.value === phase.permissionSet)?.label || phase.permissionSet,
        participationStateLabel: phase.participationState === 'finished' ? Translator.trans('yes') : Translator.trans('no'),
      }
    },

    resetForm () {
      this.isCreating = false
      this.hasAttemptedSubmit = false
      this.newPhase = { name: '', audience: '', permissionSet: '', participationState: null }
    },

    setParticipationState (value) {
      this.newPhase.participationState = value
    },

    submitForm () {
      this.hasAttemptedSubmit = true

      if (this.isDuplicateName) {
        dplan.notify.error(Translator.trans('error.name.unique'))

        return
      }

      this.hasAttemptedSubmit = false
      this.dpValidateAction('phaseForm', () => this.createPhase(), false)
    },
  },

  mounted () {
    this.fetchPhases()
  },
}
</script>
