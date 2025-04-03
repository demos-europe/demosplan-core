<template>
  <div>
    <dp-inline-notification
      class="mb-4"
      data-cy="places:editInfo"
      dismissible
      :dismissible-key="helpTextDismissibleKey"
      :message="Translator.trans('segments.fields.edit.info')"
      type="info" />

    <div
      v-if="!isNewFieldFormOpen"
      class="text-right mb-4">
      <dp-button
        data-cy="segmentFields:addField"
        @click="openNewFieldForm"
        :text="Translator.trans('field.add')" />
    </div>

    <div
      v-if="isNewFieldFormOpen"
      class="relative mb-4"
      data-dp-validate="addNewFieldForm">
      <dp-loading
        v-if="isLoading"
        overlay />
      <div class="border rounded space-stack-m space-inset-m">
        <dp-input
          id="newFieldName"
          class="w-[calc(100%-26px)]"
          data-cy="segmentFields:newFieldName"
          v-model="newField.name"
          :label="{
            text: Translator.trans('name')
          }"
          maxlength="250"
          required />
        <dp-input
          id="newFieldDescription"
          class="w-[calc(100%-26px)]"
          data-cy="segmentFields:newFieldDescription"
          v-model="newField.description"
          :label="{
            text: Translator.trans('description')
          }"
          maxlength="250" />

        <div>
          <dp-label
            class="mb-1"
            required
            :text="Translator.trans('options')" />
          <dp-input
            id="newFieldOption:1"
            class="mb-2 w-[calc(100%-26px)]"
            data-cy="segmentFields:newFieldOption1"
            v-model="newField.options[0]"
            maxlength="250"
            required />
          <dp-input
            id="newFieldOption:2"
            class="mb-2 w-[calc(100%-26px)]"
            data-cy="segmentFields:newFieldOption2"
            v-model="newField.options[1]"
            maxlength="250"
            required />

          <div
            v-for="(option, idx) in additionalOptions"
            :key="`option:${idx}`">
            <div class="w-[calc(100%-26px)] inline-block mb-2">
              <dp-input
                v-model="newField.options[idx + 2]"
                :id="`option:${newField.options[idx + 2]}`"
                maxlength="250" />
            </div>
            <dp-button
              class="w-[20px] inline-block ml-1"
              hide-text
              icon="x"
              :text="Translator.trans('remove')"
              variant="subtle"
              @click="removeOptionInput(idx + 2)" />
          </div>

          <dp-button
            data-cy="segmentFields:addOption"
            icon="plus"
            variant="subtle"
            :text="Translator.trans('option.add')"
            @click="addOptionInput" />
        </div>

        <dp-button-row
          :busy="isLoading"
          data-cy="segmentFields:addNewField"
          primary
          secondary
          @primary-action="dpValidateAction('addNewFieldForm', () => saveNewField(), false)"
          @secondary-action="closeNewFieldForm" />
      </div>
    </div>

    <dp-data-table
      v-if="!isInitiallyLoading"
      data-cy="segmentFields:table"
      has-flyout
      :header-fields="headerFields"
      :items="segmentFields"
      track-by="id">
      <template v-slot:options="rowData">
        <ul>
          <li
            v-for="(option, index) in displayedOptions(rowData)"
            :key="index"
            class="mb-1"
            data-cy="segmentFields:option">
            {{ option }}
          </li>
        </ul>
      </template>
      <template v-slot:flyout="rowData">
        <div class="float-right">
          <button
            v-if="!rowData.open"
            :aria-label="Translator.trans('item.edit')"
            class="btn--blank o-link--default"
            data-cy="segmentFields:showOptions"
            :disabled="rowData.options.length < 3"
            @click="showOptions(rowData)">
            <dp-icon
              aria-hidden="true"
              icon="caret-down" />
          </button>
          <template v-else>
            <button
              :aria-label="Translator.trans('save')"
              class="btn--blank o-link--default u-mr-0_25 inline-block"
              data-cy="segmentFields:hideOptions"
              @click="hideOptions(rowData)">
              <dp-icon
                aria-hidden="true"
                icon="caret-up" />
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
  DpButton,
  DpButtonRow,
  DpDataTable,
  DpIcon,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpLoading,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions } from 'vuex'

