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
      @reset-selection="resetSelection">
      <template v-slot:default>
        <button
          v-if="hasPermission('feature_auto_switch_element_state')"
          type="button"
          class="btn--blank o-link--default u-mr-0_5"
          @click="bulkEdit">
          <i
            aria-hidden="true"
            class="fa fa-pencil u-mr-0_125" />
          {{ Translator.trans('change.state.at.date') }}
        </button>
        <button
          v-if="hasPermission('feature_admin_element_bulk_delete')"
          type="button"
          @click="bulkDelete"
          class="btn--blank o-link--default u-mr-0_5"
          :title="Translator.trans('plandocuments.delete')">
          <i
            aria-hidden="true"
            class="fa fa-trash u-mr-0_125" />
          {{ Translator.trans('delete') }}
        </button>
      </template>
    </dp-bulk-edit-header>
    <dp-loading v-if="isLoading" />
    <p
      v-else-if="treeData.length < 1"
      v-text="Translator.trans('plandocuments.no_elements')" />
    <dp-tree-list
      v-else
      :branch-identifier="isBranch"
      :draggable="canDrag"
      :on-move="onMove"
      :options="treeListOptions"
      :tree-data="treeData"
      ref="treeList"
      @draggable:change="saveNewSort"
      @node-selection-change="nodeSelectionChange"
      @tree:change="updateTreeData">
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
            :size="nodeElement.attributes.fileInfo.size" />
          <icon-published :published="nodeElement.attributes.visible" />
          <icon-statement-enabled
            v-if="hasPermission('feature_single_document_statement')"
            :enabled="nodeElement.attributes.statementEnabled" />
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
  hasOwnProp
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import ElementsAdminItem from './ElementsAdminItem'
import lscache from 'lscache'

