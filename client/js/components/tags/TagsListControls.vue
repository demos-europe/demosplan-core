<template>
  <div>
    <a
      v-if="tagType !== 'topic'"
      href="#"
      data-cy="moveTag"
      class="o-toggle__trigger u-mr-0_5 js__toggleAnything"
      data-toggle-container="form"
      :data-toggle="`#move-tag-${tag.id}`"
      data-toggle-prevent-default
      :title="Translator.trans('tag.move')"
      @click="$emit('toggleInputs', {type: 'tag', action: 'move'})">
      <i class="fa fa-angle-double-right" aria-hidden="true"></i>
    </a>
    <a
      v-else
      href="#"
      data-cy="addNewTag"
      class="o-toggle__trigger u-mr-0_5 js__toggleAnything"
      data-toggle-container="form"
      :data-toggle="`#insert-${tag.id}`"
      data-toggle-prevent-default
      :title="Translator.trans('topic.insertTag')"
      @click="$emit('toggleInputs', {type: 'topic', action: 'insert'})">
    <i class="fa fa-plus" aria-hidden="true"></i>
    </a>
    <a
      href="#"
      data-cy="renameTagField"
      class="o-toggle__trigger u-mr-0_5 js__toggleAnything"
      data-toggle-container="form"
      :data-toggle="`#rename-tag-${tag.id}`"
      data-toggle-prevent-default
      :title="Translator.trans('tag.rename')"
      @click="$emit('toggleInputs', {type: 'any', action: 'rename'})">
      <i class="fa fa-pencil" aria-hidden="true" />
    </a>

    <button
      v-if="tagType === 'tag'"
      class="btn--blank o-link--default align-top"
      data-cy="deleteTagField"
      name="r_deletetag"
      :value="tag.id"
      :data-form-actions-confirm="Translator.trans('check.tag.delete', { tag: tag.name })"
      data-form-actions-confirm-simple
      :title="Translator.trans('tag.delete')">
      <i class="fa fa-trash"/>
    </button>

    <button
      v-else
      class="btn--blank o-link--default align-top"
      data-cy="deleteTagField"
      name="r_deletetopic"
      :value="tag.id"
      :data-form-actions-confirm="Translator.trans('check.tag.delete', { tag: tag.title })"
      data-form-actions-confirm-simple
      :title="Translator.trans('topic.delete')">
      <i class="fa fa-trash"/>
    </button>
  </div>
</template>

<script>
export default {
  name: "TagListControls",

  props: {
    tag: {
      type: Object,
      required: true
    },

    tagType: {
      type: String,
      required: false,
      default: 'topic',
      validator: (value) => {
        return ['topic', 'tag'].indexOf(value) !== -1
      }
    }
  }
}
</script>
