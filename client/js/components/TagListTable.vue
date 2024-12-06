<template>
  <table>
    <col class="w-6">
    <col class="">
    <col class="w-9">
    <col class="w-9">

    <thead>
    <tr class="border-b">
      <th class="checkbox text-left">
        <i class="fa fa-check-square-o"></i>
      </th>
      <th class="text-left">
        {{ Translator.trans('topic.or.tag') }}
      </th>
      <th class="text-left -mr-2">
        {{ Translator.trans('boilerplate') }}
      </th>
      <th class="text-right">
        {{ Translator.trans('actions') }}
      </th>
    </tr>
    </thead>

    <tbody>
    <template v-for="topic in topics">
      <tr class="border-b">
        <td class="checkbox u-ph-0 u-pb-0_25 u-pt-0_5 text-left">
          <input type="hidden" :name="`${topic.id}:r_itemtype`" :value="topic">

          <label class="u-m-0">
            <input
              type="checkbox"
              :name="`${topic.id}:r_selected`"
              data-cy="listTags:selectTopic"
              data-checkable-item>
          </label>
        </td>
        <td class="weight--bold">
          {{topic.name }}
        </td>
        <td />
        <td class="text-right">
          <a
            href="#"
            data-cy="moveTag"
            class="o-toggle__trigger u-mr-0_5 js__toggleAnything"
            data-toggle-container="form"
            :data-toggle="`#move-tag-${topic.id}`"
            data-toggle-prevent-default
            :title="Translator.trans('tag.move')">
            <i class="fa fa-angle-double-right" aria-hidden="true"></i>
          </a>

          <a
            href="#"
            data-cy="renameTagField"
            class="o-toggle__trigger u-mr-0_5 js__toggleAnything"
            data-toggle-container="form"
            :data-toggle="`#rename-tag-${topic.id}`"
            data-toggle-prevent-default
            :title="Translator.trans('tag.rename')">
            <i class="fa fa-pencil" aria-hidden="true" />
          </a>

          <button
            class="btn--blank o-link--default align-top"
            data-cy="deleteTagField"
            name="r_deletetag"
            :value="topic.id"
            data-form-actions-confirm-simple
            :title="Translator.trans('tag.delete')">
            <i class="fa fa-trash"/>
          </button>
        </td>
      </tr>
      <template v-for="tag in topic.tags">
        <tr class="border-b">
          <td class="checkbox u-ph-0 u-pb-0_25 u-pt-0_5 text-left">
            <input type="hidden" :name="`${tag.id}:r_itemtype`" :value="topic">

            <label class="u-m-0">
              <input
                type="checkbox"
                :name="`${tag.id}:r_selected`"
                data-cy="listTags:selectTopic"
                data-checkable-item>
            </label>
          </td>
          <td><a class="u-1-of-1 block o-hellip">{{ tag.title }}</a></td>
          <td class="text-center relative">
            <div class="relative">
              <dp-contextual-help
                v-if="tag.boilerplate"
                class="color--grey block"
                icon="file"
                :text="tag.boilerplate">
              </dp-contextual-help>
            </div>
          </td>

          <td class="text-right">
            <tags-list-controls :tag="tag" />
          </td>
        </tr>
      </template>
    </template>
    </tbody>
  </table>
</template>

<script>
import { DpContextualHelp } from '@demos-europe/demosplan-ui'
import TagsListControls from './TagsListControls.vue'
export default {
  name: 'TagListTable',

  components: {
    DpContextualHelp,
    TagsListControls
  },

  inject: ['topics']
}
</script>
