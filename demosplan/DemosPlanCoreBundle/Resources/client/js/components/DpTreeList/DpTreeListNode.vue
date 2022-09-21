<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li
    class="border--top position--relative"
    data-cy="treeListNode">
    <div class="c-treelist__node flex">
      <div
        class="display--inline-block u-p-0_25 u-pr-0 u-mt-0_125"
        :class="dragHandle"
        v-if="isDraggable">
        <dp-icon
          class="c-treelist__drag-handle-icon"
          icon="drag-handle" />
      </div>
      <dp-tree-list-checkbox
        v-if="isSelectable"
        :checked="isSelected"
        :name="checkboxIdentifier"
        :string-value="nodeId"
        @check="setSelectionState({ selectionState: !isSelected })" />
      <div
        class="flex flex-grow flex-items-start"
        :style="indentationStyle">
        <dp-tree-list-toggle
          class="c-treelist__folder text--left u-pv-0_25"
          :class="{'pointer-events-none': 0 === children.length}"
          :icon-class-prop="iconClassFolder"
          v-if="isBranch"
          v-model="isExpanded" />
        <div class="flex-grow u-pl-0 u-p-0_25">
          <slot
            v-if="isBranch"
            name="branch"
            :node-element="node"
            :node-children="children"
            :node-id="nodeId"
            :parent-id="parentId" />
          <slot
            v-if="isLeaf"
            name="leaf"
            :node-element="node"
            :node-id="nodeId"
            :parent-id="parentId" />
        </div>
      </div>
      <dp-tree-list-toggle
        data-cy="treeListToggle"
        v-if="isBranch"
        class="align-self-start"
        :disabled="!hasToggle"
        v-tooltip="Translator.trans(!hasToggle ? 'no.elements.existing' : '')"
        v-model="isExpanded" />
      <div
        v-else
        class="min-width-25" />
    </div>
    <draggable
      v-model="tree"
      v-bind="options.draggable"
      class="list-style-none u-mb-0 u-1-of-1"
      :disabled="hasDraggableChildren === false"
      :group="options.dragAcrossBranches ? 'treelistgroup' : nodeId"
      tag="ul"
      :move="onMove"
      @change="(action) => handleChange(action, nodeId)"
      @end="handleDrag('end')"
      @start="handleDrag('start')">
      <dp-tree-list-node
        v-for="child in children"
        v-show="true === isExpanded"
        :ref="`node_${child.id}`"
        :key="child.id"
        :check-branch="checkBranch"
        :children="child.children || []"
        :handle-change="handleChange"
        :handle-drag="handleDrag"
        :level="level + 1"
        :node="child"
        :node-id="child.id"
        :on-move="onMove"
        :options="options"
        :parent-id="nodeId"
        :parent-selected="isSelected"
        @draggable-change="bubbleDraggableChange"
        @end="handleDrag('end')"
        @node-selected="handleChildSelectionChange"
        @start="handleDrag('start')"
        @tree-data-change="bubbleChangeEvent">
        <template
          v-for="slot in Object.keys($scopedSlots)"
          v-slot:[slot]="scope">
          <slot
            :name="slot"
            v-bind="scope" />
        </template>
      </dp-tree-list-node>
    </draggable>
  </li>
</template>

<script>
import { checkboxWidth, dragHandleWidth, levelIndentationWidth } from './utils/constants'
import { DpIcon } from 'demosplan-ui/components'
import DpTreeListCheckbox from './DpTreeListCheckbox'
import DpTreeListToggle from './DpTreeListToggle'
import draggable from 'vuedraggable'

