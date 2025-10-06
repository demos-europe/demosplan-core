<template>
  <fieldset
    v-if="hasProcedureInfoPermission"
    class="o-wizard"
    name="infoForm"
    data-dp-validate="infoForm"
    :data-dp-validate-topic="Translator.trans('wizard.topic.info')"
    :data-wizard-finished="procedureSettings.infoComplete ? true : null"
    :data-wizard-topic="Translator.trans('wizard.topic.info')">

    <legend
      class="js__toggleAnything"
      data-toggle-id="procedureInfo"
      data-cy="procedureInfo">
      <i class="caret"></i>
        {{ Translator.trans('wizard.topic.info') }}
      <i class="fa fa-check"></i>
    </legend>

    <div
      class="o-wizard__content"
      data-toggle-id="procedureInfo">
      <div
        v-if="hasPermission('field_procedure_description')"
        class="o-wizard__main">

        <!-- oeb_desc -->
        <div class="u-mb">
          <dp-label
            class="mb-0"
            for="counter_oeb_desc_input"
            :text="Translator.trans('procedure.description.public')"
            :tooltip="Translator.trans('text.procedure.description.wizard')"
          />
          <dp-editor
            id="counter_oeb_desc_input"
            data-cy="procedureInfo:procedureDescriptionPublic"
            hidden-input="r_externalDesc"
            :maxlength="10000"
            :toolbar-items="{ listButtons: false }"
            :value="procedureSettings.externalDesc ? procedureSettings.externalDesc : Translator.trans('notspecified')">
          </dp-editor>
        </div>

        <!-- Contact Person -->
        <div
          v-if="hasPermission('field_procedure_contact_person')"
          class="u-mb">
          <dp-label
            class="mb-0"
            for="r_publicParticipationContact"
            :text="Translator.trans('public.participation.contact')"
            :hint="Translator.trans('explanation.public.participation.contact')"
            :tooltip="Translator.trans('text.procedure.edit.external.contact_person')"
            :required="!!hasPermission('feature_require_procedure_contact_person')"
          />
          <dp-editor
            id="r_publicParticipationContact"
            data-cy="procedureInfo:publicParticipationContact"
            :data-dp-validate-error-fieldname="Translator.trans('public.participation.contact')"
            hidden-input="r_publicParticipationContact"
            :toolbar-items="{ listButtons: false }"
            :maxlength="2000"
            :value="publicParticipationContactValue()"
            :required="!!hasPermission('feature_require_procedure_contact_person')"
          />
        </div>

        <!-- Pictogram -->
        <div
          v-if="hasPermission('field_procedure_pictogram') && hasPermission('area_public_participation')"
          class="u-mb">
          <label
            class="inline-block u-mb-0"
            for="r_pictogram">
            {{ Translator.trans('procedure.pictogram') }}*
          </label>
          <div v-if="procedureSettings.pictogram">
            <img
              class="layout__item u-1-of-6 u-pl-0 u-mb"
              :src="Routing.generate('core_logo', { 'hash': procedureSettings.pictogram.hash })"
              :alt="procedureSettings.pictogram.altText ? procedureSettings.pictogram.altText : ('procedure.pictogram')">
            <label class="layout__item u-1-of-3 cursor-pointer weight--normal">
              {{ Translator.trans('procedure.pictogram.delete')}}
              <input
                name="r_deletePictogram"
                type="checkbox"
                value="1">
            </label>
            <a
              target="_blank"
              rel="noopener"
              :href="Routing.generate('core_file_procedure', { 'hash': procedureSettings.pictogram.hash, 'procedureId': procedureId })">
              {{ procedureSettings.pictogram.name }}
            </a>
          </div>

          <div v-else>
            <dp-label
              for="r_pictogram"
              text=""
              :hint="Translator.trans('text.procedure.edit.external.pictogram')"
            />
            <dp-upload-files
              id="r_pictogram"
              allowed-file-types="img"
              :basic-auth="dplan.settings.basicAuth"
              data-cy="upload:pictogram"
              :get-file-by-hash="hash => Routing.generate('core_file', { hash: hash, procedureId: procedureId })"
              :max-file-size="5242880"
              name="r_pictogram"
              needs-hidden-input
              :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '5 MB' }) }"
              :tus-endpoint="dplan.paths.tusEndpoint"
            />
          </div>

          <dp-input
            id="r_pictogramCopyright"
            class="my-2"
            data-cy="procedure:pictogramCopyright"
            name="r_pictogramCopyright"
            v-model="pictogramCopyright"
            :label="{
              text: Translator.trans('procedure.pictogram.copyright')
            }"
            required
          />

          <dp-input
            id="r_pictogramAltText"
            class="my-2"
            data-cy="procedure:pictogramAltText"
            name="r_pictogramAltText"
            v-model="pictogramAltText"
            :label="{
              text: Translator.trans('procedure.pictogram.altText'),
              tooltip: Translator.trans('procedure.pictogram.altText.toolTipp')
            }"
            required
          />
        </div>

        <!-- Procedure categories -->
        <div
          v-if="hasPermission('feature_procedure_categories_edit')"
          class="u-mb">
          <label
            class="u-mb-0_25"
            for="r_procedure_categories">
            {{ Translator.trans('categories') }}
          </label>
          <dp-multiselect
            id="r_procedure_categories"
            v-model="selectedProcedureCategories"
            label="name"
            multiple
            :options="availableProcedureCategories"
            track-by="id">
            <template v-slot:option="{ props }">
              {{ props.option.name }}
            </template>
            <template v-slot:tag="{ props }">
                <span class="multiselect__tag">
                  {{ props.option.name }}
                  <i
                    aria-hidden="true"
                    @click="props.remove(props.option)"
                    tabindex="1"
                    class="multiselect__tag-icon">
                  </i>
                  <input
                    type="hidden"
                    :value="props.option.id"
                    name="r_procedure_categories[]"/>
                </span>
            </template>
          </dp-multiselect>
          <input
            v-if="!selectedProcedureCategories.length"
            type="hidden"
            value=""
            name="r_procedure_categories"
          />
        </div>

        <div
          v-if="hasPermission('field_procedure_linkbox')"
          class="u-mb">
          <dp-label
            class="mb-0"
            :text="Translator.trans('linkbox')"
            :tooltip="Translator.trans('text.procedure.edit.link_box')"
          />
          <dp-editor
            :value="procedureSettings.links"
            hidden-input="r_links"
            ref="r_links"
            :toolbar-items="{ linkButton: true }"
          />
        </div>
      </div>

      <label class="o-wizard__mark u-mb-0">
        <input
          data-wizard-cb
          data-cy="fieldProcedureInformationCompletions"
          name="fieldCompletions[]"
          value="infoComplete"
          type="checkbox"
          :checked="!!procedureSettings.infoComplete"
        />
        {{ Translator.trans('wizard.mark_as_done') }}
      </label>
    </div>
    <button
      type="button"
      data-cy="wizardNext"
      data-dp-validate-capture-click
      class="btn btn--primary o-wizard__btn o-wizard__btn--next hidden submit">
      {{ Translator.trans('wizard.next') }}
    </button>
  </fieldset>
