<template>
  <dp-tabs
    :active-id="activeTabId"
    use-url-fragment
    @change="setActiveTabId">
    <dp-tab
      id="institutionList"
      label="Institutionen gruppieren">
      <slot>
        <InstitutionList></InstitutionList>
      </slot>
    </dp-tab>
    <dp-tab
      id="tagList"
      label="Schlagworte verwalten">
      <slot>
        <TagList></TagList>
      </slot>
    </dp-tab>

  </dp-tabs>
</template>

<script>
import DpTab from '@DpJs/components/core/DpTabs/DpTab'
import DpTabs from '@DpJs/components/core/DpTabs/DpTabs'
import TagList from "./TagList";
import InstitutionList from "./InstitutionList";

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
      activeTabId: 'institutionList'
    }
  },

  methods: {
    setActiveTabId (id) {
      if (id) {
        window.localStorage.setItem('tagManagementActiveTabId', id)
      }

      if (window.localStorage.getItem('tagManagementActiveTabId')) {
        this.activeTabId = window.localStorage.getItem('tagManagementActiveTabId')
      }
    },
  }
}
</script>



