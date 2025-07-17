<template>
  <div class="mb">
    <label class="mb mr-[18px] inline-block">
      <input
        class="mr-0"
        type="checkbox"
        data-cy="checkAllItems"
        name="checkAllItems">
      {{ Translator.trans('all') }}
    </label>

    <button
      class="btn-icns ml"
      name="r_delete"
      data-cy="deleteSelectedItems"
      :data-form-actions-confirm="Translator.trans('check.items.marked.delete')">
      <i class="fa fa-times-circle" /> {{ Translator.trans('items.marked.delete') }}
    </button>
  </div>
</template>

<script>
export default {
  name: 'TagListBulkControls'
}
</script>
