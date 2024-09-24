<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
<!--
    Renders the list of elements in the "public detail view" (documentListAction).
 -->
</documentation>

<template>
  <div>
    <dp-loading v-if="isLoading" />
    <form
      v-else
      :action="formAction"
      method="POST">
      <input
        name="_token"
        type="hidden"
        :value="csrfToken">

      <dp-tree-list
        @node-selection-change="nodeSelectionChange"
        :tree-data="recursiveElements"
        :branch-identifier="isBranch()"
        :options="treeListOptions">
        <template v-slot:header="">
          <span class="color--grey">Dokumente des Verfahrens</span>
        </template>
        <template v-slot:branch="{ nodeElement }">
          <div class="weight--bold">
            {{ nodeElement.attributes.title }}
          </div>
          <div
            v-if="nodeElement.attributes.text"
            class="whitespace-pre-line"
            v-cleanhtml="nodeElement.attributes.text" />
        </template>
        <template v-slot:leaf="{ nodeElement }">
          <file-info
            :hash="nodeElement.attributes.fileInfo.hash"
            :name="nodeElement.attributes.fileInfo.name"
            :size="nodeElement.attributes.fileInfo.size" />
        </template>
        <template v-slot:footer="">
          <button
            type="submit"
            class="btn btn--primary">
            <i
              class="fa fa-download u-mr-0_25"
              aria-hidden="true" />
            {{ buttonLabel }}
          </button>
          <p class="lbl__hint u-mt-0_125">
            {{ Translator.trans('plandocuments.explanation') }}
          </p>
        </template>
      </dp-tree-list>
    </form>
  </div>
</template>

<script>
import { CleanHtml, DpLoading, DpTreeList, formatBytes, hasOwnProp } from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'ElementsList',

  components: {
    DpLoading,
    DpTreeList,
    FileInfo: defineAsyncComponent(() => import('@DpJs/components/document/ElementsList/FileInfo'))
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      isLoading: true,
      recursiveElements: [],
      selectedFiles: []
    }
  },

  computed: {
    ...mapState('Elements', {
      elements: 'items'
    }),

    buttonLabel () {
      let buttonLabel
      if (this.selectedFiles.length > 0 && this.selectedFiles.length !== this.allFiles.length) {
        buttonLabel = `Ausgewählte Dokumente herunterladen (Zip, ca. ${this.accumulatedFileSize(this.selectedFiles)})`
      } else {
        buttonLabel = `Alle Dokumente herunterladen (Zip, ca. ${this.accumulatedFileSize(this.allFiles)})`
      }
      return buttonLabel
    },

    formAction () {
      return Routing.generate('DemosPlan_document_zip_files', { procedureId: dplan.procedureId })
    },

    treeListOptions () {
      return {
        branchesSelectable: true,
        leavesSelectable: true,
        dragAcrossBranches: false,
        rootDraggable: false,
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
      elementList: 'list'
    }),

    // The accumulated file size of an array of files objects, converted to readable format
    accumulatedFileSize (files) {
      const accumulator = (sum, current) => {
        return sum + parseInt(current.attributes.fileInfo.size)
      }
      const byteSize = files.length > 0 ? files.reduce(accumulator, 0) : 0
      return formatBytes(byteSize).replace(/\./g, ',')
    },

    nodeSelectionChange (selectedNodes) {
      const selectedSingleDocuments = selectedNodes.filter(el => el.nodeType === 'leaf')
      this.selectedFiles = selectedSingleDocuments
    },

    /*
     * This function is passed to DpTreeList.vue to be used to chose if an item is a branch (a.k.a. "folder") or leaf.
     * Nodes can be of type "singleDocument" or "elements" [sic!]
     */
    isBranch () {
      return function ({ node }) {
        return node.type === 'elements'
      }
    },

    /*
     * Transforms the data provided by the vuex-api plugin into a hierarchical structure to throw at DpTreeList.vue
     * See https://stackoverflow.com/questions/18017869/build-tree-array-from-flat-array-in-javascript
     */
    listToTree (list) {
      const map = {}
      let node
      let roots = []
      let index

      // Initialize map and children in list elements
      for (index = 0; index < list.length; index += 1) {
        map[list[index].id] = index
        list[index].children = []
      }

      for (index = 0; index < list.length; index += 1) {
        node = list[index]
        const isTopLevel = node.attributes.parentId === null

        // Make documents direct children of node, if there are any
        if (node.hasRelationship('visibleDocuments')) {
          node.children = [...node.children, ...Object.values(node.relationships.visibleDocuments.list())]
        }

        // Push item to correct position in map
        if (!isTopLevel) {
          const nodeParentIdx = map[node.attributes.parentId]
          const hasEnabledParent = typeof nodeParentIdx !== 'undefined'

          if (hasEnabledParent) {
            list[nodeParentIdx].children.push(node)
          }
        } else {
          roots.push(node)
        }
      }

      roots = this.reorderList(roots)
      return roots
    },

    /**
     * Generate new sorting recursively
     *
     * @param list
     * @return {*}
     */
    reorderList (list) {
      list.sort((a, b) => {
        if (a.type !== 'singleDocument' && b.type === 'singleDocument') { return -1 }
        if (a.type === 'singleDocument' && b.type !== 'singleDocument') { return 1 }
        return a.attributes.index - b.attributes.index
      })
      list.forEach(el => {
        if (hasOwnProp(el, 'children')) {
          this.reorderList(el.children)
        }
      })

      return list
    }
  },

  mounted () {
    // Initially get data from endpoint
    this.elementList({
      include: ['children', 'visibleDocuments'].join(),
      filter: {
        enabledElements: {
          condition: {
            path: 'enabled',
            value: 1
          }
        }
      },
      procedureId: dplan.procedureId
    })
      .then(() => {
        // Transform the object into an array, transform that into a recursive tree structure
        this.recursiveElements = this.listToTree(Object.values(this.elements))

        /*
         * Initially get the files attached to all elements to calculate the size for all files.
         * However, this does not have to be reactive since does not change.
         */
        this.allFiles = Object.values(this.elements).reduce((visibleDocuments, element) => {
          if (element.hasRelationship('visibleDocuments')) {
            return [...visibleDocuments, ...Object.values(element.relationships.visibleDocuments.list())]
          } else {
            return visibleDocuments
          }
        }, [])

        // Finally, kickoff rendering
        this.isLoading = false
      })
  }
}
</script>
