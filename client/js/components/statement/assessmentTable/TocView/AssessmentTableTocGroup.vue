<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <ul
    class="bg-color--grey-light-2"
    :class="depth === 0 ? 'c-toc c-toc--level-0 h-full overflow-x-auto u-ph u-pv-0_25' : 'c-toc--level-1'">
    <li
      v-for="(subgroup, idx) in group.subgroups"
      :key="`subGroup:${idx}`">
      <div :class="{ 'u-pl-0_25': depth === 1, 'u-pl': depth === 2 }">
        <a
          class="o-link--default"
          :href="`#viewMode_${getElementId(subgroup.title)}`">
          {{ subgroup.title }}
        </a>
        <span class="u-pl-0_25">
          ({{ subgroup.total }})
        </span>
      </div>

      <assessment-table-toc-group
        v-if="subgroup.subgroups.length"
        :group="subgroup"
        :parent-id="getElementId(subgroup.title)" />
    </li>
  </ul>
</template>

<script>
import tocViewGroupMixin from './mixins/tocViewGroupMixin'

export default {
  name: 'AssessmentTableTocGroup',

  mixins: [tocViewGroupMixin],

  methods: {
    getElementId (title) {
      return this.parentId === '' ? title : `${this.parentId}_${title}`
    }
  }
}
</script>
