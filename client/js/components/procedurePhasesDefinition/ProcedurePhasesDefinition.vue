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

        <dp-loading v-if="isCreateFormAddonLoading" />

        <addon-wrapper
          :addon-props="{ hasAttemptedSubmit }"
          hook-name="phase.create.form"
          @addons:loaded="isCreateFormAddonLoading = false"
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

      <dp-loading v-if="isInitiallyLoading" />

      <template v-else>
        <dp-accordion
          v-for="section in audienceSections"
          :key="section.audience"
          :title="section.title"
          is-open
        >
          <div class="overflow-x-auto pb-3">
            <dp-data-table
              :has-flyout="isAddonActive"
              :header-fields="headerFields"
              :items="section.audiencePhases"
              density="spacious"
              track-by="id"
              has-borders
              is-resizable
            >

              <template v-slot:phaseCode="phase">
                <addon-wrapper
                  :addon-props="{
                    isEditing: editingRowId === phase.id,
                    phaseId: phase.id,
                    savedRowPayload: savedRowPayloads[phase.id] || null,
                  }"
                  hook-name="phase.list.fields"
                  @edit-change="handleEditChange"
                  @edit-start="handleEditStart"
                />
              </template>

              <template v-slot:flyout="rowData">
                <div class="flex float-right py-[15px]">
                  <button
                    v-if="editingRowId !== rowData.id"
                    :aria-label="Translator.trans('item.edit')"
                    :title="Translator.trans('edit')"
                    class="btn--blank o-link--default"
                    @click="startEdit(rowData)"
                  >
                    <dp-icon
                      aria-hidden="true"
                      icon="edit"
                    />
                  </button>

                  <template v-else>
                    <button
                      :aria-label="Translator.trans('save')"
                      :disabled="isSaving"
                      :title="Translator.trans('save')"
                      class="btn--blank o-link--default mr-1"
                      @click="handleSaveEditClick"
                    >
                      <dp-icon
                        aria-hidden="true"
                        icon="check"
                      />
                    </button>

                    <button
                      :aria-label="Translator.trans('abort')"
                      :title="Translator.trans('abort')"
                      class="btn--blank o-link--default"
                      @click="cancelEdit"
                    >
                      <dp-icon
                        aria-hidden="true"
                        icon="xmark"
                      />
                    </button>
                  </template>
                </div>
              </template>
            </dp-data-table>
          </div>
        </dp-accordion>
      </template>
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
  DpIcon,
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
    DpIcon,
    DpInput,
    DpLoading,
    DpRadio,
    DpSelect,
  },

  mixins: [dpValidateMixin],

  data () {
    return {
      draftRowPayloads: {},
      editingRowId: null,
      hasAttemptedSubmit: false,
      initialRowPayloads: {},
      isAddonActive: false,
      isCreateFormAddonLoading: true,
      isCreating: false,
      isInitiallyLoading: true,
      isLoading: false,
      isSaving: false,
      newPhase: {
        audience: '',
        name: '',
        participationState: null,
        permissionSet: '',
      },
      newPhaseAddonPayload: {
        attributes: null,
        parentRelationshipName: '',
        resourceType: '',
        value: '',
      },
      phaseDefinitions: [],
      savedRowPayloads: {},
    }
  },

  computed: {
    audienceOptions () {
      return [
        { label: Translator.trans('audience.external'), value: 'external' },
        { label: Translator.trans('audience.internal'), value: 'internal' },
      ]
    },

    audienceSections () {
      return ['internal', 'external'].map(audience => ({
        audience,
        audiencePhases: this.phaseDefinitions
          .filter(phase => phase.audience === audience)
          .map(phase => this.mapPhaseForDisplay(phase)),
        title: Translator.trans(`audience.${audience}`),
      }))
    },

    headerFields () {
      return [
        { field: 'name', label: Translator.trans('phase.name'), colWidth: '270px', initialMinWidth: 270 },
        { field: 'permissionSetLabel', label: Translator.trans('permissionset.label'), colWidth: '270px', initialMinWidth: 270 },
        { field: 'participationStateLabel', label: Translator.trans('participation.state.finished'), colWidth: '160px', initialMinWidth: 160 },
        ...(this.isAddonActive ? [{ field: 'phaseCode', label: Translator.trans('procedure.phase.code'), colWidth: '160px', initialMinWidth: 160 }] : []),
      ]
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
    buildNewPhaseAddonRequest (parentId) {
      const { attributes, parentRelationshipName, resourceType } = this.newPhaseAddonPayload

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

    cancelEdit () {
      this.editingRowId = null
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

          if (!this.newPhaseAddonPayload.value) {
            return null
          }

          return dpApi.post(
            Routing.generate('api_resource_create', { resourceType: this.newPhaseAddonPayload.resourceType }),
            {},
            { data: this.buildNewPhaseAddonRequest(newPhaseId) },
          )
            .catch(codeErr => {
              console.error(codeErr)
              codeFailed = true
            })
        })
        .then(() => {
          if (codeFailed) {
            dplan.notify.error(Translator.trans('phase.create.code.failed'))
          } else {
            dplan.notify.confirm(Translator.trans('phase.create.success'))
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

    detectPhaseListAddon () {
      loadAddonComponents('phase.list.fields')
        .then(addons => {
          this.isAddonActive = addons.length > 0
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

    handleEditChange (payload) {
      this.draftRowPayloads[payload.phaseId] = payload
    },

    handleEditStart (payload) {
      this.draftRowPayloads[payload.phaseId] = payload
      // Clone so the snapshot can't change if `payload` is ever mutated later.
      this.initialRowPayloads[payload.phaseId] = structuredClone(payload)
    },

    handleSaveEditClick () {
      const id = this.editingRowId
      const draftPayload = this.draftRowPayloads[id]
      const initialPayload = this.initialRowPayloads[id]

      if (!draftPayload || !initialPayload) {
        this.editingRowId = null

        return
      }

      if (draftPayload.value === initialPayload.value && draftPayload.resourceId === initialPayload.resourceId) {
        this.editingRowId = null

        return
      }

      if (draftPayload.isDuplicate) {
        dplan.notify.error(Translator.trans('procedure.phase.code.duplicate'))

        return
      }

      const request = this.sendSaveEditRequest(draftPayload)

      if (request === null) {
        this.editingRowId = null

        return
      }

      this.isSaving = true

      request
        .then(({ code, resourceId }) => {
          this.savedRowPayloads = {
            ...this.savedRowPayloads,
            [id]: {
              code,
              resourceId,
            },
          }
          this.editingRowId = null
          dplan.notify.confirm(Translator.trans('procedure.phase.code.edit.success'))
          this.fetchPhaseDefinitions()
        })
        .catch(err => {
          console.error(err)
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
        .finally(() => {
          this.isSaving = false
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
      this.newPhaseAddonPayload = {
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

    /*
     * Picks the right HTTP request on edit based on the row's draft payload:
     *   - empty value, no record yet  → skip (edit opened on a blank row, saved without typing)
     *   - empty value, record exists  → DELETE
     *   - new value, no record yet    → POST
     *   - new value, record exists    → PATCH
     * Resolves to { code, resourceId } — the new state core stores in
     * `savedRowPayloads` so the cell can re-render without a refetch.
     *
     * The PATCH body intentionally omits the parent relationship — the
     * backend only accepts that field on create, not on update.
     */
    sendSaveEditRequest (draftPayload) {
      const { attributes, parentRelationshipName, resourceId, resourceType, value } = draftPayload

      if (value === '' && resourceId === null) {
        return null
      }

      if (value === '' && resourceId !== null) {
        return dpApi.delete(
          Routing.generate('api_resource_delete', {
            resourceType,
            resourceId,
          }),
        ).then(() => ({
          code: '',
          resourceId: null,
        }))
      }

      if (value !== '' && resourceId === null) {
        return dpApi.post(
          Routing.generate('api_resource_create', { resourceType }),
          {},
          {
            data: {
              type: resourceType,
              attributes,
              relationships: {
                [parentRelationshipName]: {
                  data: {
                    type: 'ProcedurePhaseDefinition',
                    id: this.editingRowId,
                  },
                },
              },
            },
          },
        ).then(response => ({
          code: value,
          resourceId: response.data.data.id,
        }))
      }

      return dpApi.patch(
        Routing.generate('api_resource_update', {
          resourceType,
          resourceId,
        }),
        {},
        {
          data: {
            type: resourceType,
            id: resourceId,
            attributes,
          },
        },
      ).then(() => ({
        code: value,
        resourceId,
      }))
    },

    setParticipationState (value) {
      this.newPhase.participationState = value
    },

    startEdit (rowData) {
      this.editingRowId = rowData.id
    },

    submitForm () {
      this.hasAttemptedSubmit = true

      if (this.isDuplicateName) {
        dplan.notify.error(Translator.trans('error.name.unique'))

        return
      }

      if (this.newPhaseAddonPayload.isDuplicate) {
        dplan.notify.error(Translator.trans('procedure.phase.code.duplicate'))

        return
      }

      this.hasAttemptedSubmit = false
      this.dpValidateAction('phaseForm', () => this.createPhase(), false)
    },

    updateAddonPayload (payload) {
      this.newPhaseAddonPayload = payload
    },
  },

  mounted () {
    this.fetchPhaseDefinitions()
    this.detectPhaseListAddon()
  },
}
</script>
