/**
 * The Tailwind config uses the pre-configured config within
 * demosplan-ui, which is tweaked with demosplan-ui design tokens.
 * Tailwind is included as a PostCss plugin in the webpack config.
 * The Tailwind entry point is ./client/css/index.css.
 */
const config = {
  ...require('@demos-europe/demosplan-ui/tailwind.config'),
  content: [
    './client/js/**/!(generated|legacy|store)/*.{js,vue}',
    './demosplan/plugins/**/Resources/**/*.twig',
    './node_modules/@demos-europe/demosplan-ui/dist/*.js',
    './projects/**/templates/**/*.twig',
    './templates/bundles/DemosPlanCoreBundle/**/*.twig',
    './addons/vendor/demos-europe/demosplan-addon-*/client/**/*.{js,vue}'
  ],
  corePlugins: {
    preflight: false,
  },
  important: false
}

module.exports = config
