<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    class="c-treelist"
    :class="{ 'is-dragging': dragging }">
    <!-- Header -->
    <div
      ref="header"
      class="c-treelist__header o-sticky line-height--2"
      :class="{ 'has-checkbox': checkAll }">
      <div class="flex bg-color--white">
        <dp-tree-list-checkbox
          v-if="checkAll"
          name="checkAll"
          v-model="allElementsSelected"
          check-all
          :style="checkboxIndentationStyle" />
        <div class="flex-grow color--grey">
          <slot name="header" />
        </div>
        <dp-tree-list-toggle
          class="color--grey"
          @input="toggleAll"
          :value="allElementsExpanded"
          toggle-all />
      </div>
    </div>

    <!-- Tree List -->
    <draggable
      ref="treeList"
      v-model="tree"
      v-bind="opts.draggable"
      class="list-style-none u-mb-0 u-1-of-1"
      :disabled="false === draggable"
      :group="true === opts.dragAcrossBranches ? 'treelistgroup' : 'noIdGiven'"
      :move="onMove"
      tag="ul"
      @end="handleDrag('end')"
      @start="handleDrag('start')"
      @change="(action) => handleChange(action, null)">
      <dp-tree-list-node
        v-for="node in treeData"
        :ref="`node_${node.id}`"
        :key="node.id"
        :check-branch="branchIdentifier"
        :children="node.children || []"
        :draggable="draggable"
        :handle-change="handleChange"
        :handle-drag="handleDrag"
        :level="0"
        :node="node"
        :node-id="node.id"
        :on-move="onMove"
        :options="opts"
        :parent-selected="allElementsSelected"
        @draggable-change="bubbleDraggableChange"
        @end="handleDrag('end')"
        @node-selected="handleSelectEvent"
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

    <!-- Footer -->
    <div
      v-if="$slots['footer']"
      ref="footer"
      class="c-treelist__footer o-sticky">
      <div class="u-p-0_5 bg-color--white">
        <slot name="footer" />
      </div>
    </div>
  </div>
</template>

<script>
import { deepMerge, hasOwnProp, Stickier } from 'demosplan-utils'
import DpTreeListCheckbox from './DpTreeListCheckbox'
import DpTreeListNode from './DpTreeListNode'
import DpTreeListToggle from './DpTreeListToggle'
import draggable from 'vuedraggable'
import { dragHandleWidth } from './utils/constants'