export default {
  name: 'DpTreeListNode',

  components: {
    DpIcon,
    DpTreeListCheckbox,
    DpTreeListToggle,
    draggable
  },

  props: {
    checkBranch: {
      type: Function,
      required: true
    },

    children: {
      type: Array,
      required: true
    },

    draggable: {
      type: Boolean,
      required: false,
      default: true
    },

    /*
     * The function to handle the draggable "change" event is passed as a prop here.
     * This way we do not run into performance issues with deeply nested lists.
     * @see https://www.digitalocean.com/community/tutorials/vuejs-communicating-recursive-components
     */
    handleChange: {
      type: Function,
      required: true
    },

    /*
     * The function to handle the draggable "start" and "end" events is passed as a prop here.
     */
    handleDrag: {
      type: Function,
      required: true
    },

    level: {
      type: Number,
      required: true
    },

    node: {
      type: Object,
      required: true
    },

    nodeId: {
      type: String,
      required: true
    },

    onMove: {
      type: Function,
      required: true
    },

    options: {
      type: Object,
      required: false,
      default: () => ({})
    },

    parentId: {
      type: String,
      required: false,
      default: ''
    },

    parentSelected: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  data () {
    return {
      isExpanded: false,
      isSelected: false
    }
  },

  computed: {
    checkboxIdentifier () {
      const prefix = this.isBranch
        ? this.options.checkboxIdentifier.branch
        : this.options.checkboxIdentifier.leaf
      return prefix + ':' + this.nodeId
    },

    dragHandle () {
      const str = this.options.draggable.handle
      return str.substring(1, str.length)
    },

    hasDraggableChildren () {
      return this.isBranch && (this.options.dragLeaves || this.options.dragBranches) && this.draggable
    },

    hasToggle () {
      return this.isBranch && this.children.length > 0
    },

    iconClassFolder () {
      const hasContent = this.children.length > 0
      let folderClass
      if (hasContent) {
        folderClass = this.isExpanded ? 'fa-folder-open' : 'fa-folder'
      } else {
        folderClass = 'fa-folder-o'
      }
      return 'fa ' + folderClass
    },

    indentationStyle () {
      let margin = this.level * levelIndentationWidth

      // If leaves are draggable, but branches are not, or vice versa, add extra space
      if ((this.isBranch && !this.options.dragBranches && this.options.dragLeaves) ||
        (this.isLeaf && this.options.dragBranches && !this.options.dragLeaves)) {
        margin += dragHandleWidth
      }

      // If leaves are selectable, but branches are not, or vice versa, add extra space
      if ((this.isBranch && !this.options.branchesSelectable && this.options.leavesSelectable) ||
        (this.isLeaf && this.options.branchesSelectable && !this.options.leavesSelectable)) {
        margin += checkboxWidth
      }

      return `margin-left: ${margin}px;`
    },

    isBranch () {
      return this.checkBranch({ node: this.node, children: this.children, id: this.nodeId })
    },

    isDraggable () {
      return this.isDraggableLeaf || this.isDraggableBranch
    },

    isDraggableBranch () {
      return this.options.dragBranches && this.isBranch
    },

    isDraggableLeaf () {
      return this.options.dragLeaves && this.isLeaf
    },

    isLeaf () {
      return this.isBranch === false
    },

    isSelectable () {
      return (this.isBranch && this.options.branchesSelectable) || (this.isLeaf && this.options.leavesSelectable) || false
    },

    // See docblock in `tree` computed of parent component.
    tree: {
      get () {
        return this.children
      },

      set (val) {
        this.$emit('tree-data-change', { nodeId: this.nodeId, newOrder: val })
      }
    }
  },

  watch: {
    parentSelected (val) {
      if (this.options.selectOn.parentSelect && val === true && this.isSelected === false) {
        this.setSelectionState({ selectionState: val, fromParent: true })
      }

      if (this.options.deselectOn.parentDeselect && val === false && this.isSelected === true) {
        this.setSelectionState({ selectionState: val, fromParent: true })
      }
    }
  },

  methods: {
    bubbleChangeEvent (payload) {
      this.$emit('tree-data-change', payload)
    },

    bubbleDraggableChange (payload) {
      this.$emit('draggable-change', payload)
    },

    handleChildSelectionChange (selections) {
      // Extract child selection state from the latest selection event
      const childSelectionState = selections[selections.length - 1].selectionState

      if (this.options.deselectOn.childDeselect && childSelectionState === false && this.isSelected === true) {
        this.setSelectionState({ selectionState: childSelectionState, selections })
      } else if (this.options.selectOn.childSelect && childSelectionState === true && this.isSelected === false) {
        this.setSelectionState({ selectionState: childSelectionState, selections })
        // Just bubble the event if the current node doesn't require any changes
      } else {
        this.$emit('node-selected', selections)
      }
    },

    setSelectionState ({ selectionState, selections = [], fromParent = false }) {
      const selectionsCpy = [...selections]

      this.isSelected = selectionState
      selectionsCpy.push({
        nodeId: this.nodeId,
        nodeType: this.isBranch === true ? 'branch' : 'leaf',
        selectionState: selectionState
      })

      if (fromParent === false) {
        this.$emit('node-selected', selectionsCpy)
      }
    }
  },

  mounted () {
    this.$root.$on('treelist:toggle-all', (expanded) => (this.isExpanded = expanded))
  }
}
</script>
