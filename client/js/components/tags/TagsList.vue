<template>
  <div>
    <tags-list-header />

    <tags-create-form
      :is-master-procedure="isMasterProcedure"
      :procedure-id="procedureId"
    />

    <dp-tree-list
      v-if="transformedCategories"
      class="mb-4"
      align-toggle="center"
      :tree-data="transformedCategories"
      :options="{
        branchesSelectable: false,
        dragAcrossBranches: true,
        dragLeaves: true,
        leavesSelectable: false,
        selectOn: {
          parentSelect: true,
          childDeselect: true
        }
      }"
      :branch-identifier="isBranch"
      @end="handleTagReorder"
    >
      <template v-slot:header>
        <div class="flex">
          <div class="ml-4 flex-1">
            {{ Translator.trans('topic.or.tag') }}
          </div>

          <addon-wrapper hook-name="tag.extend.form" />

          <div class="ml-1 flex-0 w-9">
            {{ Translator.trans('boilerplates') }}
          </div>

          <div class="ml-1 flex-0 w-8 text-right">
            {{ Translator.trans('actions') }}
          </div>
        </div>
      </template>
      <template v-slot:branch="{ nodeElement }">
        <tag-list-edit-form
          class="font-bold"
          :node-element="nodeElement"
          :is-in-edit-state="isInEditState"
          :procedure-id="procedureId"
          type="TagTopic"
          @abort="closeEditForm"
          @delete="deleteItem"
          @edit="setEditState"
          @save="save"
        />
      </template>
      <template v-slot:leaf="{ nodeElement }">
        <tag-list-edit-form
          :node-element="nodeElement"
          :is-in-edit-state="isInEditState"
          :procedure-id="procedureId"
          type="Tag"
          @abort="closeEditForm"
          @delete="deleteItem"
          @edit="setEditState"
          @save="save"
        />
      </template>
    </dp-tree-list>

    <dp-loading v-else />

    <tags-import-form
      class="mb-1"
      :procedure-id="procedureId"
    />
  </div>
</template>

<script>
import { DpLoading, DpTreeList } from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import TagListEditForm from './TagListEditForm'
import TagsCreateForm from './TagsCreateForm'
import TagsImportForm from './TagsImportForm'
import TagsListHeader from './TagsListHeader'
import { useTagsList } from '@DpJs/composables/useTagsList'

export default {
  name: 'TagsList',

  components: {
    AddonWrapper,
    DpLoading,
    DpTreeList,
    TagsCreateForm,
    TagsImportForm,
    TagListEditForm,
    TagsListHeader,
  },

  props: {
    isMasterProcedure: {
      type: Boolean,
      required: false,
      default: false,
    },

    procedureId: {
      type: String,
      required: true,
    },
  },

  setup () {
    return useTagsList()
  },
}
</script>
