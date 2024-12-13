<template>
  <div v-if="topics">
    <tag-list-header />
    <tag-list-form>
      <template #tag-list-content-Headline>
        <h2 class="my-4 underline text-2xl font-bold">Inhaltliche Schlagworte</h2>
      </template>
      <template #tag-list-bulk-controls>
        <tag-list-bulk-controls />
      </template>
      <template #tag-list-table>
        <tag-list-table />
      </template>
    </tag-list-form>
  </div>
</template>

<script>
import { DpUpload } from '@demos-europe/demosplan-ui'
import TagListBulkControls from './TagListBulkControls'
import TagListForm from './TagListForm'
import TagListHeader from './TagListHeader'
import TagListTable from './TagListTable'

export default {
  name: 'TagsList',

  components: {
    DpUpload,
    TagListBulkControls,
    TagListForm,
    TagListHeader,
    TagListTable
  },

  props: {
    procedureId: {
      type: String,
      required: true
    },

    topics: {
      type: Array,
      required: true
    }
  },

  provide() {
    return {
      topics: this.topics,
      procedureId: this.procedureId
    }
  }
}
</script>
