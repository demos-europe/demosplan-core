<template>
  <div>
    <dp-inline-notification
      v-if="isStatementField && procedureReceivedStatements"
      class="mb-4"
      data-cy="customFields:editWarning"
      dismissible
      :dismissible-key="helpTextDismissibleKey"
      type="warning"
      :message="Translator.trans('custom.fields.edit.warning.multiSelect')"
    />

    <dp-inline-notification
      v-else
      class="mb-4"
      data-cy="customFields:editInfo"
      dismissible
      :dismissible-key="helpTextDismissibleKey"
      :message="helpText"
      type="info"
    />

    <create-custom-field-form
      :disable-type-selection="true"
      :handle-success="isSuccess"
      :is-loading="isLoading"
      :preselected-type="isStatementField ? 'multiSelect' : 'singleSelect'"
      @save="customFieldData => saveNewField(customFieldData)"
    >
      <div>
        <dp-checkbox
          v-if="isStatementField"
          id="requiredCheckbox"
          v-model="isRequired"
          class="mb-2"
          :label="{
            text: Translator.trans('statements.fields.configurable.required')
          }"
        />
        <dp-label
          class="mb-1"
          required
          :text="Translator.trans('options')"
        />
        <dp-input
          id="newFieldOption:1"
          v-model="newFieldOptions[0].label"
          class="mb-2 w-[calc(100%-26px)]"
          data-cy="customFields:newFieldOption1"
          maxlength="250"
          required
        />
        <dp-input
          id="newFieldOption:2"
          v-model="newFieldOptions[1].label"
          class="mb-2 w-[calc(100%-26px)]"
          data-cy="customFields:newFieldOption2"
          maxlength="250"
          required
        />

        <div
          v-for="(option, idx) in additionalOptions"
          :key="`option:${idx}`"
        >
          <div class="w-[calc(100%-26px)] inline-block mb-2">
            <dp-input
              :id="`option:${newFieldOptions[idx + 2].label}`"
              v-model="newFieldOptions[idx + 2].label"
              :data-cy="`customFields:newFieldOption${idx + 2}`"
              maxlength="250"
            />
          </div>
          <dp-button
            :data-cy="`customFields:removeOptionInput:${option.label}`"
            :text="Translator.trans('remove')"
            class="w-[20px] inline-block ml-1"
            hide-text
            icon="x"
            variant="subtle"
            @click="removeOptionInput(idx + 2)"
          />
        </div>

        <dp-button
          :text="Translator.trans('option.add')"
          data-cy="customFields:addOption"
          icon="plus"
          variant="subtle"
          @click="addOptionInput"
        />
      </div>
    </create-custom-field-form>

    <dp-data-table
      v-if="isProcedureTemplate ? !procedureTemplateCustomFieldsLoading : !procedureCustomFieldsLoading"
      :header-fields="headerFields"
      :items="customFieldItems"
      data-cy="customFields:table"
      data-dp-validate="editCustomFieldsForm"
      has-flyout
      track-by="id"
    >
      <template v-slot:name="rowData">
        <div v-if="rowData.edit">
          <dp-input
            id="customFieldName"
            v-model="newRowData.name"
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
            :data-cy="`customFields:option${option.label}`"
            class="mb-1"
          >
            <div>
              {{ option.label }}
            </div>
          </li>
        </ul>
        <ul v-else>
          <li
            v-for="(option, index) in newRowData.options"
            :key="index"
            class="mb-1"
          >
            <div class="flex">
              <dp-input
                :id="`option:${index}`"
                :key="`option:${index}`"
                v-model="newRowData.options[index].label"
                required
              />

              <dp-button
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
              @click="addOptionInputOnEdit(rowData)"
            />
          </li>
        </ul>
      </template>

      <template v-slot:description="rowData">
        <div v-if="rowData.edit">
          <dp-input
            id="customFieldDescription"
            v-model="newRowData.description"
          />
        </div>
        <div v-else>
          {{ rowData.description }}
        </div>
      </template>

      <template v-slot:fieldType="rowData">
        <div class="mt-1">
          <dp-badge
            color="default"
            :text="fieldTypeText(rowData.fieldType)"
          />
        </div>
      </template>

      <template v-slot:isRequired="rowData">
        <div v-if="isStatementField">
          <dp-checkbox
            v-if="rowData.edit"
            v-model="newRowData.isRequired"
            :label ="{
              text: Translator.trans('field.required'),
              hide:true
            }"
          />
          <span v-else>
            {{ rowData.isRequired ? Translator.trans('yes') : Translator.trans('no') }}
          </span>
        </div>
      </template>

      <template v-slot:flyout="rowData">
        <div class="flex float-right">
          <button
            v-if="!rowData.edit"
            class="btn--blank o-link--default mr-1"
            data-cy="customFields:editField"
            :disabled="procedureReceivedStatements"
            :aria-label="Translator.trans('item.edit')"
            :title="Translator.trans('edit')"
            @click="editCustomField(rowData)"
          >
            <dp-icon
              aria-hidden="true"
              icon="edit"
            />
          </button>

          <button
            v-if="!rowData.edit"
            class="btn--blank o-link--default mr-1"
            data-cy="customFields:deleteField"
            :disabled="procedureReceivedStatements"
            :aria-label="Translator.trans('item.edit')"
            :title="Translator.trans('edit')"
            @click="handleDeleteCustomField(rowData)"
          >
            <dp-icon
              aria-hidden="true"
              icon="delete"
            />
          </button>

          <dp-confirm-dialog
            v-if="!rowData.edit"
            ref="deleteConfirmDialog"
            data-cy="customFields:deleteConfirm"
            :message="deleteWarningMessage"
          />

          <template v-else>
            <button
              :aria-label="Translator.trans('save')"
              :disabled="isSaveDisabled[rowData.id]"
              :title="Translator.trans('save')"
              class="btn--blank o-link--default u-mr-0_25 inline-block"
              data-cy="customFields:saveEdit"
              @click="dpValidateAction('editCustomFieldsForm', () => saveEditedFields(), false)"
            >
              <dp-icon
                aria-hidden="true"
                icon="check"
              />
            </button>

            <button
              :aria-label="Translator.trans('abort')"
              :title="Translator.trans('abort')"
              class="btn--blank o-link--default inline-block"
              data-cy="customFields:abortEdit"
              @click="abortFieldEdit(rowData)"
            >
              <dp-icon
                aria-hidden="true"
                icon="xmark"
              />
            </button>
          </template>

          <dp-confirm-dialog
            ref="confirmDialog"
            :message="editWarningMessage"
            data-cy="customFields:saveEditConfirm"
          />

          <button
            v-if="!rowData.open"
            :aria-label="Translator.trans('aria.expand')"
            :disabled="rowData.options.length < 3"
            class="btn--blank o-link--default"
            data-cy="customFields:showOptions"
            @click="showOptions(rowData)"
          >
            <dp-icon
              aria-hidden="true"
              icon="caret-down"
            />
          </button>

          <template v-else>
            <button
              v-if="!rowData.edit"
              :aria-label="Translator.trans('aria.collapse')"
              class="btn--blank o-link--default"
              data-cy="customFields:hideOptions"
              @click="hideOptions(rowData)"
            >
              <dp-icon
                aria-hidden="true"
                icon="caret-up"
              />
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
  DpBadge,
  DpButton,
  DpCheckbox,
  DpConfirmDialog,
  DpDataTable,
  DpIcon,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpLoading,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import CreateCustomFieldForm from '@DpJs/components/procedure/admin/CreateCustomFieldForm'

