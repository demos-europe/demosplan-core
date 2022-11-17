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
        <InstitutionList></InstitutionList>
      </slot>
    </dp-tab>
    <dp-tab
      id="tagList"
      :label="Translator.trans('tag.administrate')">
      <slot>
        <TagList v-on:tagIsRemoved="institutionListReset"></TagList>
      </slot>
    </dp-tab>
  </dp-tabs>
</template>

<script>
import DpTab from '@DpJs/components/core/DpTabs/DpTab'
import DpTabs from '@DpJs/components/core/DpTabs/DpTabs'
import TagList from "./TagList";
import InstitutionList from "./InstitutionList";
import {mapActions} from "vuex";

export default {
  name: "InstitutionTagManagement",

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
    ...mapActions('invitableInstitution', {
      listInvitableInstitution: 'list',
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



