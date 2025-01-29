<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This component contains changes to a statement at a specific time, it is the child of DpVersionHistoryDay.vue -->
</documentation>

<template>
  <tr
    v-if="Object.entries(time).length"
    class="border--top"
    data-cy="versionHistoryItem">
    <td colspan="4">
      <table>
        <thead>
          <tr class="sr-only">
            <th>{{ Translator.trans('time') }}</th>
            <th v-if="time.userName !== null">
              {{ Translator.trans('user') }}
            </th>
            <th v-else>
              -
            </th>
            <th>{{ Translator.trans('fields') }}</th>
            <th>{{ Translator.trans('aria.toggle') }}</th>
          </tr>
        </thead>

        <tbody>
          <tr
            @click="getContent"
            class="o-sortablelist__item cursor-pointer">
            <!-- time -->
            <td
              class="line-height--1_6 u-pr u-pv-0_5 u-pl-0_5"
              style="width: 15%;"
              data-cy="historyTime">
              {{ timeCreated }} {{ Translator.trans('clock') }}
            </td>

            <!-- user name -->
            <td
              v-if="time.userName !== null"
              class="line-height--1_6 u-pr u-pv-0_5 u-pl-0_5"
              style="width: 40%;"
              data-cy="historyUserName">
              {{ time.userName }}
            </td>
            <td
              v-else
              class="line-height--1_6 u-pr u-pv-0_5 u-pl-0_5"
              style="width: 40%;">
              -
            </td>

            <!-- fields -->
            <td
              class="line-height--1_6 u-pv-0_5"
              style="width: 40%;">
              <ul class="o-list o-list--csv">
                <li
                  v-for="field in time.fieldNames"
                  class="o-list__item"
                  :key="field"
                  data-cy="historyField">
                  {{ Translator.trans(field) }}
                </li>
              </ul>
            </td>

            <td
              class="line-height--1_6 u-pr u-pv-0_5 u-pl-0_5 text-right cursor-pointer"
              style="width: 5%">
              <i
                class="btn-icns fa cursor-pointer"
                :class="{'fa-angle-down': !isOpen, 'fa-angle-up': isOpen}"
                data-cy="toggleIcon" />
            </td>
          </tr>

          <tr>
            <td colspan="4">
              <table>
                <thead>
                  <tr class="sr-only">
                    <th
                      colspan="4"
                      v-if="isOpen && isLoading">
                      {{ Translator.trans('loading') }}
                    </th>
                    <th
                      colspan="4"
                      v-if="isOpen && !isLoading">
                      {{ Translator.trans('dropdown.open') }}
                    </th>
                  </tr>
                </thead>

                <tbody>
                  <tr>
                    <td
                      v-if="isOpen && isLoading"
                      class="u-ml u-mb u-mt inline-block"
                      colspan="4">
                      <dp-loading />
                    </td>

                    <td
                      colspan="4"
                      v-if="isOpen && !isLoading">
                      <table v-if="time.displayChange">
                        <thead>
                          <tr class="sr-only">
                            <th>{{ Translator.trans('field') }}</th>
                            <th>{{ Translator.trans('change') }}</th>
                          </tr>
                        </thead>

                        <tbody>
                          <tr
                            v-for="(content, fieldName) in history.attributes"
                            :key="fieldName + 'content'"
                            class="u-pb-0_25"
                            data-cy="historyItemElement">
                            <td
                              :id="'fieldName' + time.anyEntityContentChangeIdOfThisChangeInstance"
                              class="u-pt-0_5 u-pl-0_5 u-mr u-pr align-top u-1-of-6 inline-block"
                              data-cy="fieldName">
                              <strong>
                                {{ Translator.trans(fieldName) }}
                              </strong>
                            </td>
                            <td
                              v-if="content !== null && content !== ''"
                              style="width: 79%;"
                              class="u-pt-0_5 u-pb-0_5 u-ml-0_5 break-words inline-block"
                              data-cy="contentChange"
                              v-cleanhtml="content" />
                            <td
                              v-else-if="content === null"
                              style="width: 82%;"
                              class="u-pt-0_5 u-pb-0_5 u-ml-0_5 color--grey inline-block">
                              {{ Translator.trans('formatting.change') }}
                            </td>
                          </tr>
                        </tbody>
                      </table>

                      <!-- if data is too old, don't show details since the data is incorrect -->
                      <table v-else>
                        <thead>
                          <tr class="sr-only">
                            <th>
                              {{ Translator.trans('details') }}
                            </th>
                          </tr>
                        </thead>

                        <tbody>
                          <tr class="u-pb-0_25">
                            <td class="u-pt-0_5 u-ml-0_5 u-pb-0_5 color--grey inline-block">
                              {{ Translator.trans('details.none') }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                </tbody>
              </table>
            </td>
          </tr>
        </tbody>
      </table>
    </td>
  </tr>
</template>

<script>
import { checkResponse, CleanHtml, dpApi, DpLoading, formatDate } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpVersionHistoryItem',

  components: {
    DpLoading
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    entity: {
      type: String,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    },

    time: {
      type: Object,
      required: false,
      default: () => ({})
    }
  },

  data () {
    return {
      history: null,
      isLoading: true,
      isOpen: false
    }
  },

  computed: {
    timeCreated () {
      return formatDate(this.time.created, 'HH:mm:ss')
    }
  },

  methods: {
    getContent () {
      this.isOpen = !this.isOpen
      /*
       * We need to reload history every time to display correct data
       * if data is older than a certain date, it is incorrect and we don't want to load it (displayChange === false)
       */
      if (this.time.displayChange) {
        this.loadHistory()
          .then((response) => {
            this.history = response.data
            this.isLoading = false
          })
      } else {
        this.isLoading = false
      }
    },

    loadHistory () {
      return dpApi({
        method: 'GET',
        url: Routing.generate('dplan_api_history_of_all_fields_of_specific_datetime', {
          entityContentChangeId: this.time.anyEntityContentChangeIdOfThisChangeInstance,
          procedureId: this.procedureId
        })
      })
        .then(response => checkResponse(response))
        .then(response => response)
        .catch(error => checkResponse(error.response))
    }
  }
}
</script>