export default {
  name: 'AdministrationCustomFieldsList',

  components: {
    CreateCustomFieldForm,
    DpBadge,
    DpButton,
    DpCheckbox,
    DpConfirmDialog,
    DpDataTable,
    DpIcon,
    DpInlineNotification,
    DpInput,
    DpLabel,
    DpLoading,
  },

  mixins: [dpValidateMixin],

  props: {
    isProcedureTemplate: {
      type: Boolean,
      default: false,
    },

    procedureId: {
      type: String,
      required: true,
    },
  },

  data () {
    return {
      customFieldItems: [],
      enabledFieldsEntities: {
        field_segments_custom_fields: 'Abschnitten',
        field_statements_custom_fields: 'Stellungnahmen',
      },
      initialRowData: {},
      isLoading: false,
      isNewFieldFormOpen: false,
      isSaveDisabled: {},
      isRequired: false,
      isSuccess: false,
      newFieldOptions: [
        {
          label: '',
        },
        {
          label: '',
        },
      ],
      newRowData: {},
      statementsCount: 0,
      translationKeys: {
        info: 'custom.fields.edit.info.entities',
        delete: 'warning.custom_field.delete.message',
        edit: 'warning.custom_field.edit.message',
      },
    }
  },

  computed: {
    ...mapState('CustomField', {
      customFields: 'items',
    }),

    ...mapState('AdminProcedure', {
      procedureCustomFieldsLoading: 'loading',
    }),

    ...mapState('ProcedureTemplate', {
      procedureTemplateCustomFieldsLoading: 'loading',
    }),

    additionalOptions () {
      return this.newFieldOptions.filter((option, index) => index > 1)
    },

    deleteWarningMessage () {
      return this.getTextForEnabledFieldTypes('delete', 'custom.field.delete.message.warning')
    },

    displayedOptions () {
      return (rowData) => {
        if (rowData.edit && this.newRowData.options) {
          return this.newRowData.options
        }
        return rowData.open ? rowData.options : rowData.options.slice(0, 2)
      }
    },

    editWarningMessage () {
      return this.getTextForEnabledFieldTypes('edit', 'custom.field.edit.message.warning')
    },

    fieldTypeText () {
      const fieldTypeMap = {
        'multiSelect': 'custom.field.type.multiSelect',
        'singleSelect': 'custom.field.type.singleSelect',
      }

      return (fieldType) => {
        const translationKey = fieldTypeMap[fieldType]

        return translationKey ? Translator.trans(translationKey) : fieldType
      }
    },

    headerFields () {
      const fields = [
        {
          field: 'name',
          label: Object.keys(this.newRowData).length > 0 ? `${Translator.trans('name')}*` : Translator.trans('name'),
          colClass: 'u-3-of-12',
        },
        {
          field: 'options',
          label: Object.keys(this.newRowData).length > 0 ? `${Translator.trans('options')}*` : Translator.trans('options'),
          colClass: 'u-4-of-12',
        },
        {
          field: 'description',
          label: Translator.trans('description'),
          colClass: 'u-5-of-12',
        },
        {
          field: 'fieldType',
          label: Translator.trans('type'),
          colClass: 'u-6-of-12',
        },
      ]

      if (this.isStatementField) {
        fields.push({
          field: 'isRequired',
          label: Translator.trans('field.required'),
          colClass: 'u-7-of-12',
        })
      }

      return fields
    },

    helpText () {
      return this.getTextForEnabledFieldTypes('info', 'custom.fields.edit.info')
    },

    helpTextDismissibleKey () {
      return 'customFieldsHint'
    },

    isStatementField () {
      return this.hasPermission('field_statements_custom_fields')
    },

    procedureReceivedStatements () {
      return this.statementsCount > 0
    },
  },

  watch: {
    newRowData: {
      handler (newVal) {
        if (newVal && newVal.id) {
          this.disableSaveIfFieldUnchanged(newVal)
        }
      },
      deep: true,
    },
  },

  methods: {
    ...mapActions('CustomField', {
      createCustomField: 'create',
      deleteCustomField: 'delete',
    }),

    ...mapActions('AdminProcedure', {
      getAdminProcedureWithFields: 'get',
    }),

    ...mapActions('ProcedureTemplate', {
      getProcedureTemplateWithFields: 'get',
    }),

    ...mapMutations('CustomField', {
      addCustomField: 'setItem',
    }),

    abortFieldEdit (rowData) {
      rowData.description = this.initialRowData.description
      rowData.name = this.initialRowData.name
      rowData.isRequired = this.initialRowData.isRequired
      rowData.options = this.initialRowData.options

      this.newRowData = {}

      this.setEditMode(rowData, false)
    },

    addOptionInput () {
      this.newFieldOptions.push({ label: '' })
    },

    addOptionInputOnEdit () {
      this.newRowData.options.push({ label: '' })
    },

    /**
     * @param name { string }
     * @returns { boolean }
     */
    checkIfNameIsUnique (name) {
      const identicalNames = Object.values(this.customFields).filter(field => field.attributes.name === name)
      const inEditMode = this.customFieldItems.filter(field => field.name === name && field.edit).length > 0

      if (!inEditMode) {
        return identicalNames.length === 0
      }

      return identicalNames.length <= 1
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

    async handleDeleteCustomField (rowData) {
      if (this.$refs.deleteConfirmDialog?.open) {
        const isConfirmed = await this.$refs.deleteConfirmDialog.open()

        if (isConfirmed) {
          const currentField = { ...this.customFields[rowData.id] }
          try {
            await this.deleteCustomField(rowData.id)

            // Show success notification
            dplan.notify.confirm(Translator.trans('confirm.deleted'))

            // Rebuild custom fields list from store to rerender the current list
            this.reduceCustomFields()
          } catch (error) {
            // Re-add field to store, if anything goes wrong
            this.addCustomField(currentField)

            console.error('Error deleting custom field:', error)

            dplan.notify.error(Translator.trans('error.generic'))
          }
        }
      }
    },

    deleteOptionOnEdit (index) {
      if (this.newRowData.options.length < 3) {
        return dplan.notify.error(Translator.trans('error.custom_field.minimum.option.count'))
      }

      this.newRowData.options.splice(index, 1)
    },

    disableSaveIfFieldUnchanged (newRowData) {
      const isNameUnchanged = this.initialRowData.name === newRowData.name
      const areOptionsUnchanged = JSON.stringify(this.initialRowData.options) === JSON.stringify(newRowData.options)
      const isDescriptionUnchanged = this.initialRowData.description === newRowData.description
      const isRequiredUnchanged = this.initialRowData.isRequired === newRowData.isRequired

      this.isSaveDisabled[newRowData.id] = isNameUnchanged && areOptionsUnchanged && isDescriptionUnchanged && isRequiredUnchanged
    },

    editCustomField (rowData) {
      const previouslyEditedUnsavedField = this.customFieldItems.find(customFieldItem => customFieldItem.edit === true)

      if (previouslyEditedUnsavedField) {
        this.resetEditedUnsavedField(previouslyEditedUnsavedField)
      }

      this.setFieldBeingEdited(rowData)
    },

    /**
     * Fetch custom fields that are available either in the procedure or in the procedure template
     */
    fetchCustomFields () {
      const sourceEntity = this.isProcedureTemplate ?
        'ProcedureTemplate' :
        'AdminProcedure'

      const payload = {
        id: this.procedureId,
        fields: {
          [sourceEntity]: [
            this.isStatementField ? 'statementCustomFields' : 'segmentCustomFields',
          ].join(),
          AdminProcedure: [
            'statementsCount', // needed to disable multiSelect field edits when there are existing statements
            'statementCustomFields',
          ].join(),
          CustomField: [
            'name',
            'description',
            'options',
            'fieldType',
            ...(this.isStatementField ? ['isRequired'] : []),
          ].join(),
        },
        include: [this.isStatementField ? 'statementCustomFields' : 'segmentCustomFields'].join(),
      }

      this.getCustomFields(payload).then(() => {
        this.reduceCustomFields()
      })
        .catch(err => console.error(err))
    },

    getCustomFields (payload) {
      return this.isProcedureTemplate ?
        this.getProcedureTemplateWithFields(payload)
          .then(response => {
            return response
          }) :
        this.getAdminProcedureWithFields(payload)
          .then(response => {
            console.log('Full response:', response)
            const statementsCount = response?.data?.AdminProcedure?.[this.procedureId]?.attributes?.statementsCount
            this.statementsCount = statementsCount || 0
            console.log('statementsCount:', statementsCount)
            return response
          })
    },

    getIndexOfRowData (rowData) {
      return this.customFieldItems.findIndex(el => el.id === rowData.id)
    },

    /**
     * Returns appropriate text based on which custom field types are enabled in the project
     * @param textType {String} The type of text to retrieve (e.g., 'info', 'delete', 'edit')
     * @param multiplePermissionsText {String} Translation key to use when multiple field types are enabled
     * @returns {String} The translated text message or empty string
     */
    getTextForEnabledFieldTypes (textType, multiplePermissionsText) {
      const permissions = Object.keys(this.enabledFieldsEntities)
        .filter(permission => this.hasPermission(permission))

      if (permissions.length > 1) {
        return Translator.trans(multiplePermissionsText)
      } else if (permissions.length === 1) {
        const translationKey = this.translationKeys[textType]
        const entities = this.enabledFieldsEntities[permissions[0]]

        return Translator.trans(translationKey, { entities })
      }

      return ''
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
            const { description, name, fieldType, isRequired, options } = attributes

            return {
              id,
              name,
              description,
              fieldType,
              ...(this.isStatementField && { isRequired }),
              options: JSON.parse(JSON.stringify(options)),
              open: false,
              edit: false,
            }
          }

          return undefined
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
      const { description = '', isRequired= false,  name = '', options = [] } = this.initialRowData

      customField.description = description
      customField.edit = false
      customField.isRequired = isRequired
      customField.name = name
      customField.open = false
      customField.options = options

      this.newRowData = {}
    },

    resetNewFieldForm () {
      this.newFieldOptions = [
        {
          label: '',
        },
        {
          label: '',
        },
      ]
    },

    saveCustomField (payload) {
      const url = Routing.generate('api_resource_update', { resourceType: 'CustomField', resourceId: this.newRowData.id })

      return dpApi.patch(url, {}, {
        data: payload,
      })
    },

    async saveEditedFields () {
      await this.fetchCustomFields()
      if (this.procedureReceivedStatements) {
        return dplan.notify.error(Translator.trans('custom.fields.edit.error.multiSelect'))
      }

      const isDataValid = this.validateNamesAreUnique(this.newRowData.name, this.newRowData.options)

      if (!isDataValid) {
        return
      }

      if (this.$refs.confirmDialog?.open) {
        const isConfirmed = await this.$refs.confirmDialog.open()

        if (isConfirmed) {
          const storeField = this.customFields[this.newRowData.id]
          const { description = '', isRequired, name, options } = this.newRowData

          const updatedField = {
            ...storeField,
            attributes: Object.fromEntries(
              Object.entries({
                ...storeField.attributes,
                description,
                isRequired,
                name,
                options,
              }).filter(([key]) => key !== 'fieldType'),
            ),
          }

          await this.saveCustomField(updatedField)
            .then(() => {
              const idx = this.customFieldItems.findIndex(el => el.id === storeField.id)
              this.customFieldItems[idx] = { ...this.newRowData }
              this.setEditMode(storeField, false)
              // Fetch custom fields to get a consistent state for the custom fields
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
      const { description, name, fieldType } = customFieldData
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
          fieldType,
          ...(this.isStatementField && { isRequired: this.isRequired }),
          sourceEntity: this.isProcedureTemplate ? 'PROCEDURE_TEMPLATE' : 'PROCEDURE',
          sourceEntityId: this.procedureId,
          targetEntity: this.isStatementField ? 'STATEMENT' : 'SEGMENT',
        },
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
      const { description = '', isRequired, name, options } = rowData

      this.initialRowData = {
        description,
        ...(this.isStatementField && { isRequired }),
        name,
        options: JSON.parse(JSON.stringify(options)),
      }
    },

    setNewRowData (rowData) {
      const { id, description = '', isRequired, name, options } = rowData

      this.newRowData = {
        id,
        description,
        ...(this.isStatementField && { isRequired }),
        name,
        options,
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
        if (!isAnyOptionNameDuplicated && option.label !== '') {
          isAnyOptionNameDuplicated = !this.checkIfOptionNameIsUnique(customFieldOptions, option.label)
        }
      })

      if (isAnyOptionNameDuplicated) {
        return dplan.notify.error(Translator.trans('error.custom_field.option_name.duplicate'))
      }

      return true
    },
  },

  mounted () {
    this.fetchCustomFields()
    // Set up polling to refresh custom fields every 10 seconds to check if meanwhile new statements were created
    this.polling = setInterval(() => {
      this.fetchCustomFields()
    },10000)
  },

  beforeDestroy() {
    clearInterval(this.polling)
  }
}
</script>
