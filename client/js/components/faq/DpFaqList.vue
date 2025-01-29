<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-loading v-if="isLoading" />
    <dp-tree-list
      v-else
      :tree-data="transformedCategories"
      :branch-identifier="branchFunc()"
      :options="options"
      @tree:change="updateCategorySort">
      <template v-slot:header="">
        <div class="layout--flush">
          <div class="layout__item u-4-of-12">
            {{ Translator.trans('heading') }}
          </div><!--
       --><div class="layout__item u-4-of-12">
            <span v-if="availableGroupOptions.length > 1">
              {{ Translator.trans('visibility') }}
            </span>
          </div><!--
       --><div class="layout__item u-2-of-12 text-center">
            {{ Translator.trans('status') }}
          </div><!--
       --><div class="layout__item u-2-of-12 text-center">
            {{ Translator.trans('edit') }}
          </div>
        </div>
      </template>
      <template v-slot:branch="{ nodeElement, nodeChildren }">
        <dp-faq-category-item
          :faq-category-item="nodeElement"
          :category-children="nodeChildren" />
      </template>
      <template v-slot:leaf="{ nodeElement, parentId }">
        <dp-faq-item
          :available-group-options="availableGroupOptions"
          :faq-item="nodeElement"
          :parent-id="parentId" />
      </template>
    </dp-tree-list>
  </div>
</template>

<script>
import { DpLoading, DpTreeList } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import DpFaqCategoryItem from './DpFaqCategoryItem'
import DpFaqItem from './DpFaqItem'

export default {
  name: 'DpFaqList',

  components: {
    DpFaqCategoryItem,
    DpFaqItem,
    DpLoading,
    DpTreeList
  },

  props: {
    /**
     * Defines which roles are allowed as options to be set for faq visibility.
     */
    roleGroupsFaqVisibility: {
      type: Array,
      required: true
    }
  },

  data () {
    return {
      options: {
        branchesSelectable: false,
        leavesSelectable: false,
        dragLeaves: true
      },
      treeListData: null,
      categories: null,
      isLoading: true
    }
  },

  computed: {
    ...mapState('FaqCategory', {
      faqCategories: 'items'
    }),

    ...mapState('Faq', {
      faqItems: 'items'
    }),

    /**
     * Available options for role groups. "showFor" will be filtered
     * against role_groups_faq_visibility` entries in parameters_default_project.yml.
     */
    availableGroupOptions () {
      return [
        {
          title: Translator.trans('role.fp'),
          id: 'fpVisible',
          showFor: 'GLAUTH'
        },
        {
          title: Translator.trans('institution'),
          id: 'invitableInstitutionVisible',
          showFor: 'GPSORG'
        },
        {
          title: Translator.trans('guest.citizen'),
          id: 'publicVisible',
          showFor: 'GGUEST'
        }
      ].filter(group => this.roleGroupsFaqVisibility.includes(group.showFor))
    },

    transformedCategories () {
      return this.faqItems && this.faqCategories ? this.transformCategoryData(this.faqCategories) : []
    }
  },

  methods: {
    ...mapActions('FaqCategory', {
      categoryList: 'list',
      saveCategory: 'save'
    }),

    ...mapMutations('FaqCategory', {
      updateCategory: 'setItem'
    }),

    transformCategoryData (categories) {
      return Object.values(categories).map(category => {
        let catCpy = JSON.parse(JSON.stringify(category))
        catCpy = category.hasRelationship('faq') ? { ...catCpy, ...{ children: Object.values(category.relationships.faq.list()) } } : catCpy
        return JSON.parse(JSON.stringify(catCpy))
      })
    },

    branchFunc () {
      return function ({ node, id, children }) {
        return node.type === 'FaqCategory'
      }
    },

    updateCategorySort (e) {
      const catCpy = JSON.parse(JSON.stringify(this.faqCategories[e.nodeId]))
      const newSort = e.newOrder.map(item => {
        return {
          id: item.id,
          type: item.type
        }
      })

      catCpy.relationships.faq.data = newSort

      this.updateCategory({ ...catCpy, id: catCpy.id })

      const manualSortParam = 'manualsort=' + newSort.reduce((acc, item, idx) => {
        return idx !== newSort.length - 1 ? acc + item.id + ',' : acc + item.id
      }, '')

      const categoryParam = 'category=custom_category'
      const categoryIdParam = 'categoryId=' + e.nodeId

      const postParams = manualSortParam + '&' + categoryParam + '&' + categoryIdParam

      const xhr = new XMLHttpRequest()
      const url = Routing.generate('DemosPlan_faq_administration_faq')
      xhr.open('POST', url, true)

      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded')
      xhr.setRequestHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
      xhr.setRequestHeader('Upgrade-Insecure-Requests', '1')

      // Display notifications on success or failure of the request
      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status >= 200 && xhr.status < 300) {
          dplan.notify.notify('confirm', Translator.trans('confirm.sort.saved'))
        } else if (xhr.readyState === 4) {
          dplan.notify.error(Translator.trans('error.update.manual.order'))
        }
      }
      xhr.send(postParams)
    }
  },

  mounted () {
    this.categoryList().then(() => {
      this.isLoading = false
    })
  }
}
</script>
