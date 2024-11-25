<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="space-stack-s">
    <dp-inline-notification
      data-cy="places:editInfo"
      dismissible
      :dismissible-key="helpTextDismissibleKey"
      :message="helpText"
      type="info" />
    <div
      v-if="!addNewPlace"
      class="text-right">
      <dp-button
        data-cy="places:addPlace"
        @click="addNewPlace = true"
        :text="Translator.trans('places.addPlace')" />
    </div>
    <div
      v-if="addNewPlace"
      class="relative"
      data-dp-validate="addNewPlaceForm">
      <dp-loading
        v-if="isLoading"
        overlay />
      <div class="border rounded space-stack-m space-inset-m">
        <dp-input
          id="newPlaceName"
          data-cy="places:newPlaceName"
          v-model="newPlace.name"
          :label="{
            text: Translator.trans('name')
          }"
          maxlength="250"
          required />
        <dp-input
          id="newPlaceDescription"
          data-cy="places:newPlaceDescription"
          v-model="newPlace.description"
          :label="{
            text: Translator.trans('description')
          }"
          maxlength="250" />
        <dp-checkbox
          id="newPlaceSolved"
          v-model="newPlace.solved"
          :label="{
            text: Translator.trans('completed')
          }" />
        <dp-button-row
          :busy="isLoading"
          data-cy="addNewPlace"
          primary
          secondary
          @primary-action="dpValidateAction('addNewPlaceForm', () => saveNewPlace(newPlace), false)"
          @secondary-action="addNewPlace = false" />
      </div>
    </div>
    <dp-data-table
      v-if="!isInitiallyLoading"
      data-cy="placesTable"
      data-dp-validate="placesTable"
      has-flyout
      :header-fields="headerFields"
      is-draggable
      :items="places"
      @changed-order="changeManualsort"
      track-by="id">
      <template v-slot:header-solved="headerData">
        {{ headerData.label }}
        <dp-contextual-help
          class="float-right u-mt-0_125"
          :text="Translator.trans('statement.solved.hint')" />
      </template>
      <template v-slot:name="rowData">
        <div
          v-if="!rowData.edit"
          v-text="rowData.name" />
        <dp-input
          v-else
          id="editPlaceName"
          data-cy="places:editPlaceName"
          maxlength="250"
          required
          v-model="newRowData.name" />
      </template>
      <template v-slot:description="rowData">
        <div
          v-if="!rowData.edit"
          v-text="rowData.description" />
        <dp-input
          v-else
          id="editPlaceDescription"
          data-cy="places:editPlaceDescription"
          maxlength="250"
          v-model="newRowData.description" />
      </template>
      <template v-slot:solved="rowData">
        <dp-checkbox
          :disabled="!rowData.edit"
          id="editPlaceSolved"
          :checked="rowData.edit ? newRowData.solved : rowData.solved"
          @change="checked => newRowData.solved = checked" />
      </template>
      <template v-slot:flyout="rowData">
        <div class="float-right">
          <button
            v-if="!rowData.edit"
            :aria-label="Translator.trans('item.edit')"
            class="btn--blank o-link--default"
            data-cy="places:editPlace"
            @click="editPlace(rowData)">
            <i
              class="fa fa-pencil"
              aria-hidden="true" />
          </button>
          <template v-else>
            <button
              :aria-label="Translator.trans('save')"
              class="btn--blank o-link--default u-mr-0_25"
              data-cy="places:saveEdit"
              @click="dpValidateAction('placesTable', () => updatePlace(rowData), false)">
              <dp-icon
                icon="check"
                aria-hidden="true" />
            </button>
            <button
              class="btn--blank o-link--default"
              data-cy="places:abortEdit"
              @click="abort(rowData)"
              :aria-label="Translator.trans('abort')">
              <dp-icon
                icon="xmark"
                aria-hidden="true" />
            </button>
          </template>
        </div>
      </template>
    </dp-data-table>
    <dp-loading v-else />
  </div>
</template>

