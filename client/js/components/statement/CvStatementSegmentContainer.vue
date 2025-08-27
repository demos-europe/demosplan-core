<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="cv-statement-segment-container">
    <div class="cv-container">
      <div class="cv-header-row">
        <h4 class="cv-main-title">
          Stellungnahmen und Abschnitte zum aktuellen Verfahren
        </h4>
        <span class="cv-switch-label">Darstellung</span>

        <!-- Content Switcher -->
        <cv-content-switcher @selected="onTabSwitch">
          <cv-content-switcher-button
            content-selector=".statements-content"
            :selected="activeTab === 'statements'">
            Stellungnahmen
          </cv-content-switcher-button>
          <cv-content-switcher-button
            content-selector=".sections-content"
            :selected="activeTab === 'sections'">
            Abschnitte
          </cv-content-switcher-button>
        </cv-content-switcher>
      </div>
    </div>

    <!-- Conditional Component Rendering -->
    <cv-statement-list 
      v-if="activeTab === 'statements'"
      :current-user-id="currentUserId"
      :procedure-id="procedureId"
      :local-storage-key="localStorageKey"
      :use-local-storage="useLocalStorage" />
      
    <cv-segment-list
      v-if="activeTab === 'sections'" 
      :current-user-id="currentUserId"
      :procedure-id="procedureId"
      :local-storage-key="localStorageKey + '_segments'"
      :use-local-storage="useLocalStorage" />
  </div>
</template>

<script>
import {
  CvContentSwitcher,
  CvContentSwitcherButton
} from '@carbon/vue'
import CvStatementList from './CvStatementList'
import CvSegmentList from './segments/CvSegmentList'

export default {
  name: 'CvStatementSegmentContainer',

  components: {
    CvContentSwitcher,
    CvContentSwitcherButton,
    CvStatementList,
    CvSegmentList
  },

  props: {
    currentUserId: {
      type: String,
      required: true
    },

    procedureId: {
      required: true,
      type: String
    },

    localStorageKey: {
      type: String,
      default: 'statementList'
    },

    useLocalStorage: {
      type: Boolean,
      default: true
    }
  },

  data () {
    return {
      activeTab: 'statements'
    }
  },

  methods: {
    onTabSwitch (selectedButton) {
      if (selectedButton.includes('statements')) {
        this.activeTab = 'statements'
      } else if (selectedButton.includes('sections')) {
        this.activeTab = 'sections'
      }
    }
  }
}
</script>

