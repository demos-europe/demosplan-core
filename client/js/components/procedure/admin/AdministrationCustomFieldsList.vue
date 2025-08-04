<template>
  <div>
    <dp-inline-notification
      class="mb-4"
      data-cy="places:editInfo"
      dismissible
      :dismissible-key="helpTextDismissibleKey"
      :message="Translator.trans('segments.fields.edit.info')"
      type="info" />

    <create-custom-field-form
      :handle-success="isSuccess"
      :is-loading="isLoading"
      @save="customFieldData => saveNewField(customFieldData)">
      <div>
        <dp-label
          class="mb-1"
          required
          :text="Translator.trans('options')" />
        <dp-input
          id="newFieldOption:1"
          class="mb-2 w-[calc(100%-26px)]"
          data-cy="customFields:newFieldOption1"
          v-model="newFieldOptions[0]"
          maxlength="250"
          required />
        <dp-input
          id="newFieldOption:2"
          class="mb-2 w-[calc(100%-26px)]"
          data-cy="customFields:newFieldOption2"
          v-model="newFieldOptions[1]"
          maxlength="250"
          required />

        <div
          v-for="(option, idx) in additionalOptions"
          :key="`option:${idx}`">
          <div class="w-[calc(100%-26px)] inline-block mb-2">
            <dp-input
              v-model="newFieldOptions[idx + 2]"
              :id="`option:${newFieldOptions[idx + 2]}`"
              :data-cy="`customFields:newFieldOption${idx + 2}`"
              maxlength="250" />
          </div>
          <dp-button
            class="w-[20px] inline-block ml-1"
            :data-cy="`customFields:removeOptionInput:${option}`"
            hide-text
            icon="x"
            :text="Translator.trans('remove')"
            variant="subtle"
            @click="removeOptionInput(idx + 2)" />
        </div>

        <dp-button
          data-cy="customFields:addOption"
          icon="plus"
          variant="subtle"
          :text="Translator.trans('option.add')"
          @click="addOptionInput" />
      </div>
    </create-custom-field-form>

    <dp-data-table
      v-if="isProcedureTemplate ? !procedureTemplateCustomFieldsLoading : !procedureCustomFieldsLoading"
      data-cy="customFields:table"
      has-flyout
      :header-fields="headerFields"
      :items="customFieldItems"
      track-by="id">
      <template v-slot:name="rowData">
        <div v-if="rowData.edit">
          <dp-input
            v-model="newRowData.name"
            id="cfName"
            required
          />
        </div>
        <div v-else>
          {{ rowData.name }}
        </div>
      </template>

      <template v-slot:options="rowData">
        <ul>
          <li
            v-for="(option, index) in displayedOptions(rowData)"
            :key="index"
            class="mb-1"
            :data-cy="`customFields:option${option}`">
            <div v-if="rowData.edit">
              <div class="flex">
                <dp-input
                  v-model="newRowData.options[index]"
                  :id="'option' + index"
                  :key="'option' + index"
                  required
                />

                <dp-button
                  v-if="index >= rowData.options.length"
                  class="w-[20px] inline-block ml-1"
                  :data-cy="`customFields:removeOptionInput:${option}`"
                  hide-text
                  icon="x"
                  :text="Translator.trans('remove')"
                  variant="subtle"
                  @click="deleteOptionOnEdit(index)"
                />
              </div>
            </div>

            <div v-else>
              {{ option }}
            </div>
          </li>
          <li v-if="rowData.edit">
            <dp-button
              data-cy="customFields:addOptionOnEdit"
              icon="plus"
              variant="subtle"
              :text="Translator.trans('option.add')"
              @click="addOptionInputOnEdit(rowData)" />
          </li>
        </ul>
      </template>

      <template v-slot:description="rowData">
        <div v-if="rowData.edit">
          <dp-input v-model="newRowData.description" id="cfDescription" />
        </div>
        <div v-else>
          {{ rowData.description }}
        </div>
      </template>

      <template v-slot:flyout="rowData">
        <div class="flex float-right">
          <button
            v-if="!rowData.edit"
            class="btn--blank o-link--default mr-1"
            data-cy="customFields:editField"
            :aria-label="Translator.trans('item.edit')"
            @click="editCustomField(rowData)">
            <dp-icon
              aria-hidden="true"
              icon="edit"
            />
          </button>

          <template v-else>
            <button
              :aria-label="Translator.trans('save')"
              class="btn--blank o-link--default u-mr-0_25 inline-block"
              data-cy="customFields:saveEdit"
              @click="saveEditedFields()">
              <dp-icon
                icon="check"
                aria-hidden="true" />
            </button>

            <button
              class="btn--blank o-link--default inline-block"
              data-cy="customFields:abortEdit"
              @click="abortFieldEdit(rowData)"
              :aria-label="Translator.trans('abort')">
              <dp-icon
                icon="xmark"
                aria-hidden="true" />
            </button>
          </template>

          <dp-confirm-dialog
            ref="confirmDialog"
            data-cy="customFields:saveEditConfirm"
            :message="Translator.trans('custom.field.edit.message.warning')" />

          <button
            v-if="!rowData.open"
            :aria-label="Translator.trans('item.edit')"
            class="btn--blank o-link--default"
            data-cy="customFields:showOptions"
            :disabled="rowData.options.length < 3"
            @click="showOptions(rowData)">
            <dp-icon
              aria-hidden="true"
              icon="caret-down" />
          </button>

          <template v-else>
            <button
              v-if="!rowData.edit"
              :aria-label="Translator.trans('save')"
              class="btn--blank o-link--default"
              data-cy="customFields:hideOptions"
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
  DpConfirmDialog,
  DpDataTable,
  DpIcon,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpLoading
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import CreateCustomFieldForm from '@DpJs/components/procedure/admin/CreateCustomFieldForm'

