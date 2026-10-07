<template>
  <div
    v-show="isVisible"
  >
    <p>HELLO</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useStore } from 'vuex'

const store = useStore()
const slidebar = computed(() => store.state.SegmentSlidebar.slidebar)

const isVisible = computed(() => slidebar.value.showTab === 'favoriteView')

</script>


