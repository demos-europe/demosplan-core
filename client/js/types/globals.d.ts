declare const Translator: {
  trans(key: string, params?: Record<string, string>, domain?: string): string
}

declare const Routing: {
  generate(route: string, params?: Record<string, string | number>): string
}

declare function hasPermission(permission: string): boolean
declare function dpconfirm(message: string): boolean

// Intentionally loose for now — tighten as first real TS adoption task
declare const dplan: {
  procedureId: string
  notify: (message: string, type?: string) => void
  settings: Record<string, unknown>
  [key: string]: unknown
}

declare const URL_PATH_PREFIX: string
