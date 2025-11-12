<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <slot
      :state="state"
      :auth-users="authUsers"
      :agencies="agencies"
      :select-all-auth-users="selectAllAuthUsers"
      :unselect-all-auth-users="unselectAllAuthUsers"
      :sort-selected="sortSelected"
    />
  </div>
</template>

<script>
export default {
  name: 'ProcedureTemplateBasicSettings',

  props: {
    authUsers: {
      required: false,
      type: Array,
      default: () => [],
    },

    initSelectedAuthUsers: {
      required: false,
      type: Array,
      default: () => [],
    },

    agencies: {
      required: false,
      type: Array,
      default: () => [],
    },

    initSelectedAgencies: {
      required: false,
      type: Array,
      default: () => [],
    },
  },

  data () {
    return {
      state: {
        selectedAgencies: this.agencies.filter(agency => this.initSelectedAgencies.includes(agency.id)),
        selectedAuthUsers: this.authUsers.filter(user => this.initSelectedAuthUsers.includes(user.id)),
      }
    }
  },

  methods: {
    selectAllAuthUsers () {
      this.state.selectedAuthUsers = this.authUsers
    },

    sortSelected (type) {
      const area = `selected${type}`
      this.state[area].sort((a, b) => (a.name > b.name) ? 1 : ((b.name > a.name) ? -1 : 0))
    },

    unselectAllAuthUsers () {
      this.state.selectedAuthUsers = []
    },
  },
}
</script>
