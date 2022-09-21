/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Props that are shared between multiple Vue form components.
 *
 * The prop `maxlength` is implemented as a factory function with no params - actually it could also be
 * a simple object. However, since we tend to copy and paste, and to path a way for future expansion
 * of this pattern, it should be mentioned that crafting dynamic prop definitions is possible like so:
 *
 * ```
 *    // In this file...
 *    const myProp = (param = defaultValue) => {
 *      // ...define prop
 *    }
 *    // In the component...
 *    import { myProp } from 'demosplan-ui/shared/props'
 *    props: {
 *      myProp: myProp('otherValue'),
 *    }
 * ```
 */

/**
 * Can be used e.g for maxLength to Limit the maximum allowed number of characters to the given amount.
 * If set, it renders a hint with a counter of the characters yet available.
 *
 * The type may be Boolean just to allow for the falsy default (making it possible to pass it down
 * to the actual textarea element without further transformation), or String (but containing an integer),
 * to not be forced to transform it to int in every template with `:maxlength="'<Number>'"`.
 */
const length = () => {
  return {
    type: [Boolean, String],
    required: false,
    default: false,
    validator: (string) => {
      /*
       * The `string !== true` check actually tests for the empty string being passed
       * to the property, which internally is converted to the boolean "true". That way,
       * using the `maxlength` attr without assigning a value to it throws an error.
       * On the other hand it should not be possible to pass values that can't be
       * converted into a whole number.
       */
      return string !== true && Number(string) % 1 === 0
    }
  }
}

/**
 * Allows passing of additional attributes into the inner form element. Only chosen elements
 * are allowed for each element type, failing if at least one attribute is not allowed within the array.
 *
 * Attributes are specified like `:attributes="['attrName1=attrValue1', 'attrName2=attrValue2']"`.
 *
 * @param {String} element  Type of element
 */
const attributes = element => {
  const allowed = {
    textarea: ['cols', 'rows']
  }
  return {
    type: Array,
    required: false,
    default: () => [],
    validator: applied => {
      const allowedApplied = applied.filter(attr => {
        const attrArr = attr.split('=')
        return allowed[element].includes(attrArr[0]) && attrArr.length === 2
      })
      if (applied.length === 0 || allowedApplied.length === applied.length) {
        return true
      } else {
        console.error(`A Vue form component of type "${element}" is used with the "attributes" prop containing a disallowed attr.`)
      }
    }
  }
}

export { attributes, length }
