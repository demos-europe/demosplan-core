<template>
  <div>
    <dp-inline-notification
      v-if="helpText"
      :dismissible-key="helpTextDismissibleKey"
      :message="helpText"
      class="mb-4"
      data-cy="customFields:editInfo"
      type="info"
      dismissible
    />

    <create-custom-field-form
      v-if="canCreateCustomFields"
      :handle-success="isSuccess"
      :is-loading="isLoading"
      :target-options="createTargetOptions"
      @save="saveNewField"
    >
      <div>
        <dp-label
          :text="Translator.trans('options')"
          class="mb-1"
          required
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
            icon="x"
            variant="subtle"
            hide-text
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

    <dp-tabs
      :active-id="activeTabId"
      tab-size="medium"
      @change="setActiveTabId"
    >
      <dp-tab
        v-for="[tabKey, tabLabel] in Object.entries(targetOptions)"
        :id="tabKey"
        :key="tabKey"
        :is-active="activeTabId === tabKey"
        :label="tabLabel"
      >
        <div
          v-if="activeTabId === tabKey"
          class="mt-4"
        >
          <dp-inline-notification
            v-if="isActiveTabStatementContext && procedureReceivedStatements"
            :dismissible-key="helpTextDismissibleKey"
            :message="Translator.trans('custom.fields.edit.warning.multiSelect')"
            class="mb-4"
            data-cy="customFields:editWarning"
            type="warning"
            dismissible
          />

          <dp-data-table
            v-if="!isLoadingFields"
            :header-fields="headerFields"
            :items="customFieldItems"
            data-cy="customFields:table"
            data-dp-validate="editCustomFieldsForm"
            track-by="id"
            has-flyout
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
                  <div>{{ option.label }}</div>
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
                      :data-cy="`customFields:removeOptionInput:${option.label}`"
                      :text="Translator.trans('remove')"
                      class="w-[20px] inline-block ml-1"
                      icon="x"
                      variant="subtle"
                      hide-text
                      @click="deleteOptionOnEdit(index)"
                    />
                  </div>
                </li>
                <li>
                  <dp-button
                    :text="Translator.trans('option.add')"
                    data-cy="customFields:addOptionOnEdit"
                    icon="plus"
                    variant="subtle"
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
                  :text="fieldTypeText(rowData.fieldType)"
                  color="default"
                />
              </div>
            </template>

            <template v-slot:isRequired="rowData">
              <dp-checkbox
                v-if="rowData.edit"
                v-model="newRowData.isRequired"
                :label="{
                  text: Translator.trans('field.required'),
                  hide: true
                }"
              />
              <span v-else>
                {{ rowData.isRequired ? Translator.trans('yes') : Translator.trans('no') }}
              </span>
            </template>

            <template v-slot:flyout="rowData">
              <div class="flex float-right">
                <button
                  v-if="!rowData.edit"
                  :aria-label="Translator.trans('item.edit')"
                  :disabled="isActiveTabStatementContext && procedureReceivedStatements"
                  :title="Translator.trans('edit')"
                  class="btn--blank o-link--default mr-1"
                  data-cy="customFields:editField"
                  @click="editCustomField(rowData)"
                >
                  <dp-icon aria-hidden="true" icon="edit" />
                </button>

                <button
                  v-if="!rowData.edit"
                  :aria-label="Translator.trans('item.delete')"
                  :disabled="isActiveTabStatementContext && procedureReceivedStatements"
                  :title="Translator.trans('delete')"
                  class="btn--blank o-link--default mr-1"
                  data-cy="customFields:deleteField"
                  @click="handleDeleteCustomField(rowData)"
                >
                  <dp-icon aria-hidden="true" icon="delete" />
                </button>


                <template v-else>
                  <button
                    :aria-label="Translator.trans('save')"
                    :disabled="isSaveDisabled[rowData.id]"
                    :title="Translator.trans('save')"
                    class="btn--blank o-link--default u-mr-0_25 inline-block"
                    data-cy="customFields:saveEdit"
                    @click="dpValidateAction('editCustomFieldsForm', () => saveEditedFields(), false)"
                  >
                    <dp-icon aria-hidden="true" icon="check" />
                  </button>

                  <button
                    :aria-label="Translator.trans('abort')"
                    :title="Translator.trans('abort')"
                    class="btn--blank o-link--default inline-block"
                    data-cy="customFields:abortEdit"
                    @click="abortFieldEdit(rowData)"
                  >
                    <dp-icon aria-hidden="true" icon="xmark" />
                  </button>
                </template>


                <button
                  v-if="!rowData.open"
                  :aria-label="Translator.trans('aria.expand')"
                  :disabled="rowData.options.length < 3"
                  class="btn--blank o-link--default"
                  data-cy="customFields:showOptions"
                  @click="showOptions(rowData)"
                >
                  <dp-icon aria-hidden="true" icon="caret-down" />
                </button>

                <template v-else>
                  <button
                    v-if="!rowData.edit"
                    :aria-label="Translator.trans('aria.collapse')"
                    class="btn--blank o-link--default"
                    data-cy="customFields:hideOptions"
                    @click="hideOptions(rowData)"
                  >
                    <dp-icon aria-hidden="true" icon="caret-up" />
                  </button>
                </template>
              </div>
            </template>
          </dp-data-table>

          <dp-loading v-else />
        </div>
      </dp-tab>
    </dp-tabs>

    <dp-confirm-dialog
      ref="confirmationDialog"
      :message="currentConfirmMessage"
    />
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
  DpTab,
  DpTabs,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'
