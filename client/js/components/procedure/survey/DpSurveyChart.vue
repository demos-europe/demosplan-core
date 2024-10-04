<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dl :class="prefixClass(`survey-chart ${ branded ? 'survey-chart--branded' : null } border relative u-ml-0 flow-root`)">
    <dt
      v-text="title"
      :class="prefixClass('sr-only')" />
    <dd
      :class="prefixClass('u-m-0 u-mb-0_5')">
      {{ Translator.trans('survey.votes.total') }}: <strong>{{ votes.total }}</strong> (100%)
    </dd>

    <dd
      :class="prefixClass('survey-chart__bar u-m-0 float-left relative')"
      :style="{ 'width': `calc(${votes.percentagePositive}% - 1px)` }">
      <span
        :class="prefixClass('survey-chart__bar-inner absolute u-bottom-0 u-left-0 whitespace-nowrap')">
        {{ Translator.trans('affirmative') }}: <strong>{{ votes.nPositive }}</strong> ({{ votes.percentagePositive }}%)
      </span>
    </dd>

    <dd
      :class="prefixClass('survey-chart__bar u-m-0 float-right relative')"
      :style="{ 'width': `${votes.percentageNegative}%` }">
      <span
        :class="prefixClass('survey-chart__bar-inner absolute u-bottom-0 u-right-0 whitespace-nowrap')">
        {{ Translator.trans('negative') }}: <strong>{{ votes.nNegative }}</strong> ({{ votes.percentageNegative }}%)
      </span>
    </dd>
  </dl>
</template>

<script>
import { prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpSurveyChart',

  mixins: [prefixClassMixin],

  props: {
    /**
     * The title aka. question of the survey is used as the (visually hidden) definition term
     * to improve up semantics of the rendered Html.
     */
    title: {
      type: String,
      required: true
    },

    /**
     * Values of votes.
     * The expected scheme of the Object is:
     * {
     *     nPositive:          Number
     *     percentagePositive: Number
     *     nNegative:          Number
     *     percentageNegative: Number
     * }
     */
    votes: {
      type: Object,
      required: true
    },

    /**
     * Define if the bars should use ui or branding color scheme.
     */
    branded: {
      type: Boolean,
      default: false
    }
  },

  mounted () {
    if (parseFloat(this.votes.percentagePositive) + parseFloat(this.votes.percentageNegative) !== 100) {
      console.error('DpSurveyChart: incorrect input props (votes do not add up to 100%)!')
    }
  }
}
</script>