</template>

<script>
import {
  DpContextualHelp,
  DpEditor,
  DpIcon,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpMultiselect
} from '@demos-europe/demosplan-ui'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'ProcedureInfoSettings',
  components: {
    DpIcon,
    DpLabel,
    DpContextualHelp,
    DpEditor,
    DpInlineNotification,
    DpInput,
    DpMultiselect,
    DpUploadFiles: defineAsyncComponent(async () => {
      const { DpUploadFiles } = await import('@demos-europe/demosplan-ui')
      return DpUploadFiles
    }),
  },

  props: {
    availableProcedureCategories: {
      type: Array,
      required: false,
      default: () => [],
    },

    initPictogramAltText: {
      required: false,
      type: String,
      default: '',
    },

    initPictogramCopyright: {
      required: false,
      type: String,
      default: '',
    },

    initProcedureCategories: {
      required: false,
      type: Array,
      default: () => [],
    },

    procedureId: {
      required: true,
      type: String,
    },

    procedureSettings: {
      required: true,
      type: Object,
    },
  },

  data () {
    return {
      pictogramAltText: this.initPictogramAltText,
      pictogramCopyright: this.initPictogramCopyright,
      selectedProcedureCategories: this.initProcedureCategories,
    }
  },

  methods: {
    hasProcedureInfoPermission () {
      return hasPermission('field_procedure_description') ||
        hasPermission('field_procedure_contact_person') ||
        (hasPermission('field_procedure_pictogram') && hasPermission('area_public_participation')) ||
        hasPermission('feature_procedure_categories_edit') ||
        hasPermission('field_procedure_linkbox')
    },

    publicParticipationContactValue () {
      const defaultText = hasPermission('feature_require_procedure_contact_person') ? '' : Translator.trans('notspecified')
      console.log('this.procedureSettings.publicParticipationContact ?? defaultText', this.procedureSettings.publicParticipationContact ?? defaultText)
      return this.procedureSettings.publicParticipationContact ?? defaultText
    },
  }
}
</script>
