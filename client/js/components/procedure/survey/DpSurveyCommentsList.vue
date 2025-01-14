<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-loading
      v-if="isLoading"
      class="u-mt u-ml" />
    <template v-else>
      <dp-data-table
        v-if="votes.length > 0"
        :header-fields="headerFields"
        :items="rowItems"
        track-by="id">
        <template
          v-slot:createdDate>
          <p>
            {{ rowDataCreatedDate }}
          </p>
        </template>
        <template
          v-slot:status="rowData">
          <div v-if="rowData.status === 'publication_approved'">
            <i
              :aria-label="Translator.trans('publication.approved')"
              v-tooltip="Translator.trans('publication.approved')"
              class="fa fa-eye u-ml u-mt-0_25" />
          </div>
          <div v-else-if="rowData.status === 'publication_rejected'">
            <i
              :aria-label="Translator.trans('publication.rejected')"
              v-tooltip="Translator.trans('publication.rejected')"
              class="fa fa-ban u-ml u-mt-0_25" />
          </div>
          <div v-else-if="rowData.status === 'publication_pending'">
            <i
              :aria-label="Translator.trans('survey.comment.publish.check')"
              v-tooltip="Translator.trans('survey.comment.publish.check')"
              class="fa fa-exclamation-circle color-highlight u-ml u-mt-0_25" />
          </div>
        </template>
        <template
          v-slot:actions="rowData">
          <div>
            <button
              v-if="rowData.status !== 'publication_approved'"
              type="button"
              @click="handleUpdateItem({ id: rowData.id, publish: true })"
              data-cy="publishItem"
              :title="Translator.trans('publish')"
              :aria-label="Translator.trans('survey.comment.publish')"
              class="btn--blank o-link--default u-mh-0_25">
              <i
                class="fa fa-check"
                aria-hidden="true" />
            </button>
            <button
              v-if="rowData.status !== 'publication_rejected'"
              type="button"
              @click="handleUpdateItem({ id: rowData.id })"
              :title="Translator.trans('publication.reject')"
              data-cy="rejectItem"
              :aria-label="Translator.trans('publication.reject')"
              class="btn--blank o-link--default u-mh-0_25">
              <i
                class="fa fa-times"
                aria-hidden="true" />
            </button>
          </div>
        </template>
      </dp-data-table>
      <div v-else-if="votes.length === 0">
        <p class="flash flash-info u-mb">
          {{ Translator.trans('survey.comments.none') }}
        </p>
      </div>
    </template>
  </div>
</template>

<script>
import {
  dpApi,
  DpDataTable,
  DpLoading,
  formatDate,
  hasOwnProp
} from '@demos-europe/demosplan-ui'

export default {
  name: 'DpSurveyCommentsList',

  components: {
    DpDataTable,
    DpLoading
  },

  props: {
    surveyId: {
      type: String,
      default: ''
    }
  },

  data () {
    return {
      headerFields: [
        { field: 'comment', label: Translator.trans('survey.comment') },
        { field: 'createdDate', label: Translator.trans('date') },
        { field: 'status', label: Translator.trans('status') },
        { field: 'actions', label: Translator.trans('actions') }
      ],
      isLoading: true,
      votes: []
    }
  },

  computed: {
    rowDataCreatedDate () {
      return formatDate(this.rowData.createdDate)
    },

    // Transform into format needed by DpDataTable
    rowItems () {
      return this.votes.map(vote => {
        return {
          comment: vote.attributes.text,
          createdDate: vote.attributes.createdDate,
          id: vote.id,
          status: vote.attributes.textReview
        }
      })
    }
  },

  methods: {
    fetchVotes () {
      dpApi.get(
        Routing.generate('dplan_surveyvote_list', { surveyId: this.surveyId })
      )
        .then((response) => {
          this.votes = response.data.data
          this.isLoading = false
        })
    },

    handleUpdateItem ({ id, publish = false }) {
      const textReview = publish ? 'publication_approved' : 'publication_rejected'

      this.updateItem({ id, textReview })
        .then(() => {
          const curr = this.votes.find(vote => vote.id === id)
          curr.attributes.textReview = textReview
        })
    },

    updateItem ({ id, textReview }) {
      return dpApi({
        method: 'PATCH',
        url: Routing.generate('dplan_surveyvote_update', { surveyVoteId: id }),
        data: {
          data: {
            type: 'SurveyVote',
            id,
            attributes: {
              textReview
            }
          }
        },
        headers: {
          'Content-type': 'application/vnd.api+json',
          Accept: 'application/vnd.api+json'
        }
      })
        .then((response) => {
          if (hasOwnProp(response.data.meta, 'messages') && hasOwnProp(response.data.meta.messages, 'confirm')) {
            dplan.notify.notify('confirm', response.data.meta.messages.confirm[0])
          }
        })
        .catch(e => dplan.notify.error(Translator.trans('error.api.generic')))
    }
  },

  mounted () {
    this.fetchVotes()
  }
}
</script>
