/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
const features = []
const hasPermission = jest.fn(feature => !!features[feature])

jest.mock('@uppy/core', () => () => 'mock result')
jest.mock('@uppy/drag-drop', () => () => 'mock result')
jest.mock('@uppy/progress-bar', () => () => 'mock result')
jest.mock('@uppy/tus', () => () => 'mock result')

const Translator = {
  trans: jest.fn(key => key)
}

global.Translator = Translator
global.hasPermission = hasPermission

global.PROJECT = 'blp'
