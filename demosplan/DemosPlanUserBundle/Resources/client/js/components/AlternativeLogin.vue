<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <master-portal />
    <div id="masterportal-root"></div>
    <slot
      :header-fields="headerFields"
      :login-route="loginRoute"
      :password="password"
      :row-items="rowItems" />
  </div>
</template>

<script src="https://geodienste.hamburg.de/lgv-config/special_loaders.js"></script>
<script src="https://geoportal-hamburg.de/mastercode/cesium/1_95/Cesium.js"></script>
<script>
/* eslint-ignore nextline */
import Backbone from 'backbone'
/* eslint-ignore nextline */
import Radio from 'backbone.radio'
import MasterPortal from '../lib/master-portal-umd'
import Config from '../lib/config'

window.Config = Config

export default {
  name: 'AlternativeLogin',

  components: {
    MasterPortal
  },

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
  },

  created () {
    console.log('on created', this.$options.computed)
    // MasterPortal.install(this.$options.parent)
  }
}
</script>
