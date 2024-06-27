<template>
  <div>
    <fieldset
      v-if="hasPermission('feature_map_use_plan_draw_pdf') || hasPermission('feature_map_use_plan_pdf')"
      class="u-pb layout layout--flush"
      id="drawingData">
      <div
        v-if="hasPermission('feature_map_use_plan_draw_pdf')"
        class="u-mb">
        <dp-label
          for="r_planDrawPDF"
          :text="Translator.trans('drawing')" />
        <dp-upload-files
          id="r_planDrawPDF"
          allowed-file-types="all"
          :basic-auth="dplan.settings.basicAuth"
          :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
          name="r_planDrawPDF"
          data-cy="r_planDrawPDF"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.pdf', { browse: '{browse}', maxUploadSize: '250 MB' }) }"
          :tus-endpoint="dplan.paths.tusEndpoint"
          @file-remove="() => unsetFile('uploadedPlanDrawPdf')"
          @upload-success="(file) => setFile(file, 'uploadedPlanDrawPdf')" />
      </div>

      <div
        v-if="planDrawPdf !== ''"
        class="u-mb break-words">
        <a
          class="o-hellip"
          target="_blank"
          rel="noopener"
          :href="Routing.generate('core_file_procedure', { hash: planDrawPDF.hash, procedureId: procedureId })">
        {{ planDrawPdf.name }}
        </a>
        <p>
          <input type="checkbox" name="r_planDrawDelete" value="1">
          {{ Translator.trans('delete') }}
        </p>
      </div>

      <template v-if="hasPermission('feature_map_use_plan_pdf')">
        <dp-label
          for="r_planPDF"
          :text="Translator.trans('drawing.explanation')" />
        <dp-upload-files
          id="r_planPDF"
          allowed-file-types="pdf"
          :basic-auth="dplan.settings.basicAuth"
          :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
          name="r_planDrawPDF"
          data-cy="r_planDrawPDF"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.pdf', { browse: '{browse}', maxUploadSize: '250 MB' }) }"
          :tus-endpoint="dplan.paths.tusEndpoint"
          @file-remove="() => unsetFile('uploadedPlanPdf')"
          @upload-success="(file) => setFile(file, 'uploadedPlanPdf')" />
      </template>

      <div
        v-if="planDrawPdf !== ''"
        class="u-mb break-words">
        <a
          class="o-hellip"
          target="_blank"
          rel="noopener"
          :href="Routing.generate('core_file_procedure', { hash: planPDF.hash, procedureId: procedureId })">
          {{ planPdf.name }}
        </a>
        <p>
          <input type="checkbox" name="r_planDelete" value="1">
          {{ Translator.trans('delete') }}
        </p>
      </div>
    </fieldset>
    <fieldset
      v-if="canEditMapHint"
      id="mapHint">
      <h3>
        <label for="r_mapHint" class="u-mb-0 weight--normal">
          {{ Translator.trans('map.hint') }}*
        </label>
      </h3>
      <dp-contextual-help
        class="float-right u-mt-0_5"
        :text="Translator.trans('map.hint.edit.contextual.help')">
      </dp-contextual-help>
      <p class="lbl__hint">
        {{ Translator.trans('map.hint.edit.explanation') }}
      </p>
      <p class="lbl__hint u-mb-0_75">
        {{ Translator.trans('map.hint.warning.tooshort', { minLength: 50, maxLength: 2000 }) + ' ' + Translator.trans('map.hint.set.to.default') }}
      </p>
      <textarea
        v-text="initMapHint"
        id="r_mapHint"
        name="r_mapHint"
        class="o-form__control-textarea u-p-0_5"
        data-cy="mapAdminGislayerList:mapHint"
        required
        minlength="50"
        maxlength="2000">
      </textarea>
    </fieldset>

    <div class="text-right space-inline-s">
      <input
        class="btn btn--primary"
        type="submit"
        data-cy="saveButton"
        :value="Translator.trans('save')">
      <input
        class="btn btn--primary"
        type="submit"
        name="submit_item_return_button"
        data-cy="saveAndReturn"
        :value="Translator.trans('save.and.return.to.list')">
      <input
        v-if="canEditMapHint"
        class="btn btn--secondary"
        type="submit"
        data-cy="mapAdminGislayerList:mapHintUseDefault"
        data-skip-validation
        name="reset_map_hint"
        :value="Translator.trans('map.hint.use.default')">
    </div>
  </div>
</template>

<script>

import { DpContextualHelp, DpLabel, DpUploadFiles } from '@demos-europe/demosplan-ui'
export default {
  name: 'AdminLayerPlanDrawing',

  components: {
    DpContextualHelp,
    DpLabel,
    DpUploadFiles
  },

  props: {
    initMapHint: {
      type: String,
      required: false,
      default: ''
    },

    isMaster: {
      type: Boolean,
      required: true
    },
    planDrawPdf: {
      type: String,
      required: false,
      default: ''
    },
    planPdf: {
      type: String,
      required: false,
      default: ''
    },
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      uploadedPlanDrawPdf: null,
      uploadedPlanPdf: null
    }
  },

  computed: {
    canEditMapHint() {
      return this.isMaster !== true && hasPermission('feature_map_hint')
    }
  },

  methods: {
    unsetFile (type) {
      this[type] = null
    },

    setFile (file, type) {
      this[type] = file
    },
  }
}
</script>
