<template>
  <fieldset
    v-if="hasPermission('field_organisation_competence')"
    id="organisationCompetence"
    class="w-3/4">
    <legend class="font-size-large weight--normal u-mb-0_75">
      {{ Translator.trans('competence') }}
    </legend>
    <div
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
import { DpTextArea } from '@demos-europe/demosplan-ui'

export default {
  name: 'OrganisationCompetence',

  components: {
    DpTextArea
  },

  props: {
    organisation: {
      type: Object,
      required: true
    }
  }
}
</script>
