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

    initSendMailsToCounties: {
      required: false,
      type: Boolean,
      default: false,
    },
  },

  data () {
    return {
      state: {
        selectedAgencies: this.agencies.filter(agency => this.initSelectedAgencies.includes(agency.id)),
        selectedAuthUsers: this.authUsers.filter(user => this.initSelectedAuthUsers.includes(user.id)),
        sendMailsToCounties: this.initSendMailsToCounties,
      },
    }
  },

  methods: {
    selectAllAuthUsers () {
      this.state.selectedAuthUsers = this.authUsers
    },

    sortSelected (type) {
      const area = `selected${type}`

      this.state[area].sort((a, b) => {
        if (a.name > b.name) return 1
        if (b.name > a.name) return -1

        return 0
      })
    },

    unselectAllAuthUsers () {
      this.state.selectedAuthUsers = []
    },
  },
}
</script>
