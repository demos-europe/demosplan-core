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
          v-model="newFieldOptions[0].label"
          maxlength="250"
          required />
        <dp-input
          id="newFieldOption:2"
          class="mb-2 w-[calc(100%-26px)]"
          data-cy="customFields:newFieldOption2"
          v-model="newFieldOptions[1].label"
          maxlength="250"
          required />

        <div
          v-for="(option, idx) in additionalOptions"
          :key="`option:${idx}`">
          <div class="w-[calc(100%-26px)] inline-block mb-2">
            <dp-input
              v-model="newFieldOptions[idx + 2].label"
              :id="`option:${newFieldOptions[idx + 2].label}`"
              :data-cy="`customFields:newFieldOption${idx + 2}`"
              maxlength="250" />
          </div>
          <dp-button
            class="w-[20px] inline-block ml-1"
            :data-cy="`customFields:removeOptionInput:${option.label}`"
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
      data-dp-validate="editCustomFieldsForm"
      has-flyout
      :header-fields="headerFields"
      :items="customFieldItems"
      track-by="id">
      <template v-slot:name="rowData">
        <div v-if="rowData.edit">
          <dp-input
            v-model="newRowData.name"
            id="customFieldName"
            required
          />
        </div>
        <div v-else>
          {{ rowData.name }}
        </div>
      </template>

      <template v-slot:options="rowData">
        <ul v-if="!rowData.edit">
          <li
            v-for="(option, index) in displayedOptions(rowData)"
            :key="index"
            class="mb-1"
            :data-cy="`customFields:option${option.label}`">
            <div>
              {{ option.label }}
            </div>
          </li>

        </ul>
        <ul v-else>
          <li
            v-for="(option, index) in newRowData.options"
            :key="index"
            class="mb-1">
              <div class="flex">
                <dp-input
                  v-model="newRowData.options[index].label"
                  :id="`option:${index}`"
                  :key="`option:${index}`"
                  required
                />

                <dp-button
                  v-if="index >= rowData.options.length"
                  class="w-[20px] inline-block ml-1"
                  :data-cy="`customFields:removeOptionInput:${option.label}`"
                  hide-text
                  icon="x"
                  :text="Translator.trans('remove')"
                  variant="subtle"
                  @click="deleteOptionOnEdit(index)"
                />
              </div>
          </li>
          <li>
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
          <dp-input
            id="customFieldDescription"
            v-model="newRowData.description" />
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
            :title="Translator.trans('edit')"
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
              :disabled="isSaveDisabled[rowData.id]"
              :title="Translator.trans('save')"
              @click="dpValidateAction('editCustomFieldsForm', () => saveEditedFields(), false)">
              <dp-icon
                icon="check"
                aria-hidden="true" />
            </button>

            <button
              class="btn--blank o-link--default inline-block"
              data-cy="customFields:abortEdit"
              @click="abortFieldEdit(rowData)"
              :title="Translator.trans('abort')"
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
            :aria-label="Translator.trans('aria.expand')"
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
              :aria-label="Translator.trans('aria.collapse')"
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
  dpApi,
  DpButton,
  DpConfirmDialog,
  DpDataTable,
  DpIcon,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpLoading,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
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

  mixins: [dpValidateMixin],

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
      initialRowData: {},
      isLoading: false,
      isNewFieldFormOpen: false,
      isSaveDisabled: {},
      isSuccess: false,
      newFieldOptions: [
        {
          label: ''
        },
        {
          label: ''
        }
      ],
      newRowData: {}
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

    headerFields () {
      return [
        {
          field: 'name',
          label: Object.keys(this.newRowData).length > 0 ? `${Translator.trans('name')}*` : Translator.trans('name'),
          colClass: 'u-3-of-12'
        },
        {
          field: 'options',
          label: Object.keys(this.newRowData).length > 0 ? `${Translator.trans('options')}*` : Translator.trans('options'),
          colClass: 'u-4-of-12'
        },
        {
          field: 'description',
          label: Translator.trans('description'),
          colClass: 'u-5-of-12'
        }
      ]
    },

    helpTextDismissibleKey () {
      return 'customFieldsHint'
    }
  },

  watch: {
    newRowData: {
      handler (newVal) {
        if (newVal && newVal.id) {
          this.disableSaveIfFieldUnchanged(newVal)
        }
      },
      deep: true
    }
  },

  methods: {
    ...mapActions('CustomField', {
      createCustomField: 'create',
    }),

    ...mapActions('AdminProcedure', {
      getAdminProcedureWithFields: 'get'
    }),

    ...mapActions('ProcedureTemplate', {
      getProcedureTemplateWithFields: 'get'
    }),

    abortFieldEdit (rowData) {
      rowData.description = this.initialRowData.description
      rowData.name = this.initialRowData.name
      rowData.options = this.initialRowData.options

      this.newRowData = {}

      this.setEditMode(rowData, false)
    },

    addOptionInput () {
      this.newFieldOptions.push({ label: '' })
    },

    addOptionInputOnEdit () {
      this.newRowData.options.push({ label: ''})
    },

    /**
     * @param name { string }
     * @returns { boolean }
     */
    checkIfNameIsUnique (name) {
      const identicalNames = Object.values(this.customFields).filter(field => field.attributes.name === name)

      return identicalNames.length <= 0
    },

    /**
     * @param options { array } Array of strings
     * @param name { string }
     * @returns { boolean }
     */
    checkIfOptionNameIsUnique (options, name) {
      const identicalNames = options.filter(option => option.label === name)

      return identicalNames.length <= 1
    },

    deleteOptionOnEdit (index) {
      this.newRowData.options.splice(index, 1)
    },

    disableSaveIfFieldUnchanged (newRowData) {
      const isNameUnchanged = this.initialRowData.name === newRowData.name
      const areOptionsUnchanged = JSON.stringify(this.initialRowData.options) === JSON.stringify(newRowData.options)
      const isDescriptionUnchanged = this.initialRowData.description === newRowData.description

      this.isSaveDisabled[newRowData.id] = isNameUnchanged && areOptionsUnchanged && isDescriptionUnchanged
    },

    editCustomField (rowData) {
      let previouslyEditedUnsavedField = this.customFieldItems.find(customFieldItem => customFieldItem.edit === true)

      if (previouslyEditedUnsavedField) {
        this.resetEditedUnsavedField(previouslyEditedUnsavedField)
      }

      this.setFieldBeingEdited(rowData)
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
        this.reduceCustomFields()
      })
        .catch(err => console.error(err))
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

    getIndexOfRowData (rowData) {
      return this.customFieldItems.findIndex(el => el.id === rowData.id)
    },

    hideOptions (rowData) {
      const idx = this.getIndexOfRowData(rowData)

      this.customFieldItems[idx].open = false
    },

    /**
     * CustomFields reduced to the format we need in the FE
     */
    reduceCustomFields () {
      const fieldsReduced = Object.values(this.customFields)
        .map(field => {
          if (field) {
            const { id, attributes } = field
            const { description, name, options } = attributes

            return {
              id,
              name,
              description,
              options: JSON.parse(JSON.stringify(options)),
              open: false,
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

    removeOptionInput (index) {
      this.newFieldOptions.splice(index, 1)
    },

    resetEditedUnsavedField (customField) {
      const { description = '', name = '', options = [] } = this.initialRowData

      customField.description = description
      customField.edit = false
      customField.name = name
      customField.open = false
      customField.options = options

      this.newRowData = {}
    },

    resetNewFieldForm () {
      this.newFieldOptions = [
        {
          label: ''
        },
        {
          label: ''
        }
      ]
    },

    saveCustomField (payload) {
      const url = Routing.generate('api_resource_update', { resourceType: 'CustomField', resourceId: this.newRowData.id })

      return dpApi.patch(url, {}, {
        data: payload
      })
    },

    async saveEditedFields () {
      const isDataValid = this.validateNamesAreUnique(this.newRowData.name, this.newRowData.options)

      if (!isDataValid) {
        return
      }

      if (this.$refs.confirmDialog?.open) {
        const isConfirmed = await this.$refs.confirmDialog.open()

        if (isConfirmed) {
          const storeField = this.customFields[this.newRowData.id]
          const { description = '', name, options } = this.newRowData

          const updatedField = {
            ...storeField,
            attributes: {
              ...storeField.attributes,
              description,
              name,
              options
            }
          }

          await this.saveCustomField(updatedField)
            .then(() => {
              const idx = this.customFieldItems.findIndex(el => el.id === storeField.id)
              this.customFieldItems[idx] = { ...this.newRowData }
              this.setEditMode(storeField, false)
              // fetch custom fields to get a consistent state for the custom fields
              this.fetchCustomFields()
            })
        }
      }
    },

    /**
     * Prepare payload and send create request for custom field
     * @param customFieldData {Object}
     * @param customFieldData.name {String}
     * @param customFieldData.description {String}
     */
    saveNewField (customFieldData) {
      const { description, name } = customFieldData
      const options = this.newFieldOptions.filter(option => option.label !== '')
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

    setEditMode (rowData, editState = true) {
      const idx = this.customFieldItems.findIndex(el => el.id === rowData.id)

      this.customFieldItems[idx].open = editState
      this.customFieldItems[idx].edit = editState
    },

    setFieldBeingEdited (rowData) {
      const newRowData = JSON.parse(JSON.stringify(rowData))
      this.setInitialRowData(rowData)
      this.setNewRowData(newRowData)
      this.setEditMode(rowData)
    },

    setInitialRowData (rowData) {
      const { description = '', name, options } = rowData

      this.initialRowData = {
        description,
        name,
        options: JSON.parse(JSON.stringify(options))
      }
    },

    setNewRowData (rowData) {
      const { id, description = '', name, options } = rowData

      this.newRowData = {
        id,
        description,
        name,
        options
      }
    },

    showOptions (rowData) {
      const idx = this.customFieldItems.findIndex(el => el.id === rowData.id)

      this.customFieldItems[idx].open = true
    },

    /**
     *
     * @param customFieldName {String}
     * @param customFieldOptions {Array} array of objects with label property
     */
    validateNamesAreUnique (customFieldName, customFieldOptions) {
      const isNameDuplicated = !this.checkIfNameIsUnique(customFieldName)

      if (isNameDuplicated) {
        return dplan.notify.error(Translator.trans('error.custom_field.name.duplicate'))
      }

      let isAnyOptionNameDuplicated = false
      customFieldOptions.forEach(option => {
        if (isAnyOptionNameDuplicated) {
          /* since the array function forEach does not accept continue in its arrow function we have to use return,
          also see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Errors/Bad_continue for further info */
          return
        } else if (option.label !== '') {
          isAnyOptionNameDuplicated = !this.checkIfOptionNameIsUnique(customFieldOptions, option.label)
        }
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
