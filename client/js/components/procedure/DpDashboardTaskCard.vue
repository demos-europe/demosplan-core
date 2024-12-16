<template>
  <dp-card
    :heading="Translator.trans('tasks.my')">
    <div class="u-mt">
      <span v-cleanhtml="Translator.trans('segments.assigned.now', { count: assignedSegmentCount })" />
    </div>
    <div
      class="text-right u-mt"
      v-if="assignedSegmentCount !== 0">
      <dp-button
        data-cy="dashboardTaskCard:tasksView"
        :href="userFilteredSegmentUrl"
        :text="Translator.trans('tasks.view')" />
    </div>
  </dp-card>
</template>

<script>
import { checkResponse, CleanHtml, dpApi, DpButton, DpCard } from '@demos-europe/demosplan-ui'
export default {
  name: 'DpDashboardTaskCard',

  components: {
    DpButton,
    DpCard
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    currentUserId: {
      type: String,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      assignedSegmentCount: 0,
      userHash: ''
    }
  },

  computed: {
    userFilteredSegmentUrl () {
      return Routing.generate('dplan_segments_list', { procedureId: this.procedureId }) + '/' + this.userHash
    }
  },

  mounted () {
    // Filter by current user as assignee and current procedure
    const filterQuery = {
      [this.currentUserId]: {
        condition: {
          path: 'assignee',
          value: this.currentUserId
        }
      },
      sameProcedure: {
        condition: {
          path: 'parentStatement.procedure.id',
          value: this.procedureId
        }
      }
    }

    // Get count of segments assigned to the current user
    const segmentUrl = Routing.generate('api_resource_list', { resourceType: 'StatementSegment' })
    dpApi.get(segmentUrl, { filter: filterQuery }).then(response => {
      this.assignedSegmentCount = response.data.data.length
    })

    /*
     * It is currently difficult to get the default filter hash but a filter hash is needed to retrieve an updated hash.
     * Therefore, we place a get request to the default segment list route. The backend will respond with a 302 status
     * code redirecting to the default filter hash. The 302 is handled by the browser and axios will receive the
     * redirected response. The redirected response will contain the default filter hash, which can then be extracted
     * and used to obtain an updated filter hash.
     */
    dpApi.get(Routing.generate('dplan_segments_list', { procedureId: this.procedureId }))
      .then(response => {
        const redirectUrl = response.url
        const splitUrl = redirectUrl.split('/')
        const queryHash = splitUrl[splitUrl.length - 1]
        const filterData = {
          filter: {
            ...filterQuery
          },
          searchPhrase: ''
        }

        // Get the actual filter hash
        const url = Routing.generate('dplan_rpc_segment_list_query_update', { queryHash })
        dpApi.patch(url, {}, filterData)
          .then(response => checkResponse(response))
          .then(response => {
            if (response) {
              this.userHash = response
            }
          })
      })
  }
}
</script>