<script>
import {
  checkResponse,
  dpApi,
  DpButton,
  DpButtonRow,
  DpCheckbox,
  DpContextualHelp,
  DpDataTable,
  DpIcon,
  DpInlineNotification,
  DpInput,
  DpLoading,
  dpRpc,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'

export default {
  name: 'AdministrationPlaces',

  components: {
    DpButton,
    DpButtonRow,
    DpCheckbox,
    DpContextualHelp,
    DpDataTable,
    DpIcon,
    DpInlineNotification,
    DpInput,
    DpLoading
  },

  mixins: [dpValidateMixin],

  props: {
    currentUserId: {
      type: String,
      required: true
    },

    /**
     * When displayed in the context of procedure templates (instead of procedures),
     * different content is displayed in the top notification.
     */
    isProcedureTemplate: {
      type: Boolean,
      required: true
    },

    /**
     * Passed procedure id from twig
     */
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      headerFields: [
        { field: 'name', label: Translator.trans('name'), colClass: 'u-4-of-12' },
        { field: 'description', label: Translator.trans('description'), colClass: 'u-5-of-12' },
        { field: 'solved', label: Translator.trans('completed'), colClass: 'u-2-of-12' }
      ],
      initialRowData: {},
      isInitiallyLoading: false,
      isLoading: false,
      addNewPlace: false,
      newPlace: {},
      newRowData: {},
      places: []
    }
  },

  computed: {
    helpText () {
      const procedureInfoKey = this.isProcedureTemplate ? 'places.edit.infoProcedureTemplate' : 'places.edit.infoProcedure'
      return `${Translator.trans('places.edit.info')} ${Translator.trans(procedureInfoKey)}`
    },

    helpTextDismissibleKey () {
      return `${this.currentUserId}:procedure${this.isProcedureTemplate && 'Template'}AdministrationPlacesHint`
    }
  },

  methods: {
    abort (rowData) {
      rowData.name = this.initialRowData.name
      rowData.description = this.initialRowData.description
      rowData.solved = this.initialRowData.solved
      this.newRowData = {}

      this.setEditMode(rowData.id, false)
    },

    changeManualsort ({ newIndex, oldIndex }) {
      const element = this.places.splice(oldIndex, 1)[0]

      this.places.splice(newIndex, 0, element)
      this.updateSortOrder({ id: element.id, newIndex: newIndex })
    },

    editPlace (rowData) {
      const editingPlace = this.places.find(place => place.edit === true)

      if (editingPlace) {
        // Reset row which was in editing state before
        editingPlace.name = this.initialRowData.name
        editingPlace.description = this.initialRowData.description
        editingPlace.solved = this.initialRowData.solved
        editingPlace.edit = false
      }

      // Save initial state of currently edited row
      this.initialRowData.name = rowData.name
      this.initialRowData.description = rowData.description
      this.initialRowData.solved = rowData.solved

      this.newRowData.name = rowData.name
      this.newRowData.description = rowData.description
      this.newRowData.solved = rowData.solved

      this.setEditMode(rowData.id)
    },

    fetchPlaces () {
      this.isInitiallyLoading = true

      dpApi.get(Routing.generate('api_resource_list', {
        resourceType: 'Place',
        fields: {
          Place: [
            'name',
            'description',
            'solved'
          ].join()
        },
        sort: 'sortIndex'
      }))
        .then(response => {
          const places = response.data.data

          places.forEach((place) => {
            this.places.push({
              id: place.id,
              name: place.attributes.name,
              description: place.attributes.description,
              edit: false,
              solved: place.attributes.solved || false
            })
          })
        })
        .catch(err => console.error(err))
        .finally(() => {
          this.isInitiallyLoading = false
        })
    },

    /**
     * Checks if the given place name is unique within the list of places.
     * To update an existing place the currentPlaceId is given and the
     * place with this id is excluded from the check.
     *
     * @param placeName { string }
     * @param placeId { string }
     * @returns { boolean }
     */
    isUniquePlaceName (placeName, placeId = '') {
      const identicalNames = this.places.filter(el => el.name === placeName && el.id !== placeId)
      return identicalNames.length === 0
    },

    resetNewPlaceForm () {
      this.newPlace = {}
      this.addNewPlace = false
    },

    saveNewPlace () {
      if (!this.isUniquePlaceName(this.newPlace.name)) {
        return dplan.notify.error(Translator.trans('workflow.place.error.duplication'))
      }

      this.isLoading = true
      /**
       * Persist changes in database
       */
      const payload = {
        type: 'Place',
        attributes: {
          name: this.newPlace.name,
          description: this.newPlace.description,
          solved: this.newPlace.solved
        }
      }
      dpApi.post(Routing.generate('api_resource_create', { resourceType: 'Place' }), {}, { data: payload })
        .then(response => {
          /**
           * Update local data so no additional api request is needed to fetch the updated data
           */
          const dataToUpdate = {
            name: this.newPlace.name,
            id: response.data.data.id,
            description: this.newPlace.description,
            edit: false,
            solved: this.newPlace.solved,
            sortIndex: this.places.length
          }
          this.places.push(dataToUpdate)
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(err => console.error(err))
        .finally(() => {
          this.isLoading = false
          this.resetNewPlaceForm()
        })
    },

    setEditMode (id, state = true) {
      const idx = this.places.findIndex(el => el.id === id)

      this.places[idx].edit = state
    },

    updatePlaceData (id) {
      const idx = this.places.findIndex(el => el.id === id)

      this.places[idx].name = this.newRowData.name
      this.places[idx].description = this.newRowData.description
      this.places[idx].solved = this.newRowData.solved
    },

    updatePlace (rowData) {
      if (!this.isUniquePlaceName(this.newRowData.name, rowData.id)) {
        return dplan.notify.error(Translator.trans('workflow.place.error.duplication'))
      }

      const payload = {
        data: {
          id: rowData.id,
          type: 'Place',
          attributes: {
            name: this.newRowData.name,
            description: this.newRowData.description,
            solved: this.newRowData.solved
          }
        }
      }

      dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'Place', resourceId: rowData.id }), {}, payload)
        .then(checkResponse)
        .catch((err) => console.error(err))
        .finally(response => {
          if (response?.errors?.length > 0) {
            return
          }
          dplan.notify.confirm(Translator.trans('confirm.saved'))
          this.setEditMode(rowData.id, false)
          this.updatePlaceData(rowData.id)
        })
    },

    updateSortOrder ({ id, newIndex }) {
      dpRpc(
        'workflowPlacesOfProcedure.reorder',
        {
          workflowPlaceId: id,
          newWorkflowPlaceIndex: newIndex
        },
        this.procedureId
      ).then(() => {
        dplan.notify.confirm(Translator.trans('confirm.saved'))
      })
    }
  },

  mounted () {
    this.fetchPlaces()
  }
}
</script>
