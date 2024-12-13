<template>
  <div>
    <form
      :action="Routing.generate('DemosPlan_statement_administration_tags_edit', { procedure: this.procedureId })"
      method="POST"
      name="tag_edit">

      <!-- Add csrf token -->
      <input name="_token" type="hidden" :value="dplan.csrfToken">

      <slot name="tag-list-bulk-controls" />
      <slot name="tag-list-table" />

      <fieldset class="flow-root u-pb-0 u-mt-2">

        <div class="layout u-mb">
          <label class="layout__item u-2-of-3">
            {{ Translator.trans('topic.create') }}
            <input
              data-form-actions-submit-target="#createNewTopic"
              class="layout__item o-form__control-input"
              type="text"
              name="r_newTopic"
              data-cy="nameNewTopic"
              :placeholder="Translator.trans('topic.name')">
          </label><!--
             --><div class="layout__item u-1-of-3">
          <button
            class="btn btn--primary u-mt w-full"
            id="createNewTopic"
            name="r_create"
            data-cy="addNewTopic">
            {{ Translator.trans('topic.create.short') }}
          </button>
        </div>
        </div>

        <dp-contextual-help
          class="float-right"
          :text="Translator.trans('tags.import.help')"
        ></dp-contextual-help>

        <dp-upload
          allowed-file-types="csv"
          :basic-auth="dplan.settings.basicAuth"
          name="r_importCsv"
          :tus-endpoint="dplan.paths.tusEndpoint"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.csv', { browse: '{browse}', maxUploadSize: '10GB' }) }"
          :max-number-of-files="1"
        />

        <button
          data-cy="listTags:tagsImport"
          name="r_import"
          class="btn btn--primary float-right u-mb-2">
          {{ Translator.trans('tags.import') }}
        </button>

      </fieldset>
    </form>
  </div>
</template>
<script>
import { DpContextualHelp, DpUpload } from '@demos-europe/demosplan-ui'
export default {
  name: 'TagListForm',

  components: {
    DpContextualHelp,
    DpUpload,
  },

  inject: ['topics', 'procedureId']
}
</script>
