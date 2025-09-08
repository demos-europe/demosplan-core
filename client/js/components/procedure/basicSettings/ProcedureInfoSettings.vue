<template>
  <!-- wizard item: Informationen zum Verfahren -->
  <fieldset
    v-if="hasProcedureInfoPermission"
    name="infoForm"
    :data-wizard-finished="procedureSettings.infoComplete ? true : null"
    :data-wizard-topic="Translator.trans('wizard.topic.info')"
    :data-dp-validate-topic="Translator.trans('wizard.topic.info')"
    class="o-wizard"
    data-dp-validate="infoForm">

    <legend
      data-toggle-id="procedureInfo"
      class="js__toggleAnything"
      data-cy="procedureInfo">
      <i class="caret"></i>
      {{ Translator.trans('wizard.topic.info') }}
      <i class="fa fa-check"></i>
    </legend>

    <div
      data-toggle-id="procedureInfo"
      class="o-wizard__content">
      <div
        v-if="hasPermission('field_procedure_description')"
        class="o-wizard__main">
        <!-- BLOCK oeb_desc -->
        <div class="u-mb">
          <label
            class="inline-block u-mb-0"
            for="counter_oeb_desc_input">
            {{ Translator.trans('procedure.description.public') }}
          </label>

          <dp-contextual-help
            class="mb-1"
            :text="Translator.trans('text.procedure.description.wizard')"
          />

          <dp-editor
            id="counter_oeb_desc_input"
            hidden-input="r_externalDesc"
            data-cy="procedureInfo:procedureDescriptionPublic"
            :toolbar-items="{ listButtons: false }"
            :maxlength="10000"
            :value="procedureSettings.externalDesc ? procedureSettings.externalDesc : Translator.trans('notspecified')">
          </dp-editor>
        </div>
        <!-- ENDBLOCK oeb_desc -->

        <div
          v-if="hasPermission('field_procedure_contact_person')"
          class="u-mb">
          <label
            class="inline-block u-mb-0"
            for="r_publicParticipationContact">
            {{ Translator.trans('public.participation.contact') }}
            <span
              v-if="hasPermission('feature_require_procedure_contact_person')"
              aria-hidden="true">*</span>
          </label>
          <dp-contextual-help
            class="mb-1"
            :text="Translator.trans('text.procedure.edit.external.contact_person')"
          />
          <p class="lbl__hint">
            {{ Translator.trans('explanation.public.participation.contact') }}
          </p>
          <dp-editor
            id="r_publicParticipationContact"
            hidden-input="r_publicParticipationContact"
            data-cy="procedureInfo:publicParticipationContact"
            :data-dp-validate-error-fieldname="Translator.trans('public.participation.contact')"
            :toolbar-items="{ listButtons: false }"
            :maxlength="2000"
            :value="publicParticipationContactValue"
            :required="!!hasPermission('feature_require_procedure_contact_person')"
          />
        </div>

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
              text=""
              :hint="Translator.trans('text.procedure.edit.external.pictogram')"
              for="r_pictogram"
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
            v-model="pictogramCopyright"
            :label="{
                text: Translator.trans('procedure.pictogram.copyright')
              }"
            class="my-2"
            data-cy="procedure:pictogramCopyright"
            name="r_pictogramCopyright"
            required
          />

          <dp-input
            id="r_pictogramAltText"
            v-model="pictogramAltText"
            :label="{
                text: Translator.trans('procedure.pictogram.altText'),
                tooltip: Translator.trans('procedure.pictogram.altText.toolTipp')
              }"
            class="my-2"
            data-cy="procedure:pictogramAltText"
            name="r_pictogramAltText"
            required
          />
        </div>

        <!-- procedure categories -->
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
          <label
            class="inline-block u-mb-0"
            for="r_links">
            {{ Translator.trans('linkbox') }}
          </label>

          <dp-contextual-help
            class="mb-1"
            :text="Translator.trans('text.procedure.edit.link_box')"
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
          :checked="procedureSettings.infoComplete ? true : false"
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
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpMultiselect
} from '@demos-europe/demosplan-ui'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'ProcedureInfoSettings',
  components: {
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

      return this.procedureSettings.publicParticipationContact ?? defaultText
    },
  }
}
</script>
