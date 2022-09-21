module.exports = {
  content: ['./components/**/*.{js,vue}', './directives/**/*.js'],
  corePlugins: {
    preflight: false // Enable later when removing demosplan base styles. See https://tailwindcss.com/docs/preflight
  },
  plugins: [],
  safelist: [
    {
      pattern: /./// Disable purging https://github.com/tailwindlabs/tailwindcss/discussions/6557#discussioncomment-1838214
    }
  ],
  theme: {
    extend: {}
  }
}
