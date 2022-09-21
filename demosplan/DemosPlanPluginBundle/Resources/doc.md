# DemosPlanPluginBundle - Manage things related to demosplan Plugins

## Commands

`dplan:plugin:new <name>`: Generates a new plugin using the stub as template
`dplan:plugin:list [--active] [--default-disabled] [--porcelain]`: Emit a list of plugins, optionally in script readable mode

## Variables in Stub Plugin

| name | example | notes |
| ---- | ------- | ----- |
| plugin_name | stub | base plugin name, as gathered from `php app/console dplan:plugin:new <name>` |
| plugin_config_name | stub_plugin | |
| plugin_vendor | demosplan | |
| plugin_description | | A short plugin description |
| plugin_license | MIT | |
| plugin_class_name | ExamplePlugin | |
| plugin_namespace | demosplan\plugin | |
