<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <article>
    <h2>
      {{ survey.title }}
    </h2>
    <div v-cleanhtml="survey.description" />
    <div
      v-if="showForm"
      :class="prefixClass('u-mv u-pv border--top')">
      <template
        v-if="hasVoted === false">
        <h2>{{ Translator.trans('answer.yours') }}</h2>
        <div>{{ survey.title }}</div>
        <form
          :class="prefixClass('o-form u-pv')"
          novalidate>
          <div>
            <div
              :class="prefixClass('u-mb')"
              role="radiogroup">
              <button
                role="radio"
                :aria-checked="voteValue === true"
                :class="[(voteValue === true) ? prefixClass('is-selected') : '', prefixClass('btn-blank survey-button u-mr')]"
                @click.prevent="setVoteValue(true)">
                <i
                  :class="prefixClass('fa fa-thumbs-o-up u-mr-0_25')"
                  aria-hidden="true" />
                {{ Translator.trans('survey.support.positive') }}
              </button>
              <button
                role="radio"
                :aria-checked="voteValue === false"
                :class="[(voteValue === false) ? prefixClass('is-selected') : '', prefixClass('btn-blank survey-button u-mr')]"
                @click.prevent="setVoteValue(false)">
                <i
                  :class="prefixClass('fa fa-thumbs-o-down u-mr-0_25')"
                  aria-hidden="true" />
                {{ Translator.trans('survey.support.negative') }}
              </button>
            </div>

            <dp-text-area
              :attributes="['rows=5', 'cols=10']"
              :class="prefixClass('display--inline-block u-mv-0_5')"
              :hint="Translator.trans('survey.hint.public.check')"
              id="vote_comment"
              :label="Translator.trans('survey.comment.yours')"
              maxlength="400"
              v-model="commentText" />

            <div>
              <dp-checkbox
                id="r_privacy"
                v-model="checks.privacy"
                :label="{
                  text: Translator.trans('explanation.statement.privacy')
                }"
                required />
            </div>
            <div>
              <dp-checkbox
                id="r_gdpr_consent"
                v-model="checks.gdpr"
                :label="{
                  text: Translator.trans('confirm.gdpr.consent', { link: Routing.generate('DemosPlan_misccontent_static_dataprotection'), orgaId: orgaId })
                }"
                required />
            </div>
            <div>
              <dp-checkbox
                id="r_confirm_locality"
                v-model="checks.localityAndTerms"
                :label="{
                  text: Translator.trans('statement.confirm.terms', { path: Routing.generate('DemosPlan_misccontent_terms_of_use') })
                }"
                required />
            </div>
            <button
              @click.prevent="sendVote"
              :class="[buttonBusy ? prefixClass('is-busy pointer-events-none') : '', prefixClass('btn btn-primary u-mt-0_5')]">
              {{ Translator.trans('answer.send') }}
            </button>
          </div>
        </form>
      </template>
      <template v-else>
        <h2>{{ Translator.trans('thank.you') }}</h2>
        <p>{{ Translator.trans('answer.yours.sent') }}</p>
      </template>
    </div>
    <div
      v-if="showResults"
      :class="prefixClass('u-mv u-pv border--top')">
      <h2>{{ votesTotalHeading }}: {{ votes.total }}</h2>
      <dp-survey-chart
        v-if="showChart && votes.total > 0"
        use-css-prefix
        branded
        :title="survey.title"
        :votes="votes" />
      <template v-else-if="votes.total > 0">
        <div :class="prefixClass('layout__item u-p-0_5')">
          <h3>{{ Translator.trans('affirmative') }}: {{ votes.nPositive }} ({{ votes.percentagePositive }}%)</h3>
        </div>
        <div :class="prefixClass('layout__item u-p-0_5')">
          <h3>{{ Translator.trans('negative') }}: {{ votes.nNegative }} ({{ votes.percentageNegative }}%)</h3>
        </div>
      </template>
      <div>
        <div v-if="comments.length > 0">
          <p :class="prefixClass('u-pv-0_5')">
            {{ Translator.trans('survey.comments.list') }}:
          </p>
          <div :class="prefixClass('layout__item u-pl-0 u-1-of-1-lap-down u-1-of-2-lap-up')">
            <h3 :class="prefixClass('color-cta-dark u-mb-0')">
              <i
                :class="prefixClass('fa fa-thumbs-o-up u-mr-0_25')"
                aria-hidden="true" />
              {{ Translator.trans('survey.support.positive.because') }}
            </h3>
            <dp-public-survey-comment
              use-css-prefix
              v-for="comment in survey.votes.positiveVotes"
              :comment="comment"
              :key="comment.id" />
            <p
              :class="prefixClass('font-size-small u-mt')"
              v-if="survey.votes.positiveVotes.length === 0">
              {{ Translator.trans('explanation.noentries') }}
            </p>
          </div>
          <div :class="prefixClass('layout__item u-pl-0 u-1-of-1-lap-down u-1-of-2-lap-up float--right-lap-up')">
            <h3 :class="prefixClass('color-cta-dark u-mb-0')">
              <i
                :class="prefixClass('fa fa-thumbs-o-down u-mr-0_25')"
                aria-hidden="true" />
              {{ Translator.trans('survey.support.negative.because') }}
            </h3>
            <dp-public-survey-comment
              use-css-prefix
              v-for="comment in survey.votes.negativeVotes"
              :comment="comment"
              :key="comment.id" />
            <p
              :class="prefixClass('font-size-small u-mt')"
              v-if="survey.votes.negativeVotes.length === 0">
              {{ Translator.trans('explanation.noentries') }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </article>
</template>

<script>
import {
  checkResponse,
  CleanHtml,
  dpApi,
  DpCheckbox,
  DpTextArea,
  MatchMedia,
  prefixClassMixin
} from '@demos-europe/demosplan-ui/src'
import DpPublicSurveyComment from '@DpJs/components/procedure/survey/DpPublicSurveyComment'
import DpSurveyChart from '@DpJs/components/procedure/survey/DpSurveyChart'

export default {
  name: 'DpPublicSurvey',

  components: {
    DpCheckbox,
    DpPublicSurveyComment,
    DpSurveyChart,
    DpTextArea
  },

  directives: {
    cleanhtml: CleanHtml
  },

  mixins: [prefixClassMixin],

  props: {
    isLoggedIn: {
      type: Boolean,
      required: false
    },

    userId: {
      type: String,
      required: false,
      default: ''
    },

    survey: {
      type: Object,
      required: false,
      default: () => ({})
    },

    orgaId: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      hasVoted: !this.survey.userCanVote,
      voteValue: null,
      commentText: '',
      buttonBusy: false,
      showChart: true,
      checks: {
        privacy: false,
        gdpr: false,
        localityAndTerms: false
      },
      votes: this.survey.votes
    }
  },

  computed: {
    comments () {
      return this.survey.votes.positiveVotes.concat(this.survey.votes.negativeVotes)
    },

    showForm () {
      return this.isLoggedIn && this.survey.userCanVote && hasPermission('feature_surveyvote_may_vote') && this.survey.status === 'participation'
    },

    showResults () {
      return (this.survey.status === 'evaluation' || this.survey.status === 'completed') || (this.isLoggedIn && this.hasVoted)
    },

    counterText () {
      return Translator.trans('input.text.maxlength.with.placeholder').replace('{maxlength}', 400).replace('{placeholder}', 400 - this.commentText.length)
    },

    votesTotalHeading () {
      return Translator.trans(this.survey.status === 'participation' ? 'survey.votes.until.now' : 'survey.votes.total')
    }
  },

  methods: {
    async sendVote () {
      const body = {
        data: {
          type: 'surveyVotes',
          attributes: {
            isAgreed: this.voteValue,
            text: this.commentText,
            r_privacy: this.checks.privacy,
            r_gdpr_consent: this.checks.gdpr,
            r_confirm_locality: this.checks.localityAndTerms
          },
          relationships: {
            survey: {
              data: {
                id: this.survey.id,
                type: 'surveys'
              }
            },
            user: {
              data: {
                id: this.userId,
                type: 'users'
              }
            }
          }
        }
      }

      if (this.voteValue === null || Object.values(this.checks).some(el => el === false)) {
        return dplan.notify.notify('error', Translator.trans('error.mandatoryfields.no_asterisk'))
      }
      try {
        this.buttonBusy = true
        const response = await dpApi.post(Routing.generate('dplan_surveyvote_create'), {}, { data: body })
        checkResponse(response)
        this.hasVoted = true
        this.buttonBusy = false
        this.votes = response.data.meta.votes
      } catch (err) {
        this.buttonBusy = false
      }
    },

    setVoteValue (val) {
      this.voteValue = val
    }
  },

  mounted () {
    const matchMedia = new MatchMedia()
    const currentBreakpoint = matchMedia.getCurrentBreakpoint()
    // Don't show the chart on small screens
    if (currentBreakpoint !== 'desk-up' && currentBreakpoint !== 'wide') {
      this.showChart = false
    }
  }
}
</script>
