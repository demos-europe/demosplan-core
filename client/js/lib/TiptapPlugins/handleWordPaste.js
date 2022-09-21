/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { v4 as uuid } from 'uuid'

/**
 * This parser should manage to detect if an clipbaord content comes from microsoft word (mso) or office365 (o365)
 * And them clean and transform the data into something, that tiptap can display nested (un)orderen lists
 *
 * Because Microsoft is quite special the way they store data in their XML, its likely that this functions
 * have to be adjusted to optimize special cases and not tested versions.
 *
 * The main method "handleWordContent" is designed to increase and in the future may handle images or tables as well
 *
 */

/**
 * Try to determin if the snippet from the clipboard comes from MS Office
 *
 * @param snippet{string}
 *
 * @return {boolean}
 */
function checkIfMso (snippet) {
  return (/class="?Mso|style="[^"]*\bmso-|style='[^']*\bmso-|w:WordDocument|office:wo/i).test(snippet)
}

/**
 * Try to determine if the snippet from the clipboard comes from Office365.
 *
 * @param snippet{string}
 *
 * @return {boolean}
 */
function checkIfOffice365 (snippet) {
  return (/class="OutlineElement/).test(snippet)
}

/**
 * Transforms a list item from office365 to a readable internal exchange Object
 *
 * Office365 stores the information for lists on the li items with a bunch of "data" and "data-aria" attributes
 * instead of nesting them.
 * To make the data handleable, we axtract that infos to a "Plain old Javascript Object"
 *
 * @param li{Element}
 *
 * @returns {Object<listId, indent, type, content>}
 */
function createItemFrom365List (li) {
  const indent = parseInt(li.getAttribute('data-aria-level')) // Current level of indentation
  const listId = parseInt(li.getAttribute('data-listid')) // List ID
  const type = li.parentElement.nodeName // Can be 'ul' or 'ol'
  const content = li.innerHTML.replace(/&nbsp;/gmi, ' ')

  return { listId, indent, type, content }
}

/**
 * Transforms a list item from Mso to a readable and manageable object
 *
 * Microsoft Office stores all items in a kind of plain structure, List items too.
 * The information, if an item is a list item is defined by the class name "[class^=MsoList]".
 * Level and List-Id is put in the style attribute.
 * To determin if its an ordered list, we have to check the leading listStyleType.
 *
 * @param li{Element}
 *
 * @returns {Object<listId, indent, type, content, listStyleType>}
 */
function createItemFromMsoList (li) {
  // "listInfo" is expected to be something something like "mso-list: l1 level1 lfo1"
  const listInfo = li.getAttribute('style').match(/mso-list:([^'|^"|^;]*)/i)[0] // Find ListInfo
  const indent = parseInt(listInfo.match(/level\d+/i)[0].slice(5)) // Strip "level" // current level of indentation
  const listId = listInfo.match(/lfo\d+/i)[0] || '' // List ID
  /*
   * To define if its an OL or UL, we first have to create the whole List.
   * Defining the type will be managed in `redefineMsoOrderedLists()` later in the transformation process
   */
  const type = 'ul'
  const listStyleType = li.querySelector('[data-val-supportLists]')?.getAttribute('data-val-supportLists') || ''
  const content = li.innerHTML

  return { listId, indent, type, content, listStyleType }
}

/**
 * Because Ms Word doesn't stores the information about the list type in such a way, that we can easily access it,
 * we have to handle it manually.
 * The approach is to take the structured list and step through each "sublist" (nested list).
 * Within each list we compare the "listStyleTypes" of two elements to find out if they have the same style or not.
 * If so, the list have to be unordered.
 * For lists with just one element an additional check is implemented.
 *
 * @param listItemObjects
 *
 * @return {Object<listItemObjects>}
 */
function redefineMsoOrderedLists (listItemObjects) {
  Object.values(listItemObjects).forEach(list => {
    let sublistId = uuid()
    const sublistIdsAtPosition = [sublistId]
    const sublistIds = [sublistId]
    let pos = 0

    /*
     * Group sublists by uuid.
     * Due to the nesting, we can't know all siblings by just checking the previous, next items
     * To make them groupable, lets add a sublistId to each element.
     */
    listItemObjects[list[0].listId].forEach((li, i) => {
      /*
       * If we "dive" into the next nesting level, we need a new uuid.
       * But if we already have been in this level, but in another sublist,
       * We have to keep the ID from the previous list.
       * Therefore we have to store the IDs in two different ways.
       */
      if (i > 0 && list[i - 1].indent < list[i].indent) {
        sublistId = uuid()
        pos++
        sublistIdsAtPosition[pos] = sublistId
        sublistIds.push(sublistId)
      }

      if (i > 0 && list[i - 1].indent > list[i].indent) {
        pos--
      }

      li.sublistId = sublistIdsAtPosition[pos]
      li.idx = i
    })

    // Check for each sublist (again) if its (un-)ordered
    sublistIds.forEach(sublistId => {
      const sublist = list.filter(el => el.sublistId === sublistId)
      if (sublist.length > 1) {
        const mappedItems = sublist
          .map(el => {
            // We assume, that if two items in one List have the same listStyleType, it is an unordered list
            const type = sublist[0].listStyleType === sublist[1].listStyleType ? 'ul' : 'ol'
            return { ...el, type: type }
          })

        mappedItems.forEach(li => {
          listItemObjects[list[0].listId][li.idx] = li
        })
      }

      /*
       * If we have just one list item we assume that a number or letter indicates an ordred list
       * Only if listStyleType is an "o". Than we assume that its more likely that its an
       * unordered list, than an ordered one because mso uses the "o" a outlined bullit.
       *
       * since the default is 'ul', we just have to change it to 'ol' if neccesary
       */
      if (sublist.length === 1 &&
        (/(\d+|\w+)/i).test(sublist[0].listStyleType) &&
        sublist[0].listStyleType !== 'o') {
        listItemObjects[list[0].listId][sublist[0].idx].type = 'ol'
      }
    })
  })

  return listItemObjects
}

/**
 * Create objects that hold the information about the list items
 * to be used for creating a nested list from that data.
 *
 * @param parsedDom{DOMTokenList}
 * @param isMso{Boolean}
 *
 * @return {Object}
 */
function createLists (parsedDom, isMso) {
  let listItemObjects = {}

  const listItems = isMso
    ? parsedDom.querySelectorAll('[class^=MsoList]')
    : parsedDom.querySelectorAll('li')

  listItems.forEach(li => {
    const item = isMso ? createItemFromMsoList(li) : createItemFrom365List(li)

    if (Object.keys(listItemObjects).includes(item.listId.toString()) === false) {
      listItemObjects[item.listId] = []
    }

    // Set data-attribute as Hook to find it back later on
    li.setAttribute('data-list-indicator', item.listId)

    listItemObjects[item.listId].push(item)
  })

  /*
   * Dirty part to determine if (mso) lists are ordered or not
   */
  if (isMso) {
    listItemObjects = redefineMsoOrderedLists(listItemObjects)
  }

  return listItemObjects
}

/**
 * Takes the flat list of list item objects and creates a HTML-String for that list
 *
 * @param list{Array<Object{indent, type, content, ?listStyleType}>}
 *
 * @return {string}
 */
function buildListAsHtmlString (list) {
  /*
   * `nesting` holds the type of lists for nested lists
   * It starts with the first indentation level and may be filled if there are lists in lists
   */
  const nesting = [list[0].type]
  let htmlList = `<${list[0].type}>`

  for (let i = 0; i < list.length; i++) {
    const item = list[i]
    htmlList += `<li>${item.content}`

    const hasChildren = i + 1 < list.length && list[i + 1].indent > item.indent
    const isLastChild = i + 1 < list.length && list[i + 1].indent < item.indent
    const isLastElement = i + 1 === list.length

    if (hasChildren) {
      // If the next Element is a child-Element the closing Tag follows when closing the Child List
      htmlList += `<${list[i + 1].type}>`
      nesting.unshift(list[i + 1].type)
    } else {
      htmlList += '</li>'
    }

    if (isLastChild) {
      // Close the List and the Parent, for following elements that are intended above
      let c = 0
      while (list[i + 1].indent + c < item.indent) {
        htmlList += `</${nesting[0]}></li>`
        nesting.shift()
        c++
      }
    }
    if (isLastElement) {
      // Close all open Tags if its the last Element
      for (let h = item.indent; h > 1; h--) {
        htmlList += `</${nesting[0]}></li>`
        nesting.shift()
      }
    }
  }

  htmlList += `</${list[0].type}>`

  return htmlList
}

/**
 * Remove code which would make the DomParser fail
 * and store the "bullet" tht it can be checked later on.
 *
 * @param slice{string}
 *
 * @return {string}
 */
function prepareDataBeforeParsingMso (slice) {
  return slice
    // Strip line breaks
    .replace(/(\r|\n)/gmi, '')
    // Strip head
    .replace(/<head>(.|\n|\r)*?<\/head>/mi, '')
    // Strip html wrapper and remove conentless and non html like elements "<o:p>"
    .replace(/<(\/)?(html|o:p)[^>]*>/gmi, '')
    /*
     * Remove Microsoft IF comments and replace it with empty spans holding the data, that it can be processed later on
     * to determine if that one listStyleType may be part of an ordered or unordered list.
     */
    .replace(/<!\[if !(.*?)\]>(.*?)<span(.*?)style='mso-list:Ignore'>(.*?)<!\[endif\]>/gmi, (match, p1, p2, p3, p4) => {
      // Try to remove spacing spans
      const listStyleType = p4
        .replace(/<span[^>]*>/gmi, '')
        .replace(/<\/span>/gmi, '')
        .replace(/(&nbsp;|\s|\\r|\\n|\r|\n)/gmi, '')
      return `<span data-val-${p1}="${listStyleType}" />`
    })
}

/**
 * Parses Clippboard content from Word to fix List ordering.
 *
 * @param slice{String}
 *
 * @return {string}
 */
function handleWordPaste (slice) {
  const isMso = checkIfMso(slice)
  const isOffice365 = checkIfOffice365(slice)

  if ((isMso || isOffice365) === false) {
    return slice
  }

  // Strip meta though its not closed and would break the parser
  slice = slice.replace(/<meta[^>]*>/mi, '')

  // If its Mso-Content: Before the parser can be applied, some noise has to be reduced
  if (isMso) {
    slice = prepareDataBeforeParsingMso(slice)
  }
  // For a better handling all the Stuff gets converted to html
  const parser = new DOMParser()
  const parsedDom = parser.parseFromString(slice, 'text/html')

  const listItemObjects = createLists(parsedDom, isMso)

  /**
   * After collecting the list, we go to each list and add a well formatted list before the found one.
   * Until that we need the old list as dom hook to paste the new one, afterwards it is removed.
   */
  Object.keys(listItemObjects).forEach(listId => {
    // Get the first Element of current List
    const context = parsedDom.querySelector('[data-list-indicator="' + listId + '"]')

    // Instert new List before the original one
    if (context.parentElement.nodeName.toLowerCase() === 'ul' || context.parentElement.nodeName.toLowerCase() === 'ol') {
      context.parentElement.before(buildListAsHtmlString(listItemObjects[listId]))
    } else {
      context.before(buildListAsHtmlString(listItemObjects[listId]))
    }

    // Remove old Elements
    parsedDom
      .querySelectorAll('[data-list-indicator="' + listId + '"]')
      .forEach(el => {
        if (el.parentElement.nodeName.toLowerCase() === 'ul' || el.parentElement.nodeName.toLowerCase() === 'ol') {
          el.parentElement.remove()
        } else {
          el.remove()
        }
      })
  })

  // Clear List item collection for the next run.
  return parsedDom.documentElement.outerHTML
    .replace(/&gt;/gmi, '>')
    .replace(/&lt;/gmi, '<')
    // Remove empty list wrapper
    .replace(/<div[^>]*?class="ListContainerWrapper[^>]*?><ul[^>]*?><\/ul><\/div>/gm, '')
    .replace(/<ul[^>]*?><\/ul>/gm, '')
}

export { handleWordPaste }