export default {
  name: 'ElementsAdminList',

  components: {
    ElementsAdminItem,
    DpBulkEditHeader,
    DpLoading,
    DpTreeList,
    FileInfo: () => import(/* webpackChunkName: "elements-list-file" */ '@DpJs/components/document/ElementsList/FileInfo'),
    IconPublished: () => import(/* webpackChunkName: "elements-list-icon-published" */ '@DpJs/components/document/ElementsList/IconPublished'),
    IconStatementEnabled: () => import(/* webpackChunkName: "elements-list-icon-statement-enabled" */ '@DpJs/components/document/ElementsList/IconStatementEnabled')
  },

  data () {
    return {
      canDrag: true,
      hasBulkEdit: hasAnyPermissions(['feature_admin_element_bulk_delete', 'feature_auto_switch_element_state']),
      isLoading: true,
      treeData: [],
      selectedElements: [],
      selectedFiles: []
    }
  },

  computed: {
    ...mapState('Elements', {
      elements: 'items'
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
          leaf: 'documentSelected'
        },
        selectOn: {
          childSelect: false,
          parentSelect: true
        },
        deselectOn: {
          childDeselect: false,
          parentDeselect: true
        }
      }
    }
  },

  methods: {
    ...mapActions('Elements', {
      elementList: 'list',
      deleteElement: 'delete'
    }),

    ...mapMutations('Elements', {
      setElement: 'set'
    }),

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
      this.treeData = this.sortRecursive(tree, sortField)
    },

    /**
     * Delete all selected elements (type "elements")
     */
    bulkDelete () {
      if (dpconfirm(Translator.trans('check.entries.marked.delete'))) {
        this.selectedElements.forEach(el => {
          this.deleteElement(el)
            .then(() => {
              this.buildTree()
              this.resetSelection()

              // Clear cache from previously selected items.
              lscache.remove(`${dplan.procedureId}:selectedElements`)

              dplan.notify.notify('confirm', Translator.trans('confirm.entries.marked.deleted'))
            })
        })
      }
    },

    bulkEdit () {
      lscache.set(`${dplan.procedureId}:selectedElements`, this.selectedElements)
      window.location.href = Routing.generate('dplan_elements_bulk_edit', { procedureId: dplan.procedureId })
    },

    /**
     * Given a tree and a nodeId, this function returns the node with the given id.
     * @param tree
     * @param nodeId
     * @return {*}
     */
    findNodeById (tree, nodeId) {
      return tree.reduce((acc, node) => {
        if (acc) return acc
        if (node.id === nodeId) return node
        if (hasOwnProp(node, 'children')) {
          return this.findNodeById(node.children, nodeId)
        }
        return null
      }, null)
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
     * Here it is used to cancel the drag action when dragging over singleDocument
     * elements, hereby keeping folders above files.
     */
    onMove ({ relatedContext }) {
      return relatedContext.element.type !== 'SingleDocument'
    },

    resetSelection () {
      this.selectedElements = []
      this.$refs.treeList.unselectAll()
    },

    /**
     * Persist new sort order.
     * The parentId is used to save sort across branches.
     *
     * @param action {Object<elementId, newIndex, parentId>}
     */
    saveNewSort ({ elementId, newIndex, parentId }) {
      // Find the node the element has being moved into
      const parentNode = this.findNodeById(this.treeData, parentId)
      // On the root level, treeData represents the children
      const children = parentId ? parentNode.children : this.treeData
      // Find the element that is directly following the moved element (only folders, no files)
      const nextChild = children.filter(node => node.type === 'Elements')[newIndex + 1]
      // Either send the index of the element that is being "pushed down" or undefined (if the moved element is the last item)
      const index = nextChild ? nextChild.attributes.index : null
      this.canDrag = false
      dpRpc('planningCategoryList.reorder', {
        elementId,
        newIndex: index,
        parentId
      })
        .then((response) => {
          /*
           * The response of the rpc should be an object with the elementIds as key
           * and the updated { index, parentId } as value. The store is then updated
           * with those values to rebuild the hierarchy with the correct indexes.
           */
          const elementsMap = response.data[0].result
          for (const id in elementsMap) {
            const storeElement = this.elements[id]
            const mapElement = elementsMap[id]
            if (typeof storeElement !== 'undefined') {
              this.setElement({
                ...storeElement,
                attributes: {
                  ...storeElement.attributes,
                  index: mapElement.index,
                  parentId: mapElement.parentId
                }
              })
            }
          }
          this.buildTree()
          this.canDrag = true
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('error.changes.not.saved'))
        })
    },

    /**
     * Recursively sort hierarchical category structure while sorting singleDocument items last.
     *
     * @param tree
     * @param sortField
     * @return {*}
     */
    sortRecursive (tree, sortField) {
      tree.sort((a, b) => {
        if (a.type !== 'SingleDocument' && b.type === 'SingleDocument') { return -1 }
        if (a.type === 'SingleDocument' && b.type !== 'SingleDocument') { return 1 }
        if (a.type === 'SingleDocument' && b.type === 'SingleDocument') {
          return a.attributes.index - b.attributes.index
        }
        return a.attributes[sortField] - b.attributes[sortField]
      })
      tree.forEach(el => {
        if (hasOwnProp(el, 'children')) {
          this.sortRecursive(el.children, sortField)
        }
      })

      return tree
    },

    /**
     * Updates the tree structure that respresents the draggable ui.
     * @param updatedSort {Object<newOrder,nodeId>}
     */
    updateTreeData (updatedSort) {
      if (hasOwnProp(updatedSort, 'newOrder')) {
        updatedSort.newOrder
          // Filter out items not represented in this.elements (files)
          .filter((el) => typeof this.elements[el.id] !== 'undefined')
          /*
           * Iterate over items that are present in updated order, set new index and parentId
           * in order to rebuild the tree structure to apply the new state to draggable inside DpTreeList.
           * idx is only a temporary index because in the backend, elements ids are not always counting
           * from 0 upwards, so the strategy to use the indexes that start from 0 here just applies
           * the new order to the ui until the rpc response in `saveNewSort()` sends the correct values
           * from the backend. The tree is then rebuild again with the proper values from there.
           */
          .forEach((el, idx) => {
            this.setElement({
              ...this.elements[el.id],
              attributes: {
                ...this.elements[el.id].attributes,
                idx,
                parentId: updatedSort.nodeId
              }
            })
          })

        this.buildTree('idx')
      }
    }
  },

  mounted () {
    // Initially get data from endpoint
    this.elementList({
      include: ['children', 'documents'].join(),
      filter: {
        sameProcedure: {
          condition: {
            path: 'procedure.id',
            value: dplan.procedureId
          }
        }
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
          'title'
        ].join(),
        SingleDocument: [
          'fileInfo',
          'index',
          'statementEnabled',
          'title',
          'visible'
        ].join()
      }
    })
      .then(() => {
        this.buildTree()
        this.getAllFiles()

        // Clear cache from previously selected items.
        lscache.remove(`${dplan.procedureId}:selectedElements`)

        // Finally, kickoff rendering
        this.isLoading = false
      })
  }
}
</script>
