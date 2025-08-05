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
        <ul>
          <li
            v-for="(option, index) in displayedOptions(rowData)"
            :key="index"
            class="mb-1"
            :data-cy="`customFields:option${option.label}`">
            <div v-if="rowData.edit">
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
            </div>

            <div v-else>
              {{ option.label }}
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
  DpLoading,
  dpValidateMixin
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
      updateCustomField: 'setItem'
    }),

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
            id: storeField.id,
            attributes: {
              ...storeField.attributes,
              description,
              name,
              options
            },
            type: storeField.type
          }

          this.updateCustomField(updatedField)

          await this.saveCustomField(storeField.id)
            .then(response => {
              // Reset store on error
              if (response.status >= 400) {
                const restoredField = {
                  ...this.initialRowData,
                  id: this.newRowData.id
                }
                this.updateCustomField(restoredField)
              }
            })
            .catch(() => {
              const restoredField = {
                ...this.initialRowData,
                id: this.newRowData.id
              }
              this.updateCustomField(restoredField)
            })
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
      // Store initial state of currently edited row
      const { id, description, name, options } = rowData

      this.initialRowData = {
        description,
        name,
        options
      }

      this.newRowData = {
        id,
        description,
        name,
        options
      }

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
        this.reduceCustomFields()
      })
        .catch(err => console.error(err))
    },

    hideOptions (rowData) {
      const idx = this.getIndexOfRowData(rowData)

      this.customFieldItems[idx].open = false
    },

    getIndexOfRowData (rowData) {
      return this.customFieldItems.findIndex(el => el.id === rowData.id)
    },

    removeOptionInput (index) {
      this.newFieldOptions.splice(index, 1)
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

    setEditMode (rowData, editState = true) {
      const idx = this.customFieldItems.findIndex(el => el.id === rowData.id)

      this.customFieldItems[idx].open = editState
      this.customFieldItems[idx].edit = editState
    },

    showOptions (rowData) {
      const idx = this.customFieldItems.findIndex(el => el.id === rowData.id)

      this.customFieldItems[idx].open = true
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
        if (optionName !== '') {
          isAnyOptionNameDuplicated = !this.checkIfOptionNameIsUnique(customFieldOptions, optionName)
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
