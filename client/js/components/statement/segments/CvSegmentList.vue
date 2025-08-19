<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="cv-segment-list">
    <div class="cv-container">
      <!-- Basic table structure -->
      <cv-data-table
        :columns="columns"
        :data="segments"
        id="cv-segment-table"
      >
        <template v-slot:data>
          <template v-for="(segment, index) in segments" :key="index">
            <cv-data-table-row :value="String(segment.id)">
              <cv-data-table-cell>
                {{ segment.externId }}
              </cv-data-table-cell>
            </cv-data-table-row>
          </template>
        </template>
      </cv-data-table>
    </div>
  </div>
</template>

<script>
import {
  CvDataTable,
  CvDataTableCell,
  CvDataTableRow
} from '@carbon/vue'

export default {
  name: 'CvSegmentList',

  components: {
    CvDataTable,
    CvDataTableRow,
    CvDataTableCell
  },

  props: {
    currentUserId: {
      type: String,
      required: true
    },
    procedureId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      columns: [
        { key: 'externId', label: 'ID' }
      ]
    }
  },

  computed: {
    segments () {
      // Dummy data for now
      return [
        { id: 1, externId: 'S-001' },
        { id: 2, externId: 'S-002' }
      ]
    }
  }
}
</script>