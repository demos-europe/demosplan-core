export {}

declare global {
  interface DplanTranslator {
    trans(key: string, params?: Record<string, string>, domain?: string): string
  }

  interface DplanRouting {
    generate(route: string, params?: Record<string, string | number>): string
  }

  interface DplanGlobal {
    procedureId: string
    notify: (message: string, type?: string) => void
    settings: Record<string, unknown>
    [key: string]: unknown
  }

  const Translator: DplanTranslator
  const Routing: DplanRouting
  const dplan: DplanGlobal

  function hasPermission(permission: string): boolean
  function dpconfirm(message: string): boolean

  const URL_PATH_PREFIX: string
}

/*
 * Declare globals for vue templates (template expressions are type-checked against the component instance,
 * not script scope)
 */
declare module 'vue' {
  interface ComponentCustomProperties {
    Translator: DplanTranslator
    Routing: DplanRouting
    dplan: DplanGlobal
    hasPermission(permission: string): boolean
  }

  // Tests alias vue to @vue/compat, which ships no declarations of its own
  export function configureCompat(config: Record<string, boolean | 'suppress-warning'>): void
}