import { useCustomFields } from '@DpJs/composables/useCustomFields'
import CreateCustomFieldForm from '@DpJs/components/procedure/admin/CreateCustomFieldForm'

const {
  createCustomFieldDefinition,
  deleteCustomFieldDefinition,
  fetchCustomFields: fetchCustomFieldsFromComposable,
  updateCustomFieldDefinition,
} = useCustomFields()

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
    DpTab,
    DpTabs,
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

    targetOptions: {
      type: Object,
      required: true,
    },
  },

  data () {
    return {
      activeTabId: Object.keys(this.targetOptions ?? {})[0] || '',
      allDefinitions: [],
      currentConfirmMessage: '',
      customFieldItems: [],
      enabledFieldsEntities: {
        field_segments_custom_fields: 'Abschnitten',
        field_statements_custom_fields: 'Stellungnahmen',
      },
      initialRowData: {},
      isLoading: false,
      isLoadingFields: false,
      isSaveDisabled: {},
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
        delete: 'warning.custom_field.delete.message',
        edit: 'warning.custom_field.edit.message',
        info: 'custom.fields.edit.info.entities',
      },
    }
  },

  computed: {
    additionalOptions () {
      return this.newFieldOptions.filter((option, index) => index > 1)
    },

    canCreateCustomFields () {
      return Object.keys(this.createTargetOptions).length > 0
    },

    /*
     * Filters out targets that cannot receive new fields.
     * STATEMENT fields cannot be created once statements have been submitted
     * to preserve data consistency.
     */
    createTargetOptions () {
      if (this.procedureReceivedStatements) {
        return Object.fromEntries(
          Object.entries(this.targetOptions).filter(([key]) => key !== 'STATEMENT')
        )
      }

      return this.targetOptions
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
      if (this.isActiveTabStatementContext) {
        return Translator.trans('warning.custom_field.edit.statement.message')
      }

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

      if (this.isActiveTabStatementContext) {
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

    isActiveTabStatementContext () {
      return this.activeTabId === 'STATEMENT'
    },

    procedureReceivedStatements () {
      return this.statementsCount > 0
    },
  },

  watch: {
    activeTabId () {
      this.customFieldItems = []
      this.newRowData = {}
      this.resetNewFieldForm()
      this.fetchCustomFields()
      if (this.isActiveTabStatementContext) {
        this.fetchStatementsCount()
      }
    },

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
    abortFieldEdit (rowData) {
      rowData.description = this.initialRowData.description
      rowData.isRequired = this.initialRowData.isRequired
      rowData.name = this.initialRowData.name
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
      const identicalNames = this.allDefinitions.filter(definition =>
        definition.attributes?.name === name
      )
      const isInEditMode = this.customFieldItems.some(field => field.name === name && field.edit)

      if (isInEditMode) {
        return identicalNames.length <= 1
      }

      return identicalNames.length === 0
    },

    /**
     * @param options { array } Array of objects with label property
     * @param name { string }
     * @returns { boolean }
     */
    checkIfOptionNameIsUnique (options, name) {
      const identicalNames = options.filter(option => option.label === name)

      return identicalNames.length <= 1
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

    /*
     * Fetch custom field definitions for the active tab from the composable.
     * Server filters by targetEntity and sourceEntity; no client-side filtering needed.
     * Results are cached per targetEntity/sourceEntity combination.
     */
    fetchCustomFields () {
      this.isLoadingFields = true

      return fetchCustomFieldsFromComposable(this.procedureId, {
        targetEntity: this.activeTabId,
        sourceEntity: this.isProcedureTemplate ? 'PROCEDURE_TEMPLATE' : 'PROCEDURE',
      })
        .then(definitions => {
          this.allDefinitions = definitions
          this.reduceCustomFields()
        })
        .catch(err => console.error(err))
        .finally(() => {
          this.isLoadingFields = false
        })
    },

    /*
     * Fetch the number of submitted statements for the procedure.
     * Only called for non-template procedures when the STATEMENT tab is active.
     * Cached in statementsCount after first fetch to avoid repeated requests.
     */
    fetchStatementsCount () {
      if (this.isProcedureTemplate || this.statementsCount > 0) {
        return
      }

      const url = Routing.generate('api_resource_get', {
        resourceType: 'AdminProcedure',
        resourceId: this.procedureId,
      })

      dpApi.get(url, { fields: { AdminProcedure: 'statementsCount' } })
        .then(response => {
          this.statementsCount = response?.data?.data?.attributes?.statementsCount ?? 0
        })
        .catch(err => console.error(err))
    },

    getIndexOfRowData (rowData) {
      return this.customFieldItems.findIndex(el => el.id === rowData.id)
    },

    /**
     * Returns appropriate text based on which custom field types are enabled in the project.
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

    handleDeleteCustomField (rowData) {
      this.requestConfirmation(this.deleteWarningMessage)
        .then(isConfirmed => {
          if (!isConfirmed) {
            return
          }

          const definitionSnapshot = this.allDefinitions.find(definition => definition.id === rowData.id)
          const removedIndex = this.customFieldItems.findIndex(item => item.id === rowData.id)
          const removedItem = this.customFieldItems.splice(removedIndex, 1)[0]

          deleteCustomFieldDefinition(rowData.id, this.procedureId)
            .then(() => {
              this.allDefinitions = this.allDefinitions.filter(definition => definition.id !== rowData.id)
              dplan.notify.confirm(Translator.trans('confirm.deleted'))
            })
            .catch(error => {
              if (removedIndex >= 0) {
                this.customFieldItems.splice(removedIndex, 0, removedItem)
              }

              if (definitionSnapshot) {
                this.allDefinitions = [...this.allDefinitions, definitionSnapshot]
              }

              console.error('Error deleting custom field:', error)
              dplan.notify.error(Translator.trans('error.generic'))
            })
        })
    },

    hideOptions (rowData) {
      const idx = this.getIndexOfRowData(rowData)

      this.customFieldItems[idx].open = false
    },

    /*
     * Transform the raw composable definitions into the flat format needed by the table.
     * Server already filtered by targetEntity — no client-side filtering needed.
     */
    reduceCustomFields () {
      const fieldsReduced = this.allDefinitions
        .map(definition => {
          const { id, attributes } = definition
          const { description, fieldType, isRequired, name, options } = attributes

          return {
            id,
            name,
            description,
            fieldType,
            ...(this.isActiveTabStatementContext && { isRequired }),
            options: JSON.parse(JSON.stringify(options)),
            open: false,
            edit: false,
          }
        })

      this.customFieldItems = []
      fieldsReduced.forEach(field => {
        this.customFieldItems.push(field)
      })
    },

    removeOptionInput (index) {
      this.newFieldOptions.splice(index, 1)
    },

    requestConfirmation (message) {
      this.currentConfirmMessage = message

      return this.$refs.confirmationDialog.open()
    },

    resetEditedUnsavedField (customField) {
      const { description = '', isRequired = false, name = '', options = [] } = this.initialRowData

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

    saveEditedFields () {
      const isDataValid = this.validateNamesAreUnique(this.newRowData.name, this.newRowData.options)

      if (!isDataValid) {
        return
      }

      this.requestConfirmation(this.editWarningMessage)
        .then(isConfirmed => {
          if (!isConfirmed) {
            return
          }

          const sourceDefinition = this.allDefinitions.find(definition => definition.id === this.newRowData.id)
          const { description = '', isRequired, name, options } = this.newRowData

          const updatedPayload = {
            ...sourceDefinition,
            attributes: Object.fromEntries(
              Object.entries({
                ...sourceDefinition.attributes,
                description,
                isRequired,
                name,
                options,
              }).filter(([key]) => key !== 'fieldType'),
            ),
          }

          updateCustomFieldDefinition(this.newRowData.id, updatedPayload, this.procedureId)
            .then(() => {
              const idx = this.customFieldItems.findIndex(item => item.id === sourceDefinition.id)
              this.customFieldItems[idx] = { ...this.newRowData }
              this.setEditMode(sourceDefinition, false)
            })
            .finally(() => {
              this.fetchCustomFields()
            })
        })
    },

    /**
     * Prepare payload and send create request for custom field.
     * After save, switches to the tab matching the created field's targetEntity
     * so the new field is immediately visible.
     * @param customFieldData {Object}
     * @param customFieldData.description {String}
     * @param customFieldData.fieldType {String}
     * @param customFieldData.isRequired {Boolean}
     * @param customFieldData.name {String}
     * @param customFieldData.targetEntity {String}
     */
    saveNewField (customFieldData) {
      const { description, fieldType, isRequired, name, targetEntity } = customFieldData
      const options = this.newFieldOptions.filter(option => option.label !== '')
      const isDataValid = this.validateNamesAreUnique(name, options)

      if (!isDataValid) {
        return
      }

      this.isLoading = true

      const previousTabId = this.activeTabId

      const customFieldAttributes = {
        description,
        fieldType,
        name,
        options,
        ...(targetEntity === 'STATEMENT' && { isRequired }),
        sourceEntity: this.isProcedureTemplate ? 'PROCEDURE_TEMPLATE' : 'PROCEDURE',
        sourceEntityId: this.procedureId,
        targetEntity,
      }

      createCustomFieldDefinition(customFieldAttributes, this.procedureId)
        .then(() => {
          this.isSuccess = true
          this.activeTabId = targetEntity
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(err => {
          console.error(err)
        })
        .finally(() => {
          this.isLoading = false
          this.isSuccess = false
          this.resetNewFieldForm()
          /*
           * If the tab did not change, the watcher will not fire, so fetch manually.
           * If the tab changed, the watcher handles the fetch.
           */
          if (this.activeTabId === previousTabId) {
            this.fetchCustomFields()
          }
        })
    },

    setActiveTabId (id) {
      if (id) {
        this.activeTabId = id
      }
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
        ...(this.isActiveTabStatementContext && { isRequired }),
        name,
        options: JSON.parse(JSON.stringify(options)),
      }
    },

    setNewRowData (rowData) {
      const { description = '', id, isRequired, name, options } = rowData

      this.newRowData = {
        id,
        description,
        ...(this.isActiveTabStatementContext && { isRequired }),
        name,
        options,
      }
    },

    showOptions (rowData) {
      const idx = this.customFieldItems.findIndex(el => el.id === rowData.id)

      this.customFieldItems[idx].open = true
    },

    /**
     * @param customFieldName {String}
     * @param customFieldOptions {Array} Array of objects with label property
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

    if ('STATEMENT' in this.targetOptions) {
      this.fetchStatementsCount()
    }
  },
}
</script>
