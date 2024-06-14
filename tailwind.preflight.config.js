/**
 * This Tailwind config is used by ./client/css/preflight.css.
 * It is configured to only output the "css reset" of Tailwind.
 */
const config = {
  corePlugins: {
    preflight: true,
  },
  plugins: [],
}
module.exports = config
