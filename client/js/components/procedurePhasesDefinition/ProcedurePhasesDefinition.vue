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

        <addon-wrapper
          hook-name="phase.create.form"
          @change="updateAddonPayload"
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
        <div class="overflow-x-auto pb-3">
          <dp-data-table
            :header-fields="headerFields"
            :items="internalPhaseDefinitions"
            density="spacious"
            track-by="id"
            has-borders
            is-resizable
          >

            <template v-slot:phaseCode="phase">
              <addon-wrapper
                :addon-props="{ phaseId: phase.id }"
                hook-name="phase.list.fields"
              />
            </template>
          </dp-data-table>
        </div>
      </dp-accordion>

      <dp-accordion
        v-if="!isInitiallyLoading"
        :title="Translator.trans('audience.external')"
        is-open
      >
        <div class="overflow-x-auto pb-3">
          <dp-data-table
            :header-fields="headerFields"
            :items="externalPhaseDefinitions"
            density="spacious"
            track-by="id"
            has-borders
            is-resizable
          >

            <template v-slot:phaseCode="phase">
              <addon-wrapper
                :addon-props="{ phaseId: phase.id }"
                hook-name="phase.list.fields"
              />
            </template>
          </dp-data-table>
        </div>
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
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import loadAddonComponents from '@DpJs/lib/addon/loadAddonComponents'

export default {
  name: 'ProcedurePhasesDefinition',

  components: {
    AddonWrapper,
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
      addonPayload: {
        attributes: null,
        parentRelationshipName: '',
        resourceType: '',
        value: '',
      },
      hasAttemptedSubmit: false,
      isAddonActive: false,
      isCreating: false,
      isInitiallyLoading: true,
      isLoading: false,
      newPhase: {
        audience: '',
        name: '',
        participationState: null,
        permissionSet: '',
      },
      phaseDefinitions: [],
    }
  },

  computed: {
    audienceOptions () {
      return [
        { label: Translator.trans('audience.external'), value: 'external' },
        { label: Translator.trans('audience.internal'), value: 'internal' },
      ]
    },

    externalPhaseDefinitions () {
      return this.phaseDefinitions
        .filter(phase => phase.audience === 'external')
        .map(phase => this.mapPhaseForDisplay(phase))
    },

    headerFields () {
      return [
        { field: 'name', label: Translator.trans('phase.name'), colWidth: '270px', initialMinWidth: 270 },
        { field: 'permissionSetLabel', label: Translator.trans('permissionset.label'), colWidth: '270px', initialMinWidth: 270 },
        { field: 'participationStateLabel', label: Translator.trans('participation.state.finished'), colWidth: '160px', initialMinWidth: 160 },
        ...(this.isAddonActive ? [{ field: 'phaseCode', label: Translator.trans('procedure.phase.code'), colWidth: '160px', initialMinWidth: 160 }] : []),
      ]
    },

    internalPhaseDefinitions () {
      return this.phaseDefinitions
        .filter(phase => phase.audience === 'internal')
        .map(phase => this.mapPhaseForDisplay(phase))
    },

    isDuplicateName () {
      const trimmedName = this.newPhase.name.trim()

      if (trimmedName.length === 0) {
        return false
      }

      return this.phaseDefinitions.some(phase =>
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
    createAddonPayload (parentId) {
      const { attributes, parentRelationshipName, resourceType } = this.addonPayload

      return {
        type: resourceType,
        attributes,
        relationships: {
          [parentRelationshipName]: {
            data: {
              type: 'ProcedurePhaseDefinition',
              id: parentId,
            },
          },
        },
      }
    },

    createPhase () {
      this.isLoading = true
      let codeFailed = false

      dpApi.post(Routing.generate('api_resource_create', { resourceType: 'ProcedurePhaseDefinition' }), {}, {
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
        .then(response => {
          const newPhaseId = response.data.data.id

          if (!this.addonPayload.value) {
            return null
          }

          return dpApi.post(
            Routing.generate('api_resource_create', { resourceType: this.addonPayload.resourceType }),
            {},
            { data: this.createAddonPayload(newPhaseId) },
          )
            .catch(codeErr => {
              console.error(codeErr)
              codeFailed = true
            })
        })
        .then(() => {
          if (codeFailed) {
            dplan.notify.error(Translator.trans('phase.created.code.failed'))
          } else {
            dplan.notify.confirm(Translator.trans('phase.created.success'))
          }

          this.fetchPhaseDefinitions()
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

    fetchPhaseDefinitions () {
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
          this.phaseDefinitions = data.data.map(item => ({
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

    detectPhaseListAddon () {
      loadAddonComponents('phase.list.fields')
        .then(addons => {
          this.isAddonActive = addons.length > 0
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
      this.addonPayload = {
        attributes: null,
        parentRelationshipName: '',
        resourceType: '',
        value: '',
      }
      this.newPhase = {
        audience: '',
        name: '',
        participationState: null,
        permissionSet: '',
      }
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

    updateAddonPayload (payload) {
      this.addonPayload = payload
    },
  },

  mounted () {
    this.fetchPhaseDefinitions()
    this.detectPhaseListAddon()
  },
}
</script>
