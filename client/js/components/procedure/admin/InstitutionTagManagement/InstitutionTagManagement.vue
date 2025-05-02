<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-tabs
    tab-size="medium"
    :active-id="activeTabId"
    use-url-fragment
    @change="setActiveTabId">
    <slot>
      <dp-tab
        id="institutionList"
        :is-active="activeTabId === 'institutionList'"
        :label="Translator.trans('invitable_institution.group')">
        <slot>
          <institution-list :is-active="isInstitutionListActive" />
        </slot>
      </dp-tab>
      <dp-tab
        id="tagList"
        :is-active="activeTabId === 'tagList'"
        :label="Translator.trans('tag.administrate')">
        <slot>
          <tag-list @tagIsRemoved="institutionListReset" />
        </slot>
      </dp-tab>
    </slot>
  </dp-tabs>
</template>

<script>
import {
  DpTab,
  DpTabs
} from '@demos-europe/demosplan-ui'
import InstitutionList from './InstitutionList'
import { mapActions } from 'vuex'
import TagList from './TagList'

export default {
  name: 'InstitutionTagManagement',

  components: {
    DpTabs,
    DpTab,
    TagList,
    InstitutionList
  },

  data () {
    return {
      activeTabId: 'institutionList',
      needToReset: false
    }
  },

  computed: {
    isInstitutionListActive () {
      return this.activeTabId === 'institutionList'
    }
  },

  watch: {
    needToReset: {
      handler (newValue) {
        if (newValue) {
          this.getInstitutionsByPage(1)
        }
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    }
  },

  methods: {
    ...mapActions('InvitableInstitution', {
      listInvitableInstitution: 'list'
    }),

    getInstitutionsByPage (page) {
      this.listInvitableInstitution({
        page: {
          number: page,
          size: 50
        },
        sort: '-createdDate',
        fields: {
          InstitutionTag: ['label', 'id'].join()
        }
      })
        .then(() => {
          this.needToReset = false
        })
    },

    setActiveTabId (id) {
      if (id) {
        window.localStorage.setItem('tagManagementActiveTabId', id)
      }

      if (window.localStorage.getItem('tagManagementActiveTabId')) {
        this.activeTabId = window.localStorage.getItem('tagManagementActiveTabId')
      }
    },

    institutionListReset () {
      this.needToReset = true
    }
  }
}
</script>
