<template>
  <div class="grid grid-cols-[1fr,auto,auto] items-center gap-1">
    <div class="flex space-x-1">
      <dp-icon
        v-if="item.type === 'InstitutionTag'"
        class="text-muted mt-[2px]"
        icon="tag" />
      <div
        v-if="!isEditing"
        v-text="item.name" />
      <dp-input
        v-else
        data-cy="tagListItem:tagName"
        id="tagName"
        maxlength="250"
        required
        v-model="name" />
    </div>
    <div class="flex">
      <template v-if="!isEditing">
        <dp-button
          color="secondary"
          data-cy="tagListItem:delete"
          hide-text
          icon="delete"
          :text="Translator.trans('delete')"
          variant="subtle"
          @click="$emit('delete', item)" />
        <dp-button
          class="u-pl-0"
          color="secondary"
          data-cy="tagListItem:edit"
          hide-text
          icon="edit"
          :text="Translator.trans('edit')"
          variant="subtle"
          @click="edit" />
      </template>
      <template v-else>
        <dp-button
          color="primary"
          data-cy="tagListItem:save"
          hide-text
          icon="check"
          :text="Translator.trans('save')"
          variant="subtle"
          @click="save" />
        <dp-button
          class="u-pl-0"
          color="primary"
          data-cy="tagListItem:abort"
          hide-text
          icon="xmark"
          :text="Translator.trans('abort')"
          variant="subtle"
          @click="abort" />
      </template>
    </div>
  </div>
</template>

<script>
import {
  DpButton,
  DpIcon,
  DpInput
} from '@demos-europe/demosplan-ui'

export default {
  name: 'TagListItem',

  components: {
    DpButton,
    DpIcon,
    DpInput
  },

  props: {
    item: {
      type: Object,
      required: true,
      validator: (item) => {
        return item.id && item.type && item.name
      }
    }
  },

  data () {
    return {
      isEditing: false,
      name: this.item.name
    }
  },

  methods: {
    abort () {
      this.isEditing = false
      this.name = this.item.name
    },

    edit () {
      this.isEditing = true
    },

    save () {
      this.isEditing = false
      this.$emit('save', {
        ...this.item,
        name: this.name
      })
    }
  }
}
</script>