export default {
  name: 'AdministrationSegmentsFieldsList',

  components: {
    DpButton,
    DpButtonRow,
    DpDataTable,
    DpIcon,
    DpInlineNotification,
    DpInput,
    DpLabel,
    DpLoading
  },

  mixins: [dpValidateMixin],

  props: {
    currentUserId: {
      type: String,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      headerFields: [
        { field: 'name', label: Translator.trans('segmentsFields.name'), colClass: 'u-3-of-12' },
        { field: 'options', label: Translator.trans('options'), colClass: 'u-4-of-12' },
        { field: 'description', label: Translator.trans('description'), colClass: 'u-5-of-12' }
      ],
      isInitiallyLoading: false,
      isLoading: false,
      isNewFieldFormOpen: false,
      newField: {
        name: '',
        description: '',
        options: [
          '',
          ''
        ]
      },
      segmentFields: []
    }
  },

  computed: {
    additionalOptions () {
      return this.newField.options.filter((option, index) => index > 1)
    },

    displayedOptions () {
      return (rowData) => {
        return rowData.open ? rowData.options : rowData.options.slice(0, 2)
      }
    },

    helpTextDismissibleKey () {
      return `${this.currentUserId}:procedureAdministrationSegmentsFieldsHint`
    }
  },

  methods: {
    ...mapActions('CustomField', {
      createCustomField: 'create'
    }),

    ...mapActions('AdminProcedure', {
      getAdminProcedureWithFields: 'get'
    }),

    addOptionInput () {
      this.newField.options.push('')
    },

    closeNewFieldForm () {
      this.isNewFieldFormOpen = false
    },

    fetchSegmentFields () {
      this.isInitiallyLoading = true

      const payload = {
        id: this.procedureId,
        fields: {
          AdminProcedure: [
            'segmentCustomFieldsTemplate'
          ].join(),
          CustomField: [
            'name',
            'description',
            'options'
          ].join()
        },
        include: ['segmentCustomFieldsTemplate'].join()
      }

      this.getAdminProcedureWithFields(payload)
        .then(response => {
          const fields = response.data.CustomField
          this.segmentFields = []

          Object.keys(fields).forEach(key => {
            const field = fields[key]
            this.segmentFields.push({
              id: field.id,
              name: field.attributes.name,
              description: field.attributes.description,
              options: field.attributes.options,
              open: false
            })
          })
        })
        .catch(err => console.error(err))
        .finally(() => {
          this.isInitiallyLoading = false
        })
    },

    hideOptions (rowData) {
      const field = this.segmentFields.find(field => field.id === rowData.id)
      if (field) {
        field.open = false
      }
    },

    openNewFieldForm () {
      this.isNewFieldFormOpen = true
    },

    removeOptionInput (index) {
      this.newField.options.splice(index, 1)
    },

    resetNewFieldForm () {
      this.newField = {
        name: '',
        description: '',
        options: [
          '',
          ''
        ]
      }
    },

    saveNewField () {
      this.isLoading = true
      this.newField.options = this.newField.options.filter(option => option !== '')
      const { description, name, options } = this.newField

      const payload = {
        type: 'CustomField',
        attributes: {
          description,
          name,
          options,
          sourceEntity: 'PROCEDURE',
          sourceEntityId: this.procedureId,
          targetEntity: 'SEGMENT',
          fieldType: 'radio_button'
        }
      }

      this.createCustomField(payload)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(err => {
          console.error(err)
        })
        .finally(() => {
          this.isLoading = false
          this.resetNewFieldForm()
          this.fetchSegmentFields()
        })
    },

    showOptions (rowData) {
      const field = this.segmentFields.find(field => field.id === rowData.id)
      if (field) {
        field.open = true
      }
    }
  },

  mounted () {
    this.fetchSegmentFields()
  }
}
</script>
