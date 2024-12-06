<template>
  <div>
    <a
      href="#"
      data-cy="moveTag"
      class="o-toggle__trigger u-mr-0_5 js__toggleAnything"
      data-toggle-container="form"
      :data-toggle="`#move-tag-${tag.id}`"
      data-toggle-prevent-default
      :title="Translator.trans('tag.move')">
      <i class="fa fa-angle-double-right" aria-hidden="true"></i>
    </a>TESTAS
    <a
      href="#"
      data-cy="renameTagField"
      class="o-toggle__trigger u-mr-0_5 js__toggleAnything"
      data-toggle-container="form"
      :data-toggle="`#rename-tag-${tag.id}`"
      data-toggle-prevent-default
      :title="Translator.trans('tag.rename')">
      <i class="fa fa-pencil" aria-hidden="true" />
    </a>

    <button
      class="btn--blank o-link--default align-top"
      data-cy="deleteTagField"
      name="r_deletetag"
      :value="tag.id"
      data-form-actions-confirm-simple
      :title="Translator.trans('tag.delete')">
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
    }
  }
}
</script>
