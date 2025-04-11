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
            data-cy="segmentFields:newFieldOption1"
            v-model="newFieldOptions[0]"
            maxlength="250"
            required />
          <dp-input
            id="newFieldOption:2"
            class="mb-2 w-[calc(100%-26px)]"
            data-cy="segmentFields:newFieldOption2"
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
  </create-custom-field-form>

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
  DpDataTable,
  DpIcon,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpLoading
} from '@demos-europe/demosplan-ui'
import { mapActions } from 'vuex'
import CreateCustomFieldForm from '@DpJs/components/procedure/admin/CreateCustomFieldForm'

export default {
  name: 'AdministrationSegmentsFieldsList',

  components: {
    CreateCustomFieldForm,
    DpButton,
    DpDataTable,
    DpIcon,
    DpInlineNotification,
    DpInput,
    DpLabel,
    DpLoading
  },

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
      newFieldOptions: [
        '',
        ''
      ],
      segmentFields: []
    }
  },

  computed: {
    additionalOptions () {
      return this.newFieldOptions.filter((option, index) => index > 1)
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
      this.newFieldOptions.push('')
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
      this.isLoading = true

      const { description, name } = customFieldData
      const options = this.newFieldOptions.filter(option => option !== '')
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
