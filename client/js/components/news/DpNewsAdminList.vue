<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    <div class="text-right u-pv-0_5">
      <a
        class="btn btn--primary"
        :href="pathForNewsCreation"
        data-cy="newNews">
        {{ Translator.trans('news.new') }}
      </a>
    </div>
    <dp-bulk-edit-header
      class="layout__item u-12-of-12"
      v-if="selectedItems.length > 0"
      :selected-items-text="Translator.trans('news.notes.selected', { count: selectedItems.length })"
      @reset-selection="resetSelection">
      <button
        class="btn-icns u-m-0"
        name="newsdelete"
        data-cy="deleteSelectedNews"
        @click.prevent="deleteEntries"
        type="button">
        <i
          aria-hidden="true"
          class="fa fa-times u-mr-0_125" />
        {{ Translator.trans('delete') }}
      </button>
    </dp-bulk-edit-header>
    <dp-data-table
      :header-fields="headerFields"
      :items="list"
      track-by="id"
      is-draggable
      is-selectable
      :should-be-selected-items="shouldBeSelected"
      @changed-order="changeManualsort"
      @items-selected="setShouldBeSelected">
      <template v-slot:title="{ id, pId, title }">
        <div class="o-hellip__wrapper">
          <a
            class="o-hellip block"
            data-cy="newsTitleLink"
            :href="generateEditPath(id, pId)">
            {{ title }}
          </a>
        </div>
      </template>
      <template v-slot:enabled="rowData">
        <dp-news-item-status
          class="flex space-inline-xs u-mt-0_125 items-center"
          :switch-date="rowData.designatedSwitchDate || ''"
          :switch-state="rowData.designatedState ? 'released' : 'blocked'"
          :news-status="rowData.enabled"
          :determined-to-switch="rowData.determinedToSwitch || false"
          @statusChanged="setItemStatus($event, rowData.id)" />
      </template>
      <template v-slot:picture="{ picture }">
        <i
          v-if="picture !== ''"
          class="fa fa-check"
          aria-hidden="true" />
      </template>
    </dp-data-table>
  </div>
</template>

<script>
import { checkResponse, dpApi, DpBulkEditHeader, DpDataTable, makeFormPost } from '@demos-europe/demosplan-ui'
import DpNewsItemStatus from './DpNewsItemStatus'

export default {
  name: 'DpNewsAdminList',

  components: {
    DpBulkEditHeader,
    DpNewsItemStatus,
    DpDataTable
  },

  props: {
    initList: {
      required: true,
      type: Array
    },

    procedureId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      list: this.initList,
      headerFields: [
        { field: 'title', label: 'Überschrift' },
        { field: 'enabled', label: 'Status' },
        { field: 'picture', label: 'Bild' }
      ],
      selectedItems: []
    }
  },

  computed: {
    pathForNewsCreation () {
      return this.procedureId !== ''
        ? Routing.generate('DemosPlan_news_administration_news_new_get', { procedure: this.procedureId })
        : Routing.generate('DemosPlan_globalnews_administration_news_new_get')
    },

    shouldBeSelected () {
      return !this.selectedItems
        ? {}
        : this.selectedItems.reduce((acc, el) => {
          return {
            ...acc,
            [el]: true
          }
        }, {})
    },

    updateRoute () {
      return (this.procedureId !== '')
        ? Routing.generate('DemosPlan_news_administration_news', { procedure: this.procedureId })
        : Routing.generate('DemosPlan_globalnews_administration_news')
    }
  },

  methods: {
    changeManualsort (event) {
      const listBackup = [...this.list]
      const removedItem = this.list.splice(event.oldIndex, 1)[0]
      this.list.splice(event.newIndex, 0, removedItem)

      this.updateList()
        .then(() => {
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        })
        .catch(error => {
          // Reset optimistically triggered sort on error
          this.list = listBackup
          console.error(error)
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
    },

    deleteEntries () {
      if (window.dpconfirm(Translator.trans('check.entries.marked.delete'))) {
        const currentType = this.procedureId !== '' ? 'ProcedureNews' : 'GlobalNews'

        const newsToDelete = this.selectedItems

        newsToDelete.forEach(id => {
          return dpApi.delete(Routing.generate('api_resource_delete', { resourceType: currentType, resourceId: id }))
            .then(() => {
              this.list = this.list.filter(listItem => !newsToDelete.includes(listItem.id))
              this.selectedItems = []
              dplan.notify.notify('confirm', Translator.trans('confirm.entries.marked.deleted'))
            })
            .catch(e => {
              console.error('deleting of entries failed', e)
            })
        })
      }
    },

    generateEditPath (id, pId) {
      return this.procedureId !== ''
        ? Routing.generate('DemosPlan_news_administration_news_edit_get', { newsID: id, procedure: pId })
        : Routing.generate('DemosPlan_globalnews_administration_news_edit_get', { newsID: id })
    },

    setItemStatus (value, id) {
      this.list = this.list.map(item => {
        if (item.id === id) {
          item.enabled = value
        }
        return item
      })

      this.updateList()
        .then(checkResponse)
        .then(() => {
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        })
        .catch(() => {
          // Reset optimistically triggered toggle on error
          this.list = this.list.map(item => {
            if (item.id === id) {
              item.enabled = !value
            }

            return item
          })

          dplan.notify.error(Translator.trans('error.api.generic'))
        })
    },

    updateList () {
      const payload = {
        manualsort: this.list.map(el => el.ident).toString(', '),
        r_enable: this.list.filter(el => el.enabled).map(el => el.ident)
      }

      return makeFormPost(payload, this.updateRoute)
    },

    resetSelection () {
      this.selectedItems = []
    },

    setShouldBeSelected (items) {
      this.selectedItems = items
    }
  }
}
</script>
