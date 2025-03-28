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
      class="text-right">
      <dp-button
        data-cy="segmentFields:addField"
        @click="openNewFieldForm"
        :text="Translator.trans('field.add')" />
    </div>

    <div
      v-if="isNewFieldFormOpen"
      class="relative"
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
            :text="Translator.trans('options')" />
          <dp-input
            id="newFieldOption:1"
            class="mb-2 w-[calc(100%-26px)]"
            data-cy="segmentFields:newFieldOption1"
            v-model="newField.options[0]"
            maxlength="250" />
          <dp-input
            id="newFieldOption:2"
            class="mb-2 w-[calc(100%-26px)]"
            data-cy="segmentFields:newFieldOption2"
            v-model="newField.options[1]"
            maxlength="250" />

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
          @primary-action="dpValidateAction('addNewFieldForm', () => saveNewField(newField), false)"
          @secondary-action="closeNewFieldForm" />
      </div>
    </div>
  </div>
</template>

<script>
import {
  DpButton,
  DpButtonRow,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpLoading,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'

export default {
  name: 'AdministrationSegmentsFieldsList',

  components: {
    DpButton,
    DpButtonRow,
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
    }
  },

  data () {
    return {
      isLoading: false,
      isNewFieldFormOpen: false,
      newField: {
        name: '',
        description: '',
        options: [
          '',
          ''
        ]
      }
    }
  },

  computed: {
    additionalOptions () {
      return this.newField.options.filter((option, index) => index > 1)
    },

    helpTextDismissibleKey () {
      return `${this.currentUserId}:procedureAdministrationSegmentsFieldsHint`
    }
  },

  methods: {
    addOptionInput () {
      this.newField.options.push('')
    },

    closeNewFieldForm () {
      this.isNewFieldFormOpen = false
    },

    openNewFieldForm () {
      this.isNewFieldFormOpen = true
    },

    removeOptionInput (index) {
      this.newField.options.splice(index, 1)
    },

    saveNewField () {
      this.isLoading = true
      this.newField.options = this.newField.options.filter(option => option !== '')
    }
  }
}
</script>
