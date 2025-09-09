<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div v-scroller>
    <h2 class="u-mt-0_25 o-hellip">
      {{ procedureName }}
    </h2>

    <ul class="u-m-0">
      <li>
        <h3 class="u-m-0 inline">
          <i
            class="c-publicindex__icon-content fa fa-calendar"
            aria-hidden="true" />
          <span class="sr-only">
            {{ Translator.trans('period') }}
          </span>
        </h3>
        <p
          class="u-m-0 inline"
          v-cleanhtml="period" />
      </li>
      <li>
        <h3 class="u-m-0 inline">
          <i
            class="c-publicindex__icon-content fa fa-puzzle-piece"
            aria-hidden="true" />
          <span class="sr-only">
            {{ Translator.trans('procedure.public.phase') }}
          </span>
        </h3>
        <p class="u-m-0 inline">
          {{ phaseName }}
        </p>
      </li>
      <li>
        <h3 class="u-m-0 inline">
          <i
            class="c-publicindex__icon-content fa fa-university"
            aria-hidden="true" />
          <span class="sr-only">
            {{ Translator.trans('administration.alt') }}
          </span>
        </h3>
        <p class="u-m-0 inline">
          {{ procedure.owningOrganisationName }}
        </p>
      </li>
      <li
        v-if="hasPermission('feature_procedures_count_released_drafts') && procedure.statementSubmitted > 0">
        <h3 class="u-m-0 inline">
          <i
            class="c-publicindex__icon-content fa fa-comment-o"
            aria-hidden="true" />
          <span class="sr-only">
            {{ Translator.trans('statements.submitted.institution') }}
          </span>
        </h3>
        <p class="u-m-0 inline">
          {{ Translator.trans('statements.submitted.institution') }}: {{ procedure.statementSubmitted }}
        </p>
      </li>
      <li class="u-mt-0_5">
        <h3 class="u-mb-0_25 weight--normal">
          <i
            class="c-publicindex__icon-content fa fa-file-text-o"
            aria-hidden="true" />
          {{ Translator.trans('information.short') }}
        </h3>
        <p
          v-if="hasDescription"
          v-cleanhtml="procedure.externalDescription" />
        <p v-else>
          {{ Translator.trans('information.short.empty') }}
        </p>
      </li>
    </ul>

    <a
      class="weight--bold no-underline block"
      data-cy="toProcedureDetail"
      :href="Routing.generate('DemosPlan_procedure_public_detail', { procedure: procedure.id })">
      {{ Translator.trans('procedure.view') }}
      <i
        class="c-publicindex__icon-action fa fa-chevron-circle-right"
        aria-hidden="true" />
    </a>
  </div>
</template>

<script>
import { CleanHtml } from '@demos-europe/demosplan-ui'
import Scroller from '@DpJs/directives/scroller'
import SharedMethods from './../SharedProcedureMethods'

export default {
  name: 'DetailView',

  directives: {
    cleanhtml: CleanHtml,

    /*
     * The "v-scroller" directive sets max-height on an element so that it does not exceed the viewport,
     * then it applies a utility class that makes that container scrollable.
     */
    scroller: Scroller
  },

  props: {
    procedure: {
      type: Object,
      required: true
    }
  },

  computed: Object.assign({
    hasDescription () {
      return this.procedure.externalDescription !== ''
    }
  }, SharedMethods)
}
</script>
