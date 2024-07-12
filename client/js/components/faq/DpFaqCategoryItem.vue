<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="layout--flush">
    <div class="layout__item u-10-of-12 weight--bold o-hellip">
      {{ faqCategoryItem.attributes.title }}
    </div><!--
 --><div class="layout__item u-2-of-12 text-center">
      <a
        class="btn--blank o-link--default u-mh-0_25"
        :href="Routing.generate('DemosPlan_faq_administration_category_edit', {categoryId: this.faqCategoryItem.id})"
        :aria-label="Translator.trans('item.edit')"
        data-cy="editCategoryItem">
        <i
          class="fa fa-pencil"
          aria-hidden="true" />
      </a>
      <a
        v-if="categoryChildren.length === 0"
        class="btn--blank o-link--default u-mh-0_25 u-pr"
        :href="Routing.generate('DemosPlan_faq_administration_category_delete', {categoryId: this.faqCategoryItem.id})"
        :aria-label="Translator.trans('item.delete')"
        data-cy="deleteCategoryItem">
        <i
          class="fa fa-trash"
          aria-hidden="true" />
      </a>
    </div>
  </div>
</template>

<script>

export default {
  name: 'DpFaqCategoryItem',

  components: {},

  props: {
    faqCategoryItem: {
      type: Object,
      required: true
    },

    categoryChildren: {
      type: Array,
      required: true
    }
  },

  methods: {
    deleteCategoryItem () {}
  }
}
</script>
