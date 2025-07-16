<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <ul class="o-list space-x-1">
    <li
      class="inline-block border bg-color--white color--grey-dark rounded-md px-1 py-0.5"
      :key="tag.id"
      v-for="tag in selectedTags">
      {{ tag.title }}
    </li>
  </ul>
</template>

<script>
export default {
  name: 'SelectedTagsList',

  props: {
    selectedTags: {
      type: Array,
      required: true
    }
  }
}
</script>
