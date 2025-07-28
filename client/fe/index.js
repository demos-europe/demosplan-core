/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const { Command } = require('commander')
const runWebpack = require('./webpack/runWebpack')
const FE = new Command()

// eslint-disable-next-line new-cap
FE.storeOptionsAsProperties(true)
  .version('always-never-done')
  .description('demosplan frontend')
  .option('--silent', 'Silence output')

// Define the build command
FE.command('build <project>')
  .option('-P, --prod', 'Build in prod mode')
  .option('--stats', 'Output webpack build statistics (does not work with --analyze)')
  .option('--json <filename>', 'Export build statistics / bundle analysis to json')
  .option('-A, --analyze', 'Run the bundle analyzer (does not work with --stats)')
  .option('-S, --only-styles', 'Only build the styles')
  .option('--theme <theme>', 'Theme to use for the build', 'tailwind')
  .action(runWebpack('build'))

// Define the watch command
FE.command('watch <project>')
  .action(runWebpack('watch'))
  .option('--theme <theme>', 'Theme to use for the build', 'tailwind')

FE.parse(process.argv)

module.exports = FE
