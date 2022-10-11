<template>
  <draggable
    v-model="content"
    v-bind="opts"
    v-on="$listeners"
    :class="draggableClass"
    :disabled="false === isDraggable"
    :group="dragAcrossBranches ? 'treelistgroup' : groupId"
    :move="onMove"
    :tag="draggableTag"
    @change="action => handleChange(action, nodeId)"@end="handleDrag('end')"
    @start="handleDrag('start')">
    <slot />
  </draggable>
</template>

<script>
import draggable from 'vuedraggable'
export default {
  name: 'DpDraggable',

  components: {
    draggable
  },

  model: {
    prop: 'contentData',
    event: 'change'
  },

  props: {
    /*
     * Content you want to display in the draggable.
     */
    contentData: {
      type: Array,
      required: true
    },

    /*
     * Set to true if items should be draggable between different lists
     */
    dragAcrossBranches: {
      type: Boolean,
      required: false,
      default: true
    },

    /*
     * Set to true, if the content should be draggable and false if not.
     */
    isDraggable: {
      type: Boolean,
      required: false,
      default: true
    },

    /*
     * Css classes that will be added to the surrounding tag
     */
    draggableClass: {
      type: [String, Object],

      required: false,
      default: ''
    },

    /*
     * HTML node type of the element that draggable component create as outer element for the included slot eg 'ul'.
     */
    draggableTag: {
      type: String,
      required: false,
      default: 'div'
    },

    /*
     * Id for a group in which items should be draggable
     */
    groupId: {
      type: String,
      required: false,
      default: 'noIdGiven'
    },

    /*
     * The function to handle the draggable "change" event is passed as a prop here.
     * This way we do not run into performance issues with deeply nested lists.
     * @see https://www.digitalocean.com/community/tutorials/vuejs-communicating-recursive-components
     */
    handleChange: {
      type: Function,
      required: false,
      default: () => {}
    },

    /*
     * The function to handle the draggable "start" and "end" events is passed as a prop here.
     */
    handleDrag: {
      type: Function,
      required: false,
      default: () => {}
    },

    /*
     * Id of the draggable node to identify items in callbacks
     */
    nodeId: {
      type: String,
      required: false,
      default: null
    },

    /*
     * Callback to be executed on move event of draggable.
     * It can be used to cancel drag by returning false.
     */
    onMove: {
      type: Function,
      required: false,
      default: () => true
    },

    /*
     * Callback to be executed on move event of draggable.
     * It can be used to cancel drag by returning false.
     */
    opts: {
      type: Object,
      required: false,
      default: () => ({})
    }
  },

  computed: {
    content: {
      get () {
        return this.contentData
      },

      set (val) {
        this.$emit('change', { nodeId: this.nodeId, newOrder: val })
      }
    }
  }
}
</script>

