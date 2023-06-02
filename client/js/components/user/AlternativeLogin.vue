<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <slot
      :header-fields="headerFields"
      :login-route="loginRoute"
      :password="password"
      :row-items="rowItems" />
  </div>
</template>

<script>
export default {
  name: 'AlternativeLogin',

  props: {
    loginRoute: {
      required: true,
      type: String
    },

    password: {
      required: true,
      type: String
    },

    users: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      headerFields: [
        { field: 'nameMail', label: 'Name (E-Mail)' },
        { field: 'orgaDep', label: 'Orga / Abteilung' },
        { field: 'roles', label: 'Rollen' },
        { field: 'button', label: 'Login' }
      ]
    }
  },

  computed: {
    rowItems () {
      return this.users.map((el, idx) => {
        el.orgaDep = `${el.orga}, ${el.department}`
        el.nameMail = `${el.name} (${el.email})`
        el.index = idx
        return el
      })
    }
  }
}
</script>
