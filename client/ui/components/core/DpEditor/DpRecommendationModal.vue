<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This component contains the UI for inserting recommendations into a textarea -->
</documentation>

<template>
  <dp-modal
    ref="recommendationModal"
    content-classes="u-2-of-3">
    <template>
      <h3>{{ Translator.trans('segment.recommendation.insert.similar') }}</h3>
      <div class="layout u-mb">
        <div class="layout__item u-1-of-3">
          <span class="display--block weight--bold">
            {{ Translator.trans('segment.tags') }}
          </span>
          <div class="bg-color--grey-light-2 u-p-0_25">
            <span
              :key="id"
              v-for="(id, idx) in tagIds">
              {{ getTagTitle(id, idx) }}
            </span>
          </div>
        </div><!--
     --><div class="layout__item u-2-of-3">
          <dp-label
            :text="Translator.trans('search.text')"
            for="searchField" />
          <dp-search-field
            @search="setSearchTerm"
            @reset="setSearchTerm('')"
            class="width--100p"
            :placeholder="Translator.trans('search')" />
        </div>
      </div>

      <dp-loading v-if="isLoading" />

      <template v-else>
        <ul
          v-if="currentRecommendations.length > 0"
          class="o-list space-stack-m u-pt-0_5 border--top height-50vh overflow-auto">
          <dp-insertable-recommendation
            class="o-list__item"
            :from-other-procedure="recommendation.fromOtherProcedure"
            :key="recommendation.id"
            :procedure-name="recommendation.procedureName"
            v-for="recommendation in currentRecommendations"
            :search-term="searchTerm"
            @insert-recommendation="toggleInsert(recommendation.attributes.recommendation)"
            :recommendation="recommendation.attributes.recommendation" />
        </ul>
        <div
          v-if="currentRecommendations.length === 0"
          class="u-pt-0_5 border--top">
          {{ Translator.trans('statement.list.empty') }}
        </div>
      </template>
    </template>
  </dp-modal>
</template>

<script>
import { DpLabel, DpLoading } from 'demosplan-ui/components'
import { mapMutations, mapState } from 'vuex'
import dataTableSearch from '../DpDataTable/DataTableSearch'
import { dpApi } from 'demosplan-utils'
import DpInsertableRecommendation from './DpRecommendationModal/DpInsertableRecommendation'
import DpModal from '../DpModal'
import DpSearchField from '../form/DpSearchField'

export default {
  name: 'DpRecommendationModal',

  components: {
    DpInsertableRecommendation,
    DpLabel,
    DpLoading,
    DpSearchField,
    DpModal
  },

  // Array of procedureIds recommendations should be searched in.
  inject: ['recommendationProcedureIds'],

  props: {
    procedureId: {
      type: String,
      required: false,
      default: ''
    },

    segmentId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      searchTerm: '',
      similarRecommendations: [],
      isLoading: true
    }
  },

  computed: {
    ...mapState('statementSegment', {
      segments: 'items'
    }),

    ...mapState('tag', {
      tags: 'items'
    }),

    currentRecommendations () {
      return dataTableSearch(this.searchTerm, this.similarRecommendations, ['attributes.recommendation'])
    },

    segment () {
      return this.segments[this.segmentId]
    },

    tagIds () {
      return this.segment.hasRelationship('tags')
        ? this.segment.relationships.tags.data.map(tag => tag.id)
        : []
    },

    tagTitles () {
      return this.segment.hasRelationship('tags')
        ? this.segment.relationships.tags.data.map(tag => this.tags[tag.id].attributes.title)
        : []
    }
  },

  methods: {
    ...mapMutations('statementSegment', ['setItem']),

    getRecommendationProcedureName (recommendation, included) {
      const recommendationProcedureId = this.getRecommendationProcedureId(recommendation, included)
      const procedure = included.find(item => item.id === recommendationProcedureId)
      return procedure.attributes.name
    },

    getRecommendationProcedureId (recommendation, included) {
      const parentStatementId = recommendation.relationships?.parentStatement.data?.id || null
      const parentStatement = included.find(item => item.id === parentStatementId)
      return parentStatement.relationships.procedure.data.id
    },

    getTagTitle (id, idx) {
      return (this.tags[id] && this.tags[id].attributes.title) + ((idx < this.tagIds.length - 1 && ',') || '')
    },

    isRecommendationFromOtherProcedure (recommendation, included) {
      const recommendationProcedureId = this.getRecommendationProcedureId(recommendation, included)
      return this.procedureId !== recommendationProcedureId
    },

    toggleInsert (recommendation) {
      this.$emit('insert-recommendation', recommendation)
      this.toggleModal()
    },

    toggleModal (open) {
      if (open === 'open') {
        this.isLoading = true
        this.fetchSimilarRecommendations()
          .then(({ data }) => {
            this.similarRecommendations = data.data.map(recommendation => {
              return {
                ...recommendation,
                fromOtherProcedure: this.isRecommendationFromOtherProcedure(recommendation, data.included),
                procedureName: this.getRecommendationProcedureName(recommendation, data.included)
              }
            })

            /*
             * Recommendations from the current procedure are more relevant to the user than recommendations from another procedure.
             * Therefore, we put all recommendations from the current procedure on top.
             */
            this.similarRecommendations.sort((a, b) => {
              if (a.fromOtherProcedure && b.fromOtherProcedure === false) {
                return 1
              }
              if (a.fromOtherProcedure === false && b.fromOtherProcedure) {
                return -1
              }

              return 0
            })

            const uniqueRecommendationsText = []
            const uniqueRecommendations = []

            this.similarRecommendations.forEach((obj) => {
              if (uniqueRecommendationsText.includes(obj.attributes.recommendation) === false) {
                uniqueRecommendations.push(obj)
                uniqueRecommendationsText.push(obj.attributes.recommendation)
              }
            })

            this.similarRecommendations = uniqueRecommendations

            this.isLoading = false
          })
      }
      this.$refs.recommendationModal.toggle()
    },

    updateSegment (recommendation) {
      const payload = JSON.parse(JSON.stringify(this.segment))
      payload.attributes.recommendation = recommendation
      this.setItem({ ...payload, id: payload.id, group: null })
    },

    fetchSimilarRecommendations () {
      const url = Routing.generate('api_resource_list', { resourceType: 'StatementSegment' })
      const params = {
        include: 'parentStatement,parentStatement.procedure',
        fields: {
          StatementSegment: [
            'id',
            'recommendation',
            'text',
            'externId',
            'internId',
            'orderInProcedure',
            'parentStatement'
          ].join(),
          Statement: [
            'procedure'
          ].join()
        },
        filter: {
          tags: {
            condition: {
              path: 'tags.title',
              value: this.tagTitles,
              operator: 'IN'
            }
          },
          notEmpty: {
            condition: {
              path: 'recommendation',
              value: '',
              operator: '<>'
            }
          },
          notSelf: {
            condition: {
              path: 'id',
              value: this.segmentId,
              operator: '<>'
            }
          },
          anyOfProcedures: {
            condition: {
              path: 'parentStatement.procedure.id',
              value: this.recommendationProcedureIds,
              memberOf: 'IN'
            }
          }
        }
      }
      return dpApi.get(url, params, { serialize: true })
    },

    setSearchTerm (term) {
      this.searchTerm = term
    }
  }
}
</script>
