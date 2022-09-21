<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { prefixClass } from 'demosplan-ui/lib'
import SlidingPagination from 'vue-sliding-pagination'

export default {
  name: 'DpSlidingPagination',
  functional: true,
  methods: {
    prefixClass (classList) {
      return prefixClass(classList)
    }
  },
  render (createElement, context) {
    const current = context.data.attrs.current
    const total = context.data.attrs.total
    const customizedProperties = {
      ariaPaginationLabel: Translator.trans('pager.label'),
      ariaGotoPageLabel: Translator.trans('pager.goto.page.from.pages', { page: current, total: total }),
      ariaPreviousPageLabel: Translator.trans('pager.previous'),
      ariaNextPageLabel: Translator.trans('pager.next'),
      ariaCurrentPageLabel: Translator.trans('pager.page.from.pages.current.page', { page: current, total: total }),
      classMap: {
        componentClass: prefixClass('c-sliding-pagination'),
        list: prefixClass('c-sliding-pagination__list'),
        element: prefixClass('c-sliding-pagination__list-element'),
        elementDisabled: prefixClass('c-sliding-pagination__list-element--disabled'),
        elementActive: prefixClass('c-sliding-pagination__list-element--active'),
        page: prefixClass('c-sliding-pagination__page')
      }
    }
    context.data.attrs = { ...context.data.attrs, ...customizedProperties }

    return createElement(SlidingPagination, context.data, context.children)
  }
}
</script>
