<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li
    class="c-publicindex__list-item"
    data-cy="procedureListItem">
    <a
      @click.prevent="showDetailView(procedure.id)"
      class="block o-link--default cursor-pointer o-hellip"
      data-cy="zoomIn"
      href="#">
      {{ procedureName() }}
    </a>
    <span
      class="block"
      v-cleanhtml="procedurePeriod" />
    <span class="block">
      <i
        class="c-publicindex__icon-content fa fa-puzzle-piece"
        aria-hidden="true"
        :title="Translator.trans('procedure.public.phase')" />
      {{ phaseName() }}
    </span>
    <span class="block">
      <i
        class="c-publicindex__icon-content fa fa-university"
        aria-hidden="true"
        :title="Translator.trans('administration.alt')" />
      {{ procedure.owningOrganisationName }}
    </span>
    <span
      class="block"
      v-if="hasPermission('feature_procedures_count_released_drafts') && procedure.statementSubmitted > 0">
      <i
        class="c-publicindex__icon-content fa fa-comment-o"
        aria-hidden="true" />
      {{ Translator.trans('statements.submitted.institution') }}: {{ procedure.statementSubmitted }}
    </span>
  </li>
</template>

<script>
import { CleanHtml } from '@demos-europe/demosplan-ui'
import { mapActions } from 'vuex'
import SharedMethods from './../SharedProcedureMethods'

export default {
  name: 'DpListItem',

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    procedure: {
      type: Object,
      required: true
    }
  },

  data () {
    return {
      detailView: false
    }
  },

  computed: {
    procedurePeriod () {
      return `<i class="c-publicindex__icon-content fa fa-calendar" aria-hidden="true" :title="Translator.trans('period')"></i>${this.period()}`
    }
  },

  methods: Object.assign({
    ...mapActions('Procedure', [
      'setProperty',
      'showDetailView'
    ])
  }, SharedMethods)
}
</script>