export default {
  name: 'AdministrationCustomFieldsList',

  components: {
    CreateCustomFieldForm,
    DpButton,
    DpConfirmDialog,
    DpDataTable,
    DpIcon,
    DpInlineNotification,
    DpInput,
    DpLabel,
    DpLoading
  },

  props: {
    isProcedureTemplate: {
      type: Boolean,
      default: false
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      customFieldItems: [],
      headerFields: [
        { field: 'name', label: Translator.trans('name'), colClass: 'u-3-of-12' },
        { field: 'options', label: Translator.trans('options'), colClass: 'u-4-of-12' },
        { field: 'description', label: Translator.trans('description'), colClass: 'u-5-of-12' }
      ],
      initialRowData: {},
      isLoading: false,
      isNewFieldFormOpen: false,
      isSuccess: false,
      newFieldOptions: [
        '',
        ''
      ],
      newRowData: {},
      // ToDo: Find out if expandedFields are still needed: it seems as they can be deleted, if the list items are not expanded through dpDataTable expand mechanism
      expandedFields: {},
    }
  },

  computed: {
    ...mapState('CustomField', {
      customFields: 'items'
    }),

    ...mapState('AdminProcedure', {
      procedureCustomFieldsLoading: 'loading'
    }),

    ...mapState('ProcedureTemplate', {
      procedureTemplateCustomFieldsLoading: 'loading'
    }),

    additionalOptions () {
      return this.newFieldOptions.filter((option, index) => index > 1)
    },

    displayedOptions () {
      return (rowData) => {
        if (rowData.edit && this.newRowData.options) {
          return this.newRowData.options
        }
        return rowData.open ? rowData.options : rowData.options.slice(0, 2)
      }
    },

    helpTextDismissibleKey () {
      return 'customFieldsHint'
    }
  },

  methods: {
    ...mapActions('CustomField', {
      createCustomField: 'create',
      saveCustomField: 'save'
    }),

    ...mapActions('AdminProcedure', {
      getAdminProcedureWithFields: 'get'
    }),

    ...mapActions('ProcedureTemplate', {
      getProcedureTemplateWithFields: 'get'
    }),

    ...mapMutations('CustomField', {
      updateCustomField: 'set'
    }),

    async saveEditedFields () {
      if (this.$refs.confirmDialog?.open) {
        const isConfirmed = await this.$refs.confirmDialog.open()

        if (isConfirmed) {
          //TODO: Send data
          //update store item
          const storeField = this.customFields[this.newRowData.id]
          storeField.attributes.options = this.newRowData.options
          this.updateCustomField(storeField)
          // send patch
          await this.saveCustomField(storeField.id).catch()

          //Beispiel Update ohne Store
          // dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'AnnotatedStatementPdf', resourceId: this.documentId }), {}, payload)
          //   .then(response => {
          //     if (response.ok) {
          //       dplan.notify.confirm(Translator.trans('statement.save.quickSave.success'))
          //     } else {
          //       dplan.notify.error(Translator.trans('error.api.generic'))
          //     }
          //   })
          //   .catch((err) => {
          //     console.error(err)
          //     dplan.notify.error(Translator.trans('error.api.generic'))
          //   })
        }
      }
    },

    abortFieldEdit (rowData) {
      rowData.description = this.initialRowData.description
      rowData.name = this.initialRowData.name
      rowData.options = this.initialRowData.options

      this.newRowData = {}

      this.setEditMode(rowData, false)
      this.hideOptions(rowData)
    },

    addOptionInput () {
      this.newFieldOptions.push('')
    },

    addOptionInputOnEdit () {
      this.newRowData.options.push('')
    },

    /**
     * @param name { string }
     * @returns { boolean }
     */
    checkIfNameIsUnique (name) {
      const identicalNames = Object.values(this.customFields).filter(field => field.attributes.name === name)

      return identicalNames.length <= 1
    },

    /**
     * @param options { array } Array of strings
     * @param name { string }
     * @returns { boolean }
     */
    checkIfOptionNameIsUnique (options, name) {
      const identicalNames = options.filter(optionName => optionName === name)

      return identicalNames.length <= 1
    },

    deleteOptionOnEdit (index) {
      this.newRowData.options.splice(index, 1)
    },

    editCustomField (rowData) {
      console.log(this.customFieldItems)
      const editingCustomField = this.customFieldItems.find(customFieldItem => customFieldItem.edit === true)

      console.log(editingCustomField)
      console.log(rowData)
      if (editingCustomField) {
        // Reset row which was in editing state before
        editingCustomField.description = this.initialRowData.description
        editingCustomField.edit = false
        editingCustomField.name = this.initialRowData.name
        editingCustomField.open = false
        editingCustomField.options = this.initialRowData.options
        console.log(this.newRowData)
        this.newRowData = {}
      }

      // Save initial state of currently edited row
      this.initialRowData.description = rowData.description
      this.initialRowData.name = rowData.name
      this.initialRowData.options = rowData.options

      this.newRowData.description = rowData.description
      this.newRowData.name = rowData.name

      if (!this.newRowData.options) {
        this.newRowData.options = [...rowData.options]//rowData.options.map((option) => option)

        // rowData.options.forEach((option) => {
        //   this.newRowData.options.push(option)
        // })
      }
      //this.showOptions(rowData)

      this.setEditMode(rowData)
    },

    /**
     * Fetch custom fields that are available either in the procedure or in the procedure template
     */
    fetchCustomFields () {
      const sourceEntity = this.isProcedureTemplate
        ? 'ProcedureTemplate'
        : 'AdminProcedure'

      const payload = {
        id: this.procedureId,
        fields: {
          [sourceEntity]: [
            'segmentCustomFields'
          ].join(),
          CustomField: [
            'name',
            'description',
            'options'
          ].join()
        },
        include: ['segmentCustomFields'].join()
      }

      this.getCustomFields(payload).then(() => {
        this.customFieldsReduced()
      })
        .catch(err => console.error(err))
    },

    hideOptions (rowData) {
      const idx = this.getIndexOfRowData(rowData)

      this.customFieldItems[idx].open = false

      delete this.expandedFields[rowData.id]
    },

    getIndexOfRowData (rowData) {
      return this.customFieldItems.findIndex(el => el.id === rowData.id)
    },

    removeOptionInput (index) {
      this.newFieldOptions.splice(index, 1)
    },

    resetNewFieldForm () {
      this.newFieldOptions = [
        '',
        ''
      ]
    },

    /**
     * Prepare payload and send create request for custom field
     * @param customFieldData {Object}
     * @param customFieldData.name {String}
     * @param customFieldData.description {String}
     */
    saveNewField (customFieldData) {
      const { description, name } = customFieldData
      const options = this.newFieldOptions.filter(option => option !== '')
      const isDataValid = this.validateNamesAreUnique(name, options)

      if (!isDataValid) {
        return
      }

      this.isLoading = true

      const payload = {
        type: 'CustomField',
        attributes: {
          description,
          name,
          options,
          sourceEntity: this.isProcedureTemplate ? 'PROCEDURE_TEMPLATE' : 'PROCEDURE',
          sourceEntityId: this.procedureId,
          targetEntity: 'SEGMENT',
          fieldType: 'singleSelect'
        }
      }

      this.createCustomField(payload)
        .then(() => {
          this.isSuccess = true
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(err => {
          console.error(err)
        })
        .finally(() => {
          this.isLoading = false
          this.resetNewFieldForm()
          this.fetchCustomFields()
        })
    },

    getCustomFields (payload) {
      return this.isProcedureTemplate
        ? this.getProcedureTemplateWithFields(payload)
          .then(response => {
            return response
          })
        : this.getAdminProcedureWithFields(payload)
          .then(response => {
            return response
          })
    },

    /**
     * CustomFields reduced to the format we need in the FE
     */
    customFieldsReduced () {
      const fieldsReduced = Object.values(this.customFields)
        .map(field => {
          if (field) {
            const { id, attributes } = field
            const { description, name, options } = attributes

            return {
              id,
              name,
              description,
              options,
              open: this.expandedFields[id] || false,
              edit: false,
            }
          }
        })
        .filter(field => field !== undefined)

      if (this.customFieldItems.length > 0) {
        this.customFieldItems = []
      }

      fieldsReduced.forEach((field) => {
        this.customFieldItems.push(field)
      })
    },

    setEditMode (rowData, editState = true) {
      const idx = this.customFieldItems.findIndex(el => el.id === rowData.id)

      this.customFieldItems[idx].open = editState
      this.customFieldItems[idx].edit = editState
    },

    showOptions (rowData) {
      const idx = this.customFieldItems.findIndex(el => el.id === rowData.id)
      console.log(rowData)
      console.log(this.expandedFields)
      this.customFieldItems[idx].open = true
      this.expandedFields[rowData.id] = true
    },

    /**
     *
     * @param customFieldName {String}
     * @param customFieldOptions {Array} array of strings
     */
    validateNamesAreUnique (customFieldName, customFieldOptions) {
      const isNameDuplicated = !this.checkIfNameIsUnique(customFieldName)

      if (isNameDuplicated) {
        return dplan.notify.error(Translator.trans('error.custom_field.name.duplicate'))
      }

      let isAnyOptionNameDuplicated = false
      customFieldOptions.forEach(optionName => {
        isAnyOptionNameDuplicated = !this.checkIfOptionNameIsUnique(customFieldOptions, optionName)
      })

      if (isAnyOptionNameDuplicated) {
        return dplan.notify.error(Translator.trans('error.custom_field.option_name.duplicate'))
      }

      return true
    }
  },

  mounted () {
    this.fetchCustomFields()
  }
}
</script>
