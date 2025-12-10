<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
<!--
    Renders the list of elements in "Planungsdokumente und Planzeichnung" view.
 -->
</documentation>

<template>
  <div>
    <dp-bulk-edit-header
      v-if="hasBulkEdit"
      v-show="selectedElements.length > 0"
      class="layout__item u-12-of-12 u-mv-0_5"
      :selected-items-text="Translator.trans('elements.selected', { count: selectedElements.length })"
      @reset-selection="resetSelection"
    >
      <template v-slot:default>
        <button
          v-if="hasPermission('feature_auto_switch_element_state')"
          type="button"
          class="btn--blank o-link--default u-mr-0_5"
          @click="bulkEdit"
        >
          <i
            aria-hidden="true"
            class="fa fa-pencil u-mr-0_125"
          />
          {{ Translator.trans('change.state.at.date') }}
        </button>
        <button
          v-if="hasPermission('feature_admin_element_bulk_delete')"
          type="button"
          class="btn--blank o-link--default u-mr-0_5"
          :title="Translator.trans('plandocuments.delete')"
          @click="bulkDelete"
        >
          <i
            aria-hidden="true"
            class="fa fa-trash u-mr-0_125"
          />
          {{ Translator.trans('delete') }}
        </button>
      </template>
    </dp-bulk-edit-header>
    <dp-loading v-if="isLoading" />
    <p
      v-else-if="treeData.length < 1"
      v-text="Translator.trans('plandocuments.no_elements')"
    />
    <dp-tree-list
      v-else
      ref="treeList"
      :branch-identifier="isBranch"
      :draggable="canDrag"
      :on-move="onMove"
      :options="treeListOptions"
      :tree-data="treeData"
      @end="(event, item, parentId) => saveNewSort(event, parentId)"
      @node-selection-change="nodeSelectionChange"
      @tree:change="updateTreeData"
    >
      <template v-slot:header="">
        <span class="color--grey">
          {{ Translator.trans('procedure.documents') }}
        </span>
      </template>
      <template v-slot:branch="{ nodeElement }">
        <elements-admin-item :element-id="nodeElement.id" />
      </template>
      <template v-slot:leaf="{ nodeElement }">
        <div class="flex justify-end space-inline-s">
          <file-info
            class="u-mr-auto"
            :hash="nodeElement.attributes.fileInfo.hash"
            :name="nodeElement.attributes.fileInfo.name"
            :size="nodeElement.attributes.fileInfo.size"
          />
          <icon-published :published="nodeElement.attributes.visible" />
          <icon-statement-enabled
            v-if="hasPermission('feature_single_document_statement')"
            :enabled="nodeElement.attributes.statementEnabled"
          />
        </div>
      </template>
    </dp-tree-list>
  </div>
</template>

