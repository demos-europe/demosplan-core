<template>
  <fieldset
    v-if="hasPaperCopyPermission"
    id="paperCopy"
    class="w-3/4">
    <legend class="font-size-large weight--normal u-mb-0_75">
      {{ Translator.trans('copies.paper') }}
    </legend>

    <div
      v-if="hasPermission('field_organisation_paper_copy')"
      class="w-full mb-3">
      <dp-select
        id="orga_paperCopy"
        :name="`${organisation.id}:paperCopy`"
        v-model="organisation.paperCopy"
        data-cy="organisationData:paperCopy:select"
        :label="{
          text: Translator.trans('copies.paper'),
          hint: Translator.trans('explanation.organisation.copies.paper')
        }"
        :selected="organisation.paperCopy"
        :options="paperCopyCountOptions()" />
    </div>

    <div
      v-if="hasPermission('field_organisation_paper_copy_spec')"
      class="w-full mb-3">
      <dp-text-area
        id="orga_paperCopySpec"
        data-cy="organisationData:paperCopy:specification"
        :name="`${organisation.id}:paperCopySpec`"
        :value="organisation.paperCopySpec"
        :label="Translator.trans('copies.kind')"
        :hint="Translator.trans('explanation.organisation.copies.kind')" />
    </div>

    <div
      v-if="hasPermission('field_organisation_competence')"
      class="w-full mb-3">
      <dp-text-area
        id="orga_competence"
        data-cy="organisationData:paperCopy:competence"
        :name="`${organisation.id}:competence`"
        :value="organisation.competence"
        :label="Translator.trans('competence.explanation')"
        :hint="Translator.trans('explanation.organisation.competence')" />
    </div>
  </fieldset>
</template>

<script>
import { DpSelect, DpTextArea } from '@demos-europe/demosplan-ui'

export default {
  name: 'PaperCopyPreferences',

  components: {
    DpSelect,
    DpTextArea
  },

  props: {
    organisation: {
      type: Object,
      required: true
    }
  },

  computed: {
    hasPaperCopyPermission () {
      return hasPermission('field_organisation_paper_copy') ||
        hasPermission('field_organisation_paper_copy_spec') ||
        hasPermission('field_organisation_competence')
    }
  },

  methods: {
    paperCopyCountOptions () {
      return Array.from({ length: 11 }, (_, i) => ({
        label: i.toString(),
        value: i
      }))
    }
  }
}
</script>
