<template>
  <div class="procedure-type-form">

    <!-- Hints -->
    <p v-if="isCreate && !selectedProcedureTypeId">
      {{ Translator.trans('text.procedures.type.create.hint') }}
    </p>
    <p v-else>
      {{ Translator.trans('text.procedures.type.edit.hint') }}
    </p>

    <!-- Procedure Type Selector for Create -->
    <procedure-type-select
      v-if="isCreate"
      class="u-mb"
      :procedure-types="procedureTypes"
      :selected-id="selectedProcedureTypeId" />

    <!-- Main Form -->
    <form
      v-if="!isCreate || selectedProcedureTypeId"
      id="administrationProcedureTypeForm"
      :action="formAction"
      method="post"
      data-dp-validate>
      <!-- General Settings -->
      <section>
        <h2 class="u-mb-0">{{ Translator.trans('general.settings') }}</h2>
        <div class="u-pt">
          <input
            name="action"
            type="hidden"
            value="">
          <input
            name=""
            type="hidden"
            :value="formData.id">
          <input
            name="_token"
            type="hidden"
            :value="csrfToken">

          <div class="u-mb-0_25">
            <dp-input
              type="text"
              :model-value="formData.name"
              :label="{
                text: Translator.trans('text.procedures.type.name')
              }"
              required
            />
          </div>

          <dp-input
            class="u-mb-0_25"
            type="text"
            :model-value="formData.description"
            :label="{
              text: Translator.trans('description')
            }"
          />

          <dp-checkbox
            :model-value="formData.allowedToEnableMap"
            class="u-mb-0_25"
            :label="{
              text: Translator.trans('map.allow.procedure.type.activate')
            }"
          />

          <dp-checkbox
            v-if="hasPermission('field_statement_priority_area')"
            v-model="formData.hasPriorityArea"
            class="u-mb-0_25"
            :label="{
              text: Translator.trans('procedure.behavior.hasPriorityArea')
            }" />

          <div class="flex flex-row">
            <dp-checkbox
              id="procedureBehaviorDefinition_participationGuestOnly"
              name="participationGuestOnly"
              v-model="formData.participationGuestOnly"
              class="mb-1 mr-0.5"
              :label="{
                text: Translator.trans('text.procedure.types.guests.only')
              }"
              :disabled="participationGuestOnly"
              :tooltip="Translator.trans('text.procedure.types.guests.only.tip')" />
            <dp-contextual-help :text="Translator.trans('text.procedure.types.guests.only.tip')" />
          </div>
        </div>
      </section>

      <!-- Hint Texts -->
      <section>
        <h2 class="u-mt u-mb-0">{{ Translator.trans('form.fields.and.hint.texts') }}</h2>
        <div class="u-pt">
          <p class="font-bold">{{ Translator.trans('statement.form.hint.statement') }}</p>
          <p>{{ Translator.trans('text.visible.to.loggedin.users') }}</p>
          <dp-editor
            v-model="formData.statementFormHintStatement"
            class="u-mb-0_25" />

          <dp-editor
            v-model="formData.statementFormHintPersonalData"
            class="u-mb-0_25"
          />

          <dp-editor
            v-model="formData.statementFormHintRecheck"
            class="u-mb-0_25"
          />

          <dp-editor
            v-model="formData.statementPublicSubmitConfirmationText"
            class="u-mb-0_25"
            max-length="500"
            link-button
            list-buttons="false"
            fullscreen-button="false"
            :suggestions="[{
              matcher: {
                char: '$',
                allowSpaces: false,
                startOfLine: false
              },
              suggestions: [{
                id: 'statementPublicSubmitConfirmationTextPlaceholder',
                label: 'Vorgangsnummer'
              }]
            }]"
          />

          <p class="weight&#45;&#45;bold u-mt">{{ Translator.trans('statement.form.choose.fields') }}</p>

          <p
            v-if="!guestOnly"
            class="flash flash-warning">
            {{ Translator.trans('statement.field.is.disabled') }}
          </p>

          <fieldset
            v-for="(field, index) in fieldDefinitions"
            :key="field.name">
            <p class="u-mb-0_25">
              {{ Translator.trans(`statement.fieldset.${field.name}`) }}
            </p>

            <dp-checkbox
              v-model="field.enabled"
              class="u-mb-0_25"
              :disabled="!guestOnly"
            />

            <dp-checkbox
              v-model="field.required"
              class="u-mb-0_25"
              :disabled="!guestOnly"
            />
          </fieldset>
        </div>
      </section>

      <!-- Adjustments -->
      <section>
        <h2 class="u-mb-0">{{ Translator.trans('text.adjustments') }}</h2>
        <div class="u-pt">
          <div class="u-mb-0_25">
            <dp-text-area
              class="mt-4"
              :hint="Translator.trans('map.hint.edit.explanation')"
              :label="Translator.trans('map.hint')"
              :tooltip="Translator.trans('map.hint.edit.contextual.help')"
              maxlength="2000"
              minlength="50"
              v-model="formData.mapHintDefault" />
          </div>
        </div>
      </section>

      <!-- Actions -->
      <div class="u-mv">
        <dp-button-row
          primary
          secondary
          data-cy=""
          @primary-action="submit"
          @secondary-action="Routing.generate('DemosPlan_procedureType_list')" />
      </div>
    </form>
  </div>
</template>
<script>
import { DpButtonRow, DpEditor, DpInput, DpCheckbox, DpTextArea, DpContextualHelp } from '@demos-europe/demosplan-ui'
import ProcedureTypeSelect from '@DpJs/components/procedure/admin/ProcedureTypeSelect'

export default {
  name: 'AdministrationProcedureTypeEdit',
  components: {
    DpButtonRow,
    DpEditor,
    DpInput,
    DpCheckbox,
    DpTextArea,
    DpContextualHelp,
    ProcedureTypeSelect
  },
  props: {
    csrfToken: {
      type: String,
      required: true
    },

    fieldDefinitions: {
      type: Object,
      required: true
    },

    guestOnly: {
      type: Boolean,
      default: false
    },

    initialProcedureTypeId: {
      type: [String, Number],
      default: ''
    },

    isCreate: {
      type: Boolean,
      default: false
    },

    initialFormData: {
      type: Object,
      required: true
    },

    selectedProcedureTypeId: {
      type: String,
      required: true
    },
    participationGuestOnly: { // ToDo: name to be adjusted
      type: Boolean,
      default: false
    },

    procedureTypes: {
      type: Array,
      required: true
    }
  },

  data () {
    return {
      formData: {
        id: '',
        name: '',
        description: '',
        allowedToEnableMap: false,
        participationGuestOnly: false,
        mapHintDefault: '',
        statementFormHintStatement: '',
        statementFormHintPersonalData: '',
        statementFormHintRecheck: '',
        statementPublicSubmitConfirmationText: ''
      }
    }
  },

  methods: {
    formAction () {
      return this.isCreate ? Routing.generate('DemosPlan_procedureType_create_save') : Routing.generate('DemosPlan_procedureType_edit_save', { procedureTypeId: this.selectedProcedureTypeId })
    },

    submit () {
      this.dpValidateAction('administrationProcedureTypeForm', () => {
        this.$refs.administrationProcedureTypeForm.submit()
      }, false)
    }
  },

  mounted () {
    this.formData = { ...this.formData, ...this.initialFormData }
  }
}
</script>