<script>
import {
  DpBulkEditHeader,
  DpLoading,
  dpRpc,
  DpTreeList,
  hasAnyPermissions,
  hasOwnProp,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import { defineAsyncComponent } from 'vue'
import ElementsAdminItem from './ElementsAdminItem'
import lscache from 'lscache'

export default {
  name: 'ElementsAdminList',

  components: {
    ElementsAdminItem,
    DpBulkEditHeader,
    DpLoading,
    DpTreeList,
    FileInfo: defineAsyncComponent(() => import('@DpJs/components/document/ElementsList/FileInfo')),
    IconPublished: defineAsyncComponent(() => import('@DpJs/components/document/ElementsList/IconPublished')),
    IconStatementEnabled: defineAsyncComponent(() => import('@DpJs/components/document/ElementsList/IconStatementEnabled')),
  },

  data () {
    return {
      canDrag: true,
      expandedNodeRefs: new Map(), // Map of nodeId -> component ref
      hasBulkEdit: hasAnyPermissions(['feature_admin_element_bulk_delete', 'feature_auto_switch_element_state']),
      isLoading: true,
      treeData: [],
      selectedElements: [],
      selectedFiles: [],
    }
  },

  computed: {
    ...mapState('Elements', {
      elements: 'items',
    }),

    treeListOptions () {
      return {
        branchesSelectable: this.hasBulkEdit,
        leavesSelectable: false,
        dragBranches: true,
        dragAcrossBranches: false,
        dragLeaves: false,
        rootDraggable: true,
        checkboxIdentifier: {
          branch: 'elementSelected',
          leaf: 'documentSelected',
        },
        selectOn: {
          childSelect: false,
          parentSelect: true,
        },
        deselectOn: {
          childDeselect: false,
          parentDeselect: true,
        },
      }
    },
  },

  methods: {
    ...mapActions('Elements', {
      elementList: 'list',
      deleteElement: 'delete',
    }),

    ...mapMutations('Elements', {
      setElement: 'set',
    }),

    /**
     * Apply optimistic UI update by updating idx values and rebuilding tree
     * @param {Array} reorderedFolders - Folders in new order
     */
    applyOptimisticReorder (reorderedFolders) {
      // Update idx values to reflect the new order
      for (const [index, folder] of reorderedFolders.entries()) {
        const storeElement = this.elements[folder.id]

        if (storeElement) {
          this.setElement({
            ...storeElement,
            attributes: {
              ...storeElement.attributes,
              idx: index,
            },
          })
        }
      }

      // Rebuild tree with temporary idx values
      this.buildTreeWithPreservedExpandedState(true)
    },

    /**
     * Build a tree representation of the elements, nested by node.attributes.parentId
     * and sorted by node.attributes.index.
     *
     * @param sortField which fields should be used to sort items. If the `idx` field is used,
     *                  the `index` field should not be synchronized with it.
     */
    buildTree (sortField = 'index') {
      const elementsCopy = JSON.parse(JSON.stringify(Object.values(this.elements)))
      const tree = this.listToTree(elementsCopy)

      this.treeData = this.sortRecursively(tree, sortField)
    },

    buildTreeWithPreservedExpandedState (updateOrder) {
      // Preserve expanded state before rebuilding
      this.collectExpandedNodes()

      if (updateOrder) {
        // Rebuild tree with temporary idx values to show optimistic state
        this.buildTree('idx')
      } else {
        this.buildTree()
      }

      // Restore expanded state after rebuild
      this.restoreExpandedNodes()
    },

    /**
     * Delete all selected elements (type "elements")
     */
    bulkDelete () {
      if (dpconfirm(Translator.trans('check.entries.marked.delete'))) {
        // Parent deletion cascades to children, so only delete parents if both selected
        const elementsToDelete = this.filterTopLevelParents(this.selectedElements)
        const deletePromises = elementsToDelete.map(el => this.deleteElement(el))

        Promise.all(deletePromises)
          .then(() => {
            this.buildTree()
            this.resetSelection()

            // Clear cache from previously selected items.
            lscache.remove(`${dplan.procedureId}:selectedElements`)

            dplan.notify.notify('confirm', Translator.trans('confirm.entries.marked.deleted'))
          })
          .catch(error => {
            console.error('Error during bulk deletion:', error)

            // Still perform cleanup even if some deletions failed
            this.buildTree()
            this.resetSelection()
            lscache.remove(`${dplan.procedureId}:selectedElements`)

            dplan.notify.notify('error', Translator.trans('error.entries.marked.deleted'))
          })
      }
    },

    bulkEdit () {
      lscache.set(`${dplan.procedureId}:selectedElements`, this.selectedElements)
      globalThis.location.href = Routing.generate('dplan_elements_bulk_edit', { procedureId: dplan.procedureId })
    },

    /**
     * Collect ids and refs of all currently expanded nodes
     * @param tree
     * @param parentComponent - The parent component to access child refs from
     */
    collectExpandedNodes (tree = this.treeData, parentComponent = null) {
      if (!this.$refs.treeList) {
        return
      }

      // Clear previous state to ensure we have current state
      if (tree === this.treeData) {
        this.expandedNodeRefs.clear()
      }

      // Use the parent component if provided, otherwise use root treeList
      const componentToSearch = parentComponent || this.$refs.treeList

      for (const node of tree) {
        if (node.type === 'Elements') {
          const nodeRef = componentToSearch.$refs[`node_${node.id}`]

          if (nodeRef && nodeRef[0] && nodeRef[0].isExpanded) {
            // Store both the node ID and its component ref for fast restoration
            this.expandedNodeRefs.set(node.id, nodeRef[0])
          }

          // Recursively check children, passing the current node's component as parent
          if (node.children && node.children.length > 0 && nodeRef && nodeRef[0]) {
            this.collectExpandedNodes(node.children, nodeRef[0])
          }
        }
      }
    },

    /**
     * Fetch elements from API and initialize the tree
     */
    fetchElements () {
      this.elementList({
        include: ['children', 'documents'].join(),
        filter: {
          sameProcedure: {
            condition: {
              path: 'procedure.id',
              value: dplan.procedureId,
            },
          },
        },
        fields: {
          Elements: [
            'category',
            'children',
            'designatedSwitchDate',
            'documents',
            'enabled',
            'fileInfo',
            'filePathWithHash',
            'index',
            'parentId',
            'title',
          ].join(),
          SingleDocument: [
            'fileInfo',
            'index',
            'statementEnabled',
            'title',
            'visible',
          ].join(),
        },
      })
        .then(() => {
          this.buildTree()
          this.getAllFiles()

          // Clear cache from previously selected items.
          lscache.remove(`${dplan.procedureId}:selectedElements`)

          // Finally, kickoff rendering
          this.isLoading = false
        })
    },

    /**
     * Given a tree and a nodeId, this function returns the node with the given id.
     * @param tree
     * @param nodeId
     * @return {*}
     */
    findNodeById (tree, nodeId) {
      return tree.reduce((acc, node) => {
        if (acc) {
          return acc
        }
        if (node.id === nodeId) {
          return node
        }
        if (hasOwnProp(node, 'children')) {
          return this.findNodeById(node.children, nodeId)
        }

        return null
      }, null)
    },

    /**
     * Filter out child elements when their parents are also selected
     * to avoid faulty API calls when deleting (parent deletion cascades to children)
     * @param selectedIds
     */
    filterTopLevelParents (selectedIds) {
      // Use Set for O(1) lookup performance
      const selectedSet = new Set(selectedIds)

      return selectedIds.filter(elementId => {
        const element = this.elements[elementId]

        if (!element?.attributes?.parentId) {
          return true
        }

        return !selectedSet.has(element.attributes.parentId)
      })
    },

    /**
     * Initially get the files attached to all elements to calculate the size for all files.
     * However, this does not have to be reactive since does not change.
     */
    getAllFiles () {
      this.allFiles = Object.values(this.elements).reduce((documents, element) => {
        if (element.hasRelationship('documents')) {
          return [...documents, ...Object.values(element.relationships.documents.list())]
        } else {
          return documents
        }
      }, [])
    },

    /**
     * Get reordered folders and target backend index
     * @param {Array} siblingsList - List of siblings
     * @param {String} itemId - ID of the item being moved
     * @param {Number} newIndex - New position index
     * @return {Object} Object containing reorderedFolders and targetBackendIndex
     */
    getReorderedData (siblingsList, itemId, newIndex) {
      // Filter to get only folders (Elements), not files (SingleDocument)
      const foldersOnly = siblingsList.filter(node => node.type === 'Elements')

      // Sort by current order
      const sortedFolders = [...foldersOnly]
        .sort((a, b) => a.attributes.index - b.attributes.index)

      // Find current position of moved folder
      const currentPosition = sortedFolders.findIndex(folder => folder.id === itemId)

      const reorderedFolders = [...sortedFolders]
      // Simulate the move: remove from old position and insert at new position
      const [movedFolder] = reorderedFolders.splice(currentPosition, 1)
      reorderedFolders.splice(newIndex, 0, movedFolder)

      // Find the folder that will come AFTER the moved folder
      const folderAfterMove = reorderedFolders[newIndex + 1]
      const targetBackendIndex = folderAfterMove ? folderAfterMove.attributes.index : null

      return {
        reorderedFolders,
        targetBackendIndex,
      }
    },

    /**
     * Get the siblings list for a given parent
     * @param {String|null} parentId - The parent ID, or null for root level
     * @return {Array|null} The siblings list, or null if parent not found
     */
    getSiblingsList (parentId) {
      if (parentId === null) {
        // Root level - use treeData directly
        return this.treeData
      }

      // Find the parent node in the tree
      const parentNode = this.findNodeById(this.treeData, parentId)

      if (!parentNode || !parentNode.children) {
        return null
      }

      return parentNode.children
    },

    /**
     * This function is passed to DpTreeList to choose if an item is a branch (a.k.a. "folder") or leaf.
     * Nodes can be of type "singleDocument" or "elements" [sic!]
     */
    isBranch ({ node }) {
      return node.type === 'Elements'
    },

    /**
     * Transform the data provided by the vuex-api plugin into a hierarchical structure to pass into DpTreeList.
     * See https://stackoverflow.com/questions/18017869/build-tree-array-from-flat-array-in-javascript
     *
     * @param list{Array}
     */
    listToTree (list) {
      const roots = []

      // Initialize children in list elements
      for (const [index] of list.entries()) {
        list[index].children = []
      }

      for (const [index] of list.entries()) {
        const node = list[index]

        // If not already set, copy the `index` value to an additional field `idx`.
        if (!hasOwnProp(node.attributes, 'idx')) {
          node.attributes.idx = node.attributes.index
        }

        // Make documents direct children of node, if there are any
        if (this.elements[node.id].hasRelationship('documents')) {
          node.children = [...node.children, ...Object.values(this.elements[node.id].relationships.documents.list())]
        }

        // Push item to correct position in map
        if (node.attributes.parentId) {
          list.find(el => el.id === node.attributes.parentId)?.children.push(node)
        } else {
          roots.push(node)
        }
      }

      return roots
    },

    /**
     * Set the selection state for the different items.
     *
     * @param selected{Array<Object>}
     */
    nodeSelectionChange (selected) {
      this.selectedFiles = selected
        .filter(node => node.nodeType === 'leaf')
        .map(el => el.nodeId)

      this.selectedElements = selected
        .filter(node => node.nodeType === 'branch')
        .map(el => el.nodeId)
    },

    /**
     * Callback that is executed whenever an item is dragged over a new target.
     * Here it is used to cancel the drag action when dragging over singleDocument (=!isBranch)
     * elements, hereby keeping folders above files.
     * @param _event
     * @param {Boolean} isAllowedTarget Is the item allowed to be dragged into the target list?
     * @return {Boolean} isAllowedTarget
     */
    onMove (_event, isAllowedTarget) {
      return isAllowedTarget
    },

    /**
     * Prepare reorder by getting siblings and calculating reordered data
     * @param {String|null} parentId - Parent ID or null for root
     * @param {String} itemId - ID of item being moved
     * @param {Number} newIndex - New position index
     * @return {Object|null} Reordered data or null if siblings not found
     */
    prepareReorder (parentId, itemId, newIndex) {
      const siblingsList = this.getSiblingsList(parentId)

      if (!siblingsList) {
        return null
      }

      return this.getReorderedData(siblingsList, itemId, newIndex)
    },

    resetSelection () {
      this.selectedElements = []
      this.$refs.treeList.unselectAll()
    },

    /**
     * Restore expanded state for previously expanded nodes (O(n) single traversal)
     */
    restoreExpandedNodes () {
      if (this.expandedNodeRefs.size === 0) {
        return
      }

      this.$nextTick(() => {
        this.restoreExpandedNodesRecursively()
      })
    },

    /**
     * Restore expanded state by traversing tree once and checking against cached ids
     * @param tree
     * @param parentComponent
     */
    restoreExpandedNodesRecursively (tree = this.treeData, parentComponent = null) {
      const componentToSearch = parentComponent || this.$refs.treeList

      for (const node of tree) {
        if (node.type === 'Elements') {
          const nodeRef = componentToSearch.$refs[`node_${node.id}`]

          // If this node was expanded, restore it
          if (nodeRef && nodeRef[0] && this.expandedNodeRefs.has(node.id)) {
            nodeRef[0].isExpanded = true
          }

          // Recursively restore children
          if (node.children && node.children.length > 0 && nodeRef && nodeRef[0]) {
            this.restoreExpandedNodesRecursively(node.children, nodeRef[0])
          }
        }
      }
    },

    /**
     * Persist new sort order.
     * The parentId is used to save sort across branches.
     *
     * @param {Object< newIndex, oldIndex, item >} event
     * @param {String} parentId
     */
    saveNewSort ({ newIndex, oldIndex, item }, parentId) {
      const { id } = item

      // If item is not moved, do nothing
      if (newIndex === oldIndex) {
        return
      }

      const reorderData = this.prepareReorder(parentId, id, newIndex)

      if (!reorderData) {
        return
      }

      const { reorderedFolders, targetBackendIndex } = reorderData

      // Apply optimistic UI update
      this.applyOptimisticReorder(reorderedFolders)

      this.canDrag = false

      dpRpc('planningCategoryList.reorder', {
        elementId: id,
        newIndex: newIndex === 0 ? newIndex : targetBackendIndex,
        parentId,
      })
        .then(response => {
          // Update store with backend response
          const elementsMap = response.data[0].result
          this.updateElementsFromResponse(elementsMap)

          // Rebuild tree with backend data
          this.buildTreeWithPreservedExpandedState(false)

          this.canDrag = true
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(error => {
          console.error(error)

          // Rebuild tree to revert optimistic changes
          this.buildTreeWithPreservedExpandedState(false)

          this.canDrag = true
          dplan.notify.error(Translator.trans('error.changes.not.saved'))
        })
    },

    /**
     * Update store elements with backend response data
     * @param {Object} elementsMap - Map of elementId to {index, parentId}
     */
    updateElementsFromResponse (elementsMap) {
      for (const id in elementsMap) {
        const storeElement = this.elements[id]
        const mapElement = elementsMap[id]

        if (storeElement) {
          this.setElement({
            ...storeElement,
            attributes: {
              ...storeElement.attributes,
              index: mapElement.index,
              parentId: mapElement.parentId,
            },
          })
        }
      }
    },

    /**
     * Recursively sort hierarchical category structure while sorting singleDocument items last.
     *
     * @param tree
     * @param sortField
     * @return {*}
     */
    sortRecursively (tree, sortField) {
      tree.sort((a, b) => {
        if (a.type !== 'SingleDocument' && b.type === 'SingleDocument') {
          return -1
        }

        if (a.type === 'SingleDocument' && b.type !== 'SingleDocument') {
          return 1
        }

        if (a.type === 'SingleDocument' && b.type === 'SingleDocument') {
          return a.attributes.index - b.attributes.index
        }

        return a.attributes[sortField] - b.attributes[sortField]
      })

      for (const el of tree) {
        if (hasOwnProp(el, 'children')) {
          this.sortRecursively(el.children, sortField)
        }
      }

      return tree
    },

    /**
     * Updates the tree structure that represents the draggable ui.
     * @param updatedSort {Object<newOrder,nodeId>}
     */
    updateTreeData (updatedSort) {
      if (hasOwnProp(updatedSort, 'newOrder')) {
        // Filter out items not represented in this.elements (files)
        const filteredOrder = updatedSort.newOrder
          .filter(el => typeof this.elements[el.id] !== 'undefined')

        /*
         * Iterate over items that are present in updated order, set new index and parentId
         * in order to rebuild the tree structure to apply the new state to draggable inside DpTreeList.
         * idx is only a temporary index because in the backend, elements ids are not always counting
         * from 0 upwards, so the strategy to use the indexes that start from 0 here just applies
         * the new order to the ui until the rpc response in `saveNewSort()` sends the correct values
         * from the backend. The tree is then rebuild again with the proper values from there.
         */
        for (const [idx, el] of filteredOrder.entries()) {
          this.setElement({
            ...this.elements[el.id],
            attributes: {
              ...this.elements[el.id].attributes,
              idx,
              parentId: updatedSort.nodeId,
            },
          })
        }

        this.buildTree('idx')
      }
    },
  },

  mounted () {
    this.fetchElements()
  },
}
</script>
