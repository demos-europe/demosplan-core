<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    <h1>{{ Translator.trans('procedure.phases.currently.defined') }}</h1>

    <div class="space-stack-m mt-4">
      <div
        v-if="!isCreating"
        class="text-right"
      >
        <dp-button
          :text="Translator.trans('procedure.phase.create')"
          data-cy="procedurePhases:create"
          @click="openCreateForm"
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
          :invalid="isNewPhaseNameInvalid"
          :label="{ text: Translator.trans('procedure.phase.name') }"
          data-cy="procedurePhases:createForm:name"
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
          data-cy="procedurePhases:createForm:audience"
          required
        />

        <dp-select
          id="phasePermissionSet"
          v-model="newPhase.permissionSet"
          :label="{ text: Translator.trans('permissionset.label') }"
          :options="permissionSetOptions"
          data-cy="procedurePhases:createForm:permissionSet"
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
              data-cy="procedurePhases:createForm:participationState:notFinished"
              name="phaseParticipationState"
              value=""
              @change="setParticipationState(null)"
            />

            <dp-radio
              id="phaseParticipationStateFinished"
              :checked="newPhase.participationState === 'finished'"
              :label="{ text: Translator.trans('participation.state.finished') }"
              data-cy="procedurePhases:createForm:participationState:finished"
              name="phaseParticipationState"
              value="finished"
              @change="setParticipationState('finished')"
            />
          </div>
        </fieldset>

        <dp-button-row
          :busy="isLoading"
          data-cy="procedurePhases:createForm"
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
          <div class="overflow-x-auto pb-3 has-scrollable-content">
            <dp-data-table
              :data-cy="`procedurePhases:dataTable:${section.audience}`"
              :flyout-width="flyoutWidth"
              :header-fields="headerFields"
              :items="section.audiencePhases"
              density="spacious"
              track-by="id"
              has-borders
              has-flyout
              is-resizable
            >
              <template v-slot:name="phase">
                <dp-input
                  v-if="editingRowId === phase.id"
                  :id="`phaseName-${phase.id}`"
                  v-model="draftCoreRowValue.name"
                  :data-cy="`procedurePhases:editName:${phase.id}`"
                  :invalid="isEditedPhaseNameInvalid"
                />

                <span v-else>{{ phase.name }}</span>
              </template>

              <template v-slot:permissionSet="phase">
                <dp-multiselect
                  v-if="editingRowId === phase.id && phase.orderInAudience !== 0"
                  :id="`phasePermissionSet-${phase.id}`"
                  :allow-empty="false"
                  :data-cy="`procedurePhases:editPermissionSet:${phase.id}`"
                  :options="permissionSetOptions"
                  :value="findPermissionSetOption(draftCoreRowValue.permissionSet)"
                  label="label"
                  track-by="value"
                  @input="option => updateCoreRowValue('permissionSet', option?.value)"
                />

                <span v-else>{{ phase.permissionSetLabel }}</span>
              </template>

              <template v-slot:participationState="phase">
                <fieldset v-if="editingRowId === phase.id && phase.orderInAudience !== 0">
                  <legend class="sr-only">
                    {{ Translator.trans('participation.state.radio.label') }}
                  </legend>

                  <div class="flex gap-4">
                    <dp-radio
                      :id="`phaseParticipationStateNotFinished-${phase.id}`"
                      :checked="draftCoreRowValue.participationState !== 'finished'"
                      :data-cy="`procedurePhases:editParticipationState:notFinished:${phase.id}`"
                      :label="{ text: Translator.trans('no') }"
                      :name="`phaseParticipationState-${phase.id}`"
                      value=""
                      @change="updateCoreRowValue('participationState', null)"
                    />

                    <dp-radio
                      :id="`phaseParticipationStateFinished-${phase.id}`"
                      :checked="draftCoreRowValue.participationState === 'finished'"
                      :data-cy="`procedurePhases:editParticipationState:finished:${phase.id}`"
                      :label="{ text: Translator.trans('yes') }"
                      :name="`phaseParticipationState-${phase.id}`"
                      value="finished"
                      @change="updateCoreRowValue('participationState', 'finished')"
                    />
                  </div>
                </fieldset>

                <span v-else>{{ phase.participationStateLabel }}</span>
              </template>

              <template v-slot:phaseCode="phase">
                <addon-wrapper
                  :addon-props="{
                    hasAttemptedSubmit,
                    isEditing: editingRowId === phase.id,
                    phaseId: phase.id,
                    savedRowPayload: savedAddonRowPayloads[phase.id] || null,
                  }"
                  hook-name="phase.list.fields"
                  @edit-change="handleAddonEditChange"
                  @edit-start="handleAddonEditStart"
                />
              </template>

              <template v-slot:flyout="rowData">
                <div class="flex justify-center gap-3 py-[15px]">
                  <template v-if="editingRowId !== rowData.id">
                    <button
                      :aria-label="Translator.trans('item.edit')"
                      :data-cy="`procedurePhases:edit:${rowData.id}`"
                      :title="Translator.trans('edit')"
                      class="btn--blank o-link--default"
                      @click="startEdit(rowData)"
                    >
                      <dp-icon
                        aria-hidden="true"
                        icon="edit"
                      />
                    </button>

                    <button
                      v-if="rowData.orderInAudience !== 0"
                      :aria-label="Translator.trans('item.delete')"
                      :data-cy="`procedurePhases:delete:${rowData.id}`"
                      :title="Translator.trans('delete')"
                      class="btn--blank o-link--default"
                      @click="deletePhase(rowData.id)"
                    >
                      <dp-icon
                        aria-hidden="true"
                        icon="delete"
                      />
                    </button>
                  </template>

                  <template v-else>
                    <button
                      :aria-label="Translator.trans('save')"
                      :data-cy="`procedurePhases:saveEdit:${rowData.id}`"
                      :disabled="isSaving"
                      :title="Translator.trans('save')"
                      class="btn--blank o-link--default"
                      @click="handleSaveEditClick"
                    >
                      <dp-icon
                        aria-hidden="true"
                        icon="check"
                      />
                    </button>

                    <button
                      :aria-label="Translator.trans('abort')"
                      :data-cy="`procedurePhases:abortEdit:${rowData.id}`"
                      :disabled="isSaving"
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

    <dp-confirm-dialog
      ref="confirmDeleteDialog"
      :message="Translator.trans('procedure.phase.delete.confirm')"
    />
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue'
import {
  DpAccordion,
  dpApi,
  DpButton,
  DpButtonRow,
  DpConfirmDialog,
  DpDataTable,
  DpIcon,
  DpInput,
  DpLoading,
  DpMultiselect,
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
    DpConfirmDialog,
    DpDataTable,
    DpIcon,
    DpInput,
    DpLoading,
    DpMultiselect,
    DpRadio,
    DpSelect,
  },

  mixins: [dpValidateMixin],

  setup () {
    // *** SHARED LOGIC ***
    const audienceOptions = [
      { label: Translator.trans('audience.external'), value: 'external' },
      { label: Translator.trans('audience.internal'), value: 'internal' },
    ]

    const permissionSetOptions = [
      { label: Translator.trans('permissionset.hidden'), value: 'hidden' },
      { label: Translator.trans('permissionset.read'), value: 'read' },
      { label: Translator.trans('permissionset.write'), value: 'write' },
    ]

    const flyoutWidth = ref('80px')
    const hasAttemptedSubmit = ref(false)

    const isNewPhaseNameInvalid = computed(() =>
      hasAttemptedSubmit.value && isNewPhaseNameDuplicate.value,
    )

    const isEditedPhaseNameInvalid = computed(() =>
      hasAttemptedSubmit.value && (isEditedPhaseNameDuplicate.value || isEditedPhaseNameEmpty.value),
    )

    const findPermissionSetOption = (value) =>
      permissionSetOptions.find(option => option.value === value) || null

    const isNameTakenInAudience = ({ name, audience, excludeId = null }) => {
      const trimmedName = name.trim()

      if (trimmedName.length === 0) {
        return false
      }

      return phaseDefinitions.value.some(phase =>
        phase.id !== excludeId &&
        phase.audience === audience &&
        phase.name.trim().toLowerCase() === trimmedName.toLowerCase(),
      )
    }

    // *** PHASE LIST LOGIC ***
    const isAddonActive = ref(false)
    const isInitiallyLoading = ref(true)
    const phaseDefinitions = ref([])
    const savedAddonRowPayloads = ref({})

    const audienceSections = computed(() =>
      ['internal', 'external'].map(audience => ({
        audience,
        audiencePhases: phaseDefinitions.value
          .filter(phase => phase.audience === audience)
          .map(phase => mapPhaseToRow(phase)),
        title: Translator.trans(`audience.${audience}`),
      })),
    )

    const headerFields = computed(() => [
      { field: 'name', label: Translator.trans('procedure.phase.name'), colWidth: '270px', initialMinWidth: 270 },
      { field: 'permissionSet', label: Translator.trans('permissionset.label'), colWidth: '270px', initialMinWidth: 270 },
      { field: 'participationState', label: Translator.trans('participation.state.finished'), colWidth: '160px', initialMinWidth: 160 },
      ...(isAddonActive.value ? [{ field: 'phaseCode', label: Translator.trans('procedure.phase.code'), colWidth: '160px', initialMinWidth: 160 }] : []),
    ])

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
        filter: {
          notDeleted: {
            condition: {
              path: 'isDeleted',
              value: 0,
            },
          },
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

    const mapPhaseToRow = (phase) => ({
      id: phase.id,
      name: phase.name,
      orderInAudience: phase.orderInAudience,
      participationState: phase.participationState,
      participationStateLabel: phase.participationState === 'finished' ? Translator.trans('yes') : Translator.trans('no'),
      permissionSet: phase.permissionSet,
      permissionSetLabel: findPermissionSetOption(phase.permissionSet)?.label || phase.permissionSet,
    })

    // *** CREATE PHASE LOGIC ***
    const isCreateFormAddonLoading = ref(true)
    const isCreating = ref(false)
    const isLoading = ref(false)
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

    const isNewPhaseNameDuplicate = computed(() => isNameTakenInAudience({
      name: newPhase.value.name,
      audience: newPhase.value.audience,
    }))

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

    const openCreateForm = () => {
      editingRowId.value = null
      hasAttemptedSubmit.value = false
      isCreating.value = true
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
      let phaseCodeFailed = false

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
            .then(phaseCodeResponse => {
              // Push the new code into savedAddonRowPayloads so the row's addon cell renders the value without waiting for cache refetch
              savedAddonRowPayloads.value = {
                ...savedAddonRowPayloads.value,
                [newPhaseId]: {
                  code: phaseCode,
                  resourceId: phaseCodeResponse.data.data.id,
                },
              }
            })
            .catch(phaseCodeErr => {
              console.error(phaseCodeErr)
              phaseCodeFailed = true
            })
        })
        .then(() => {
          if (phaseCodeFailed) {
            dplan.notify.error(Translator.trans('procedure.phase.code.create.failed'))
          } else {
            dplan.notify.confirm(Translator.trans('procedure.phase.create.success'))
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

    // *** EDIT PHASE LOGIC ***
    const draftAddonRowPayloads = reactive({})
    const draftCoreRowValue = ref({ name: '', participationState: null, permissionSet: '' })
    const editingRowId = ref(null)
    const initialAddonRowPayloads = reactive({})
    const initialCoreRowValue = ref({ name: '', participationState: null, permissionSet: '' })
    const isSaving = ref(false)

    const isEditedPhaseNameDuplicate = computed(() => {
      const editingPhase = phaseDefinitions.value.find(phase => phase.id === editingRowId.value)

      return editingPhase ?
        isNameTakenInAudience({
          name: draftCoreRowValue.value.name,
          audience: editingPhase.audience,
          excludeId: editingRowId.value,
        }) :
        false
    })

    const isEditedPhaseNameEmpty = computed(() =>
      editingRowId.value !== null && draftCoreRowValue.value.name.trim() === '',
    )

    const startEdit = (rowData) => {
      if (isCreating.value) {
        resetForm()
      }

      hasAttemptedSubmit.value = false

      draftCoreRowValue.value = {
        name: rowData.name,
        participationState: rowData.participationState,
        permissionSet: rowData.permissionSet,
      }
      initialCoreRowValue.value = { ...draftCoreRowValue.value }

      editingRowId.value = rowData.id
    }

    const cancelEdit = () => {
      draftCoreRowValue.value = { ...initialCoreRowValue.value }
      editingRowId.value = null
    }

    const handleAddonEditStart = (payload) => {
      draftAddonRowPayloads[payload.phaseId] = payload
      // Clone so the snapshot can't change if `payload` is ever mutated later.
      initialAddonRowPayloads[payload.phaseId] = structuredClone(payload)
    }

    const handleAddonEditChange = (payload) => {
      draftAddonRowPayloads[payload.phaseId] = payload
    }

    const updateCoreRowValue = (field, value) => {
      draftCoreRowValue.value[field] = value
    }

    /*
     * Picks the right HTTP request on edit based on the row's draft payload:
     *   - empty value, no record yet  → skip (edit opened on a blank row, saved without typing)
     *   - empty value, record exists  → DELETE
     *   - new value, no record yet    → POST
     *   - new value, record exists    → PATCH
     * Resolves to { code, resourceId } — the new state core stores in
     * `savedAddonRowPayloads` so the cell can re-render without a refetch.
     *
     * The PATCH body intentionally omits the parent relationship — the
     * backend only accepts that field on create, not on update.
     */
    const sendAddonEditRequest = (draftPayload) => {
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

    /*
     * Builds the PATCH for the core fields, sending only those that changed.
     * The configuration phase (orderInAudience 0) may only change its
     * name — permissionSet and participationState cannot be edited.
     * Returns null when nothing changed (so no request is fired).
     */
    const sendCoreEditRequest = (id) => {
      const draft = draftCoreRowValue.value
      const initial = initialCoreRowValue.value
      const isConfigurationPhase = phaseDefinitions.value.find(phase => phase.id === id)?.orderInAudience === 0
      const attributes = {}
      const trimmedName = draft.name.trim()

      if (trimmedName !== initial.name) {
        attributes.name = trimmedName
      }

      if (!isConfigurationPhase) {
        if (draft.permissionSet !== initial.permissionSet) {
          attributes.permissionSet = draft.permissionSet
        }

        if (draft.participationState !== initial.participationState) {
          attributes.participationState = draft.participationState
        }
      }

      if (Object.keys(attributes).length === 0) {
        return null
      }

      return dpApi.patch(
        Routing.generate('api_resource_update', {
          resourceType: 'ProcedurePhaseDefinition',
          resourceId: id,
        }),
        {},
        {
          data: {
            type: 'ProcedurePhaseDefinition',
            id,
            attributes,
          },
        },
      )
    }

    const handleSaveEditClick = () => {
      hasAttemptedSubmit.value = true

      if (isEditedPhaseNameDuplicate.value) {
        dplan.notify.error(Translator.trans('error.name.unique'))

        return
      }

      if (isEditedPhaseNameEmpty.value) {
        dplan.notify.error(Translator.trans('error.name.required'))

        return
      }

      const id = editingRowId.value
      const draftPayload = draftAddonRowPayloads[id]
      const initialPayload = initialAddonRowPayloads[id]

      let addonRequest = null

      if (draftPayload && initialPayload) {
        if (draftPayload.isDuplicate) {
          dplan.notify.error(Translator.trans('procedure.phase.code.duplicate'))

          return
        }

        const hasPhaseCodeChanged = draftPayload.value !== initialPayload.value || draftPayload.resourceId !== initialPayload.resourceId

        if (hasPhaseCodeChanged) {
          addonRequest = sendAddonEditRequest(draftPayload)
        }
      }

      const coreRequest = sendCoreEditRequest(id)

      if (!addonRequest && !coreRequest) {
        editingRowId.value = null

        return
      }

      isSaving.value = true

      Promise.all([
        addonRequest ?
          addonRequest.then(({ code, resourceId }) => {
            savedAddonRowPayloads.value = {
              ...savedAddonRowPayloads.value,
              [id]: {
                code,
                resourceId,
              },
            }
          }) :
          Promise.resolve(),
        coreRequest || Promise.resolve(),
      ])
        .then(() => {
          editingRowId.value = null
          dplan.notify.confirm(Translator.trans(
            coreRequest ? 'confirm.all.changes.saved' : 'procedure.phase.code.edit.success',
          ))

          if (coreRequest) {
            phaseDefinitions.value = phaseDefinitions.value.map(phase =>
              phase.id === id ?
                {
                  ...phase,
                  name: draftCoreRowValue.value.name.trim(),
                  participationState: draftCoreRowValue.value.participationState,
                  permissionSet: draftCoreRowValue.value.permissionSet,
                } :
                phase,
            )
          }
        })
        .catch(err => {
          console.error(err)
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
        .finally(() => {
          isSaving.value = false
        })
    }

    // *** DELETE PHASE LOGIC ***
    const confirmDeleteDialog = ref(null)

    const deletePhase = async (id) => {
      const isConfirmed = await confirmDeleteDialog.value.open()

      if (!isConfirmed) {
        return
      }

      try {
        await dpApi.patch(
          Routing.generate('api_resource_update', { resourceType: 'ProcedurePhaseDefinition', resourceId: id }),
          {},
          { data: { type: 'ProcedurePhaseDefinition', id, attributes: { isDeleted: true } } },
        )
        phaseDefinitions.value = phaseDefinitions.value.filter(phase => phase.id !== id)
        dplan.notify.confirm(Translator.trans('procedure.phase.delete.success'))
      } catch (err) {
        if (!err.data?.meta?.messages) {
          console.error(err)
          // Backend already surfaced its specific message via meta.messages; only fall back to a generic toast when it didn't.
          dplan.notify.error(Translator.trans('error.api.generic'))
        }
      }
    }

    onMounted(() => {
      fetchPhaseDefinitions()
      detectPhaseListAddon()
    })

    return {
      audienceOptions,
      audienceSections,
      cancelEdit,
      confirmDeleteDialog,
      createPhase,
      deletePhase,
      draftCoreRowValue,
      editingRowId,
      findPermissionSetOption,
      flyoutWidth,
      handleAddonEditChange,
      handleAddonEditStart,
      handleSaveEditClick,
      hasAttemptedSubmit,
      headerFields,
      isCreateFormAddonLoading,
      isCreating,
      isEditedPhaseNameInvalid,
      isInitiallyLoading,
      isLoading,
      isNewPhaseNameDuplicate,
      isNewPhaseNameInvalid,
      isSaving,
      newPhase,
      newPhaseAddonPayload,
      openCreateForm,
      permissionSetOptions,
      resetForm,
      savedAddonRowPayloads,
      setParticipationState,
      startEdit,
      updateAddonPayload,
      updateCoreRowValue,
    }
  },

  methods: {
    submitForm () {
      this.hasAttemptedSubmit = true

      if (this.isNewPhaseNameDuplicate) {
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
