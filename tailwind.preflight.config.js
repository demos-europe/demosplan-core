/**
 * This Tailwind config is used by ./client/css/preflight.css.
 * It is configured to only output the "css reset" of Tailwind.
 */
const config = {
  content: ['./suppress-no-content-configured-warning-in-console/'],
  corePlugins: {
    preflight: true,
  },
  plugins: [],
}
module.exports = config
