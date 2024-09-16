<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="c-publicindex relative overflow-hidden">
    <dp-drawer />
    <dp-map
      :map-data="mapData"
      :initial-map-settings="initialMapSettings"
      :is-public-agency="isPublicAgency"
      :is-public-user="isPublicUser"
      :projection-name="projectionName"
      :projection-string="projectionString"
      :project-version="projectVersion" />
  </div>
</template>

<script>
import DpDrawer from './drawer/Drawer'
import { mapActions } from 'vuex'

export default {
  name: 'DpProcedures',

  components: {
    DpMap: () => import('./map/Map'),
    DpDrawer
  },

  props: {
    initialMapSettings: {
      type: Object,
      required: false,
      default: () => ({})
    },

    isPublicAgency: {
      type: Boolean,
      required: false,
      default: false
    },

    isPublicUser: {
      type: Boolean,
      required: false,
      default: false
    },

    mapData: {
      type: Object,
      required: true
    },

    projectionName: {
      type: String,
      required: false,
      default: ''
    },

    projectionString: {
      type: String,
      required: false,
      default: ''
    },

    projectVersion: {
      required: false,
      type: String,
      default: ''
    }
  },

  methods: {
    ...mapActions('Procedure', {
      getProcedures: 'get'
    })
  },

  created () {
    this.getProcedures()
  }
}
</script>
