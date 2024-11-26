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
        maxlength="250"
        required
        v-model="item.name" />
    </div>
    <div class="flex">
      <template v-if="!isEditing">
        <dp-button
          color="secondary"
          hide-text
          icon="delete"
          :text="Translator.trans('delete')"
          variant="subtle"
          @click="$emit('delete', item)" />
        <dp-button
          class="u-pl-0"
          color="secondary"
          hide-text
          icon="edit"
          :text="Translator.trans('edit')"
          variant="subtle"
          @click="edit" />
      </template>
      <template v-else>
        <dp-button
          color="primary"
          hide-text
          icon="check"
          :text="Translator.trans('save')"
          variant="subtle"
          @click="save" />
        <dp-button
          class="u-pl-0"
          color="primary"
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
      isEditing: false
    }
  },

  methods: {
    abort () {
      this.isEditing = false
    },

    edit () {
      this.isEditing = true
    },

    save () {
      this.isEditing = false
    }
  }
}
</script>
