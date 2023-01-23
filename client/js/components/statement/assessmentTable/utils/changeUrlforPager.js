/**
 * Update the Url matching the pager
 * @param pager {Object<count, current_page>}
 *
 * @returns url {Array<base, params>}
 */
/* eslint-disable camelcase */
function changeUrlforPager ({ count, current_page }) {
  const url = window.location.href.split('?')

  if (typeof url[1] !== 'undefined') {
    if (url[1].match('r_limit') !== null) {
      url[1] = url[1].replace(/r_limit=(\d*)($|&)/g, (hit) => `r_limit=${count}&`)
    } else {
      url[1] = `r_limit=${count}&${url[1]}`
    }
    if (url[1].match('page') !== null) {
      url[1] = url[1].replace(/page=(\d*)/g, (hit) => 'page=' + current_page)
    } else {
      url[1] = `page=${current_page}&${url[1]}`
    }
    url[1] = url[1].replace(/&$/g, (hit) => '') // Strip trailing &
  } else {
    url[1] = `r_limit=${count}&page=${current_page}`
  }

  return url
}

export default changeUrlforPager
