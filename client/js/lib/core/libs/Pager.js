/**
 *  DPPager
 *  Helper script to submit pager select form on change
 *
 *  Markup is found in vendor\demosplan\DemosPlanCoreBundle\Resources\views\macros.html.twig
 *  Usage of Pager in Template via `{{ partial.pager( templateVars|default() ) }}`
 *  Limits are set in backend on DemosPlanPaginator Object
 *
 * @deprecated use DpPager.vue instead
 */
export default function Pager () {
  // Auto submit closest form on select
  const pagerSelectEl = $('[data-pager-select]')
  pagerSelectEl.on('change', function () {
    //  Set all instances of r_limit to val of triggering r_limit instance
    if ($(this).attr('name') === 'r_limit') {
      pagerSelectEl.val($(this).val())
    }

    $(this).closest('form').submit()
  })

  /*
   *  Add aria-current based on class name
   *  see http://www.a11ymatters.com/pattern/pagination/
   */
  const pagerNavEl = $('[data-pager-nav]')
  pagerNavEl.find('.c-pager__page-item').each(function (index, item) {
    item = $(item)
    if (item.hasClass('current')) {
      item.attr('aria-label', Translator.trans('pager.current.page', {}))
      item.attr('aria-current', 'page')
    } else {
      item.find('a').attr('aria-label', Translator.trans('pager.goto.page', {}) + ' ' + item.text())
    }
  })
}
