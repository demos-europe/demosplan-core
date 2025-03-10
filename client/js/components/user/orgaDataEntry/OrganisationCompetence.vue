<template>
  <fieldset
    id="organisationCompetence"
    class="w-3/4">
    <legend class="font-size-large weight--normal mb-3">
      {{ Translator.trans('competence') }}
    </legend>
    <div class="w-full mb-3">
      <dp-text-area
        id="orga_competence"
        data-cy="organisationData:paperCopy:competence"
        :hint="Translator.trans('explanation.organisation.competence')"
        :label="Translator.trans('competence.explanation')"
        :name="`${organisation.id}:competence`"
        :value="organisation.competence" />
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
