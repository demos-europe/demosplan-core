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
    <dp-tab
      id="institutionList"
      :label="Translator.trans('invitable_institution.group')">
      <slot>
        <InstitutionList />
      </slot>
    </dp-tab>
    <dp-tab
      id="tagList"
      :label="Translator.trans('tag.administrate')">
      <slot>
        <TagList @tagIsRemoved="institutionListReset" />
      </slot>
    </dp-tab>
  </dp-tabs>
</template>

<script>
import { DpTab, DpTabs } from '@demos-europe/demosplan-ui'
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

  watch: {
    needToReset (newValue) {
      if (newValue === true) {
        this.getInstitutionsByPage(1)
      }
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