export default {
  name: 'DpTreeList',

  components: {
    DpTreeListCheckbox,
    DpTreeListNode,
    DpTreeListToggle,
    draggable
  },

  props: {
    branchIdentifier: {
      type: Function,
      required: true
    },

    draggable: {
      type: Boolean,
      required: false,
      default: true
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

    options: {
      type: Object,
      required: false,
      default: () => ({})
    },

    treeData: {
      type: Array,
      required: true
    }
  },

  data () {
    return {
      allElementsExpanded: false,
      allElementsSelected: false,

      /*
       * To be able to control the appearance of nodes when hovered vs. when dragged,
       * the outermost container receives an `is-dragging` class if a node is dragged.
       */
      dragging: false,

      opts: {},
      selectedNodesObject: {}
    }
  },

  computed: {
    checkAll () {
      return this.opts.branchesSelectable || this.opts.leavesSelectable
    },

    checkboxIndentationStyle () {
      const margin = this.opts.dragBranches || this.opts.dragLeaves ? dragHandleWidth : 0
      return `margin-left: ${margin}px;`
    },

    selectedNodes () {
      return Object.keys(this.selectedNodesObject).map(id => this.selectedNodesObject[id])
    },

    /**
     * The `tree` getter is called whenever this.treeData is changed from outside DpTreeList.
     * The setter is called whenever the tree structure is changed from within the draggable instance.
     * It emits the changed structure, which may be used in the parent component to update treeData accordingly.
     */
    tree: {
      get () {
        return this.treeData
      },

      set (val) {
        this.$emit('tree-data-change', { nodeId: null, newOrder: val })
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

    destroyFixedControls () {
      this.stickyHeader.destroy()
      this.stickyFooter.destroy()
    },

    filterSelectableNodes (selectedNodes) {
      if (this.opts.branchesSelectable && this.opts.leavesSelectable) return selectedNodes

      return selectedNodes.filter(({ nodeType }) => {
        let nodeSelectable = false
        if ((this.opts.branchesSelectable && nodeType === 'branch') ||
          (this.opts.leavesSelectable && nodeType === 'leaf')) {
          nodeSelectable = true
        }

        return nodeSelectable
      })
    },

    /**
     * Handler for the draggable `change` event.
     * The change event fires whenever the order of items is changed by a drag interaction.
     * The handler then emits an event that is being bubbled up the tree.
     * @see https://github.com/SortableJS/Vue.Draggable#events
     * @param action
     * @param nodeId
     * @emits draggable-change
     */
    handleChange (action, nodeId) {
      // The event should only be emitted if an element is moved inside or into a folder.
      if (hasOwnProp(action, 'added') || hasOwnProp(action, 'moved')) {
        const { element, newIndex } = hasOwnProp(action, 'moved') ? action.moved : action.added
        const payload = {
          elementId: element.id,
          newIndex: newIndex,
          parentId: nodeId
        }
        this.$emit('draggable-change', payload)
      }
    },

    /**
     * Set `dragging` to true if called via `start` event of draggable, else to false.
     * @param eventType
     * @emits <start|end>
     */
    handleDrag (eventType) {
      this.$emit(eventType)
      this.dragging = (eventType === 'start')
    },

    handleSelectEvent (selections) {
      const filteredSelections = this.filterSelectableNodes(selections)

      filteredSelections.forEach(selection => this.setSelectionState(selection))

      this.$emit('node-selection-change', this.selectedNodes)
    },

    // Header and Footer should be fixed to the top/bottom of the page when the TreeList exceeds the viewport height.
    initFixedControls () {
      this.stickyHeader = new Stickier(this.$refs.header, this.$refs.treeList.$el, 0)

      if (this.$slots.footer) {
        this.stickyFooter = new Stickier(this.$refs.footer, this.$refs.treeList.$el, 0, 'bottom')
      }
    },

    setSelectionState (elem) {
      if (elem.selectionState === true) {
        this.selectedNodesObject = { ...this.selectedNodesObject, ...{ [elem.nodeId]: elem } }
      }

      if (elem.selectionState === false) {
        const selectionCpy = { ...this.selectedNodesObject }
        delete selectionCpy[elem.nodeId]
        this.selectedNodesObject = selectionCpy
      }
    },

    toggleAll () {
      this.$root.$emit('treelist:toggle-all', !this.allElementsExpanded)
      this.allElementsExpanded = !this.allElementsExpanded
    },

    unselectAll () {
      this.selectedNodes.forEach(node => {
        if (this.$refs['node_' + node.id]) {
          this.$refs['node_' + node.id][0].setSelectionState(false)
        }
      })

      this.selectedNodesObject = {}
      this.allElementsSelected = false

      this.$emit('node-selection-change', this.selectedNodes)
    }
  },

  mounted () {
    const defaults = {
      branchesSelectable: false,
      leavesSelectable: false,
      rootDraggable: false,
      dragAcrossBranches: false,
      dragBranches: false,
      dragLeaves: false,
      // Options that are directly bound to the instances of vuedraggable
      draggable: {
        /*
         * The `handle` property is used both to style the handle and as a DOM hook for draggable,
         * that's why it is noted with the leading dot here. This is to be refactored later.
         */
        handle: '.c-treelist__drag-handle',
        ghostClass: 'c-treelist__node-ghost',
        chosenClass: 'c-treelist__node-chosen'
      },
      checkboxIdentifier: {
        branch: 'nodeSelected',
        leaf: 'nodeSelected'
      },
      selectOn: {
        childSelect: false,
        parentSelect: false
      },
      deselectOn: {
        childDeselect: false,
        parentDeselect: false
      }
    }
    this.opts = deepMerge(defaults, this.options)

    this.initFixedControls()
  }
}
</script>
