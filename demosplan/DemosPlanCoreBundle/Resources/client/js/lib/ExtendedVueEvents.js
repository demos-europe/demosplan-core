/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Prefixes name of Vue `$emit`
 *
 * @param events
 * @param args
 */
export const extendedEmit = function (events, ...args) {
  events = events.search(/\s/) === -1 ? [events] : events.split(' ')
  let i = 0; const l = events.length

  for (; i < l; i++) {
    this.$emit.apply(this, ['dp:' + events[i], this].concat(args))
  }
}

/**
 * Prefixes name of Vue `$on`
 *
 * @param events
 * @param cb
 */
export const extendedOn = function (events, cb) {
  events = events.search(/\s/) === -1 ? [events] : events.split(' ')
  let i = 0; const l = events.length

  for (; i < l; i++) {
    this.$on('dp:' + events[i], cb)
  }
}
