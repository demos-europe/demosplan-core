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
import { computed, onMounted, reactive, ref } from 'vue'
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

  setup () {
    const draftRowPayloads = reactive({})
    const editingRowId = ref(null)
    const hasAttemptedSubmit = ref(false)
    const initialRowPayloads = reactive({})
    const isAddonActive = ref(false)
    const isCreateFormAddonLoading = ref(true)
    const isCreating = ref(false)
    const isInitiallyLoading = ref(true)
    const isLoading = ref(false)
    const isSaving = ref(false)
    const newPhase = ref({
      audience: '',
      name: '',
      participationState: null,
      permissionSet: '',
    })
    const newPhaseAddonPayload = ref({
      attributes: null,
      parentRelationshipName: '',
      resourceType: '',
      value: '',
    })
    const phaseDefinitions = ref([])
    const savedRowPayloads = ref({})

    const audienceOptions = [
      { label: Translator.trans('audience.external'), value: 'external' },
      { label: Translator.trans('audience.internal'), value: 'internal' },
    ]

    const permissionSetOptions = [
      { label: Translator.trans('permissionset.hidden'), value: 'hidden' },
      { label: Translator.trans('permissionset.read'), value: 'read' },
      { label: Translator.trans('permissionset.write'), value: 'write' },
    ]

    const mapPhaseForDisplay = (phase) => ({
      id: phase.id,
      name: phase.name,
      orderInAudience: phase.orderInAudience,
      permissionSetLabel: permissionSetOptions.find(option => option.value === phase.permissionSet)?.label || phase.permissionSet,
      participationStateLabel: phase.participationState === 'finished' ? Translator.trans('yes') : Translator.trans('no'),
    })

    const audienceSections = computed(() =>
      ['internal', 'external'].map(audience => ({
        audience,
        audiencePhases: phaseDefinitions.value
          .filter(phase => phase.audience === audience)
          .map(phase => mapPhaseForDisplay(phase)),
        title: Translator.trans(`audience.${audience}`),
      })),
    )

    const headerFields = computed(() => [
      { field: 'name', label: Translator.trans('phase.name'), colWidth: '270px', initialMinWidth: 270 },
      { field: 'permissionSetLabel', label: Translator.trans('permissionset.label'), colWidth: '270px', initialMinWidth: 270 },
      { field: 'participationStateLabel', label: Translator.trans('participation.state.finished'), colWidth: '160px', initialMinWidth: 160 },
      ...(isAddonActive.value ? [{ field: 'phaseCode', label: Translator.trans('procedure.phase.code'), colWidth: '160px', initialMinWidth: 160 }] : []),
    ])

    const isDuplicateName = computed(() => {
      const trimmedName = newPhase.value.name.trim()

      if (trimmedName.length === 0) {
        return false
      }

      return phaseDefinitions.value.some(phase =>
        phase.audience === newPhase.value.audience &&
        phase.name.trim().toLowerCase() === trimmedName.toLowerCase(),
      )
    })

    const showErrorInputStyle = computed(() => hasAttemptedSubmit.value && isDuplicateName.value)

    const buildNewPhaseAddonRequest = (parentId) => {
      const { attributes, parentRelationshipName, resourceType } = newPhaseAddonPayload.value

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
    }

    const resetForm = () => {
      isCreating.value = false
      hasAttemptedSubmit.value = false
      newPhaseAddonPayload.value = {
        attributes: null,
        parentRelationshipName: '',
        resourceType: '',
        value: '',
      }
      newPhase.value = {
        audience: '',
        name: '',
        participationState: null,
        permissionSet: '',
      }
    }

    const setParticipationState = (value) => {
      newPhase.value.participationState = value
    }

    const updateAddonPayload = (payload) => {
      newPhaseAddonPayload.value = payload
    }

    const createPhase = () => {
      isLoading.value = true
      let codeFailed = false

      dpApi.post(Routing.generate('api_resource_create', { resourceType: 'ProcedurePhaseDefinition' }), {}, {
        data: {
          type: 'ProcedurePhaseDefinition',
          attributes: {
            audience: newPhase.value.audience,
            name: newPhase.value.name.trim(),
            participationState: newPhase.value.participationState,
            permissionSet: newPhase.value.permissionSet,
          },
        },
      })
        .then(response => {
          const newPhaseId = response.data.data.id
          const phaseCode = newPhaseAddonPayload.value.value

          if (!phaseCode) {
            return null
          }

          return dpApi.post(
            Routing.generate('api_resource_create', { resourceType: newPhaseAddonPayload.value.resourceType }),
            {},
            { data: buildNewPhaseAddonRequest(newPhaseId) },
          )
            .then(codeResponse => {
              // Push the new code into savedRowPayloads so the row's addon cell renders the value without waiting for cache refetch
              savedRowPayloads.value = {
                ...savedRowPayloads.value,
                [newPhaseId]: {
                  code: phaseCode,
                  resourceId: codeResponse.data.data.id,
                },
              }
            })
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

          fetchPhaseDefinitions()
          resetForm()
        })
        .catch(err => {
          console.error(err)
          dplan.notify.error(Translator.trans('error.generic'))
        })
        .finally(() => {
          isLoading.value = false
        })
    }

    const handleEditStart = (payload) => {
      draftRowPayloads[payload.phaseId] = payload
      // Clone so the snapshot can't change if `payload` is ever mutated later.
      initialRowPayloads[payload.phaseId] = structuredClone(payload)
    }

    const handleEditChange = (payload) => {
      draftRowPayloads[payload.phaseId] = payload
    }

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
    const sendSaveEditRequest = (draftPayload) => {
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
                    id: editingRowId.value,
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
    }

    const handleSaveEditClick = () => {
      const id = editingRowId.value
      const draftPayload = draftRowPayloads[id]
      const initialPayload = initialRowPayloads[id]

      if (!draftPayload || !initialPayload) {
        editingRowId.value = null

        return
      }

      if (draftPayload.value === initialPayload.value && draftPayload.resourceId === initialPayload.resourceId) {
        editingRowId.value = null

        return
      }

      if (draftPayload.isDuplicate) {
        dplan.notify.error(Translator.trans('procedure.phase.code.duplicate'))

        return
      }

      const request = sendSaveEditRequest(draftPayload)

      if (request === null) {
        editingRowId.value = null

        return
      }

      isSaving.value = true

      request
        .then(({ code, resourceId }) => {
          savedRowPayloads.value = {
            ...savedRowPayloads.value,
            [id]: {
              code,
              resourceId,
            },
          }
          editingRowId.value = null
          dplan.notify.confirm(Translator.trans('procedure.phase.code.edit.success'))
          fetchPhaseDefinitions()
        })
        .catch(err => {
          console.error(err)
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
        .finally(() => {
          isSaving.value = false
        })
    }

    const startEdit = (rowData) => {
      editingRowId.value = rowData.id
    }

    const cancelEdit = () => {
      editingRowId.value = null
    }

    const detectPhaseListAddon = () => {
      loadAddonComponents('phase.list.fields')
        .then(addons => {
          isAddonActive.value = addons.length > 0
        })
    }

    const fetchPhaseDefinitions = () => {
      isInitiallyLoading.value = true

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
          phaseDefinitions.value = data.data.map(item => ({
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
          isInitiallyLoading.value = false
        })
    }

    onMounted(() => {
      fetchPhaseDefinitions()
      detectPhaseListAddon()
    })

    return {
      audienceOptions,
      audienceSections,
      cancelEdit,
      createPhase,
      editingRowId,
      handleEditChange,
      handleEditStart,
      handleSaveEditClick,
      hasAttemptedSubmit,
      headerFields,
      isAddonActive,
      isCreateFormAddonLoading,
      isCreating,
      isDuplicateName,
      isInitiallyLoading,
      isLoading,
      isSaving,
      newPhase,
      newPhaseAddonPayload,
      permissionSetOptions,
      resetForm,
      savedRowPayloads,
      setParticipationState,
      showErrorInputStyle,
      startEdit,
      updateAddonPayload,
    }
  },

  methods: {
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
  },
}
</script>
