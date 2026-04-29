import type {
  Category,
  ExpiringResponse,
  MovementReason,
  Product,
  ProductPayload,
  ReferenceItem,
  ShoppingListResponse,
  User,
} from './types'

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly body: unknown,
  ) {
    super(`API request failed with status ${status}`)
  }
}

type RequestOptions = Omit<RequestInit, 'body'> & {
  body?: unknown
  skipUnauthorizedRedirect?: boolean
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')

  if (options.body !== undefined) {
    headers.set('Content-Type', 'application/json')
  }

  const response = await fetch(path, {
    ...options,
    credentials: 'same-origin',
    headers,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
  })

  if (response.status === 204) {
    return undefined as T
  }

  const contentType = response.headers.get('content-type') ?? ''
  const body = contentType.includes('application/json')
    ? await response.json()
    : await response.text()

  if (!response.ok) {
    if (response.status === 401 && !options.skipUnauthorizedRedirect) {
      window.dispatchEvent(new CustomEvent('homestock:unauthorized'))
    }
    throw new ApiError(response.status, body)
  }

  return body as T
}

export const api = {
  me: () => request<User>('/api/auth/me', { skipUnauthorizedRedirect: true }),
  login: (email: string, password: string) =>
    request<User>('/api/auth/login', { method: 'POST', body: { email, password } }),
  register: (name: string, email: string, password: string) =>
    request<User>('/api/auth/register', { method: 'POST', body: { name, email, password } }),
  logout: () =>
    request<void>('/api/auth/logout', { method: 'POST', skipUnauthorizedRedirect: true }),

  products: (filters: URLSearchParams = new URLSearchParams()) => {
    const query = filters.toString()
    return request<Product[]>(`/api/products${query ? `?${query}` : ''}`)
  },
  product: (id: number) => request<Product>(`/api/products/${id}`),
  createProduct: (payload: ProductPayload) =>
    request<Product>('/api/products', { method: 'POST', body: payload }),
  updateProduct: (id: number, payload: ProductPayload) =>
    request<Product>(`/api/products/${id}`, { method: 'PATCH', body: payload }),
  deleteProduct: (id: number) => request<void>(`/api/products/${id}`, { method: 'DELETE' }),
  addMovement: (id: number, delta: string, reason: MovementReason) =>
    request<Product>(`/api/products/${id}/movements`, {
      method: 'POST',
      body: { delta, reason },
    }),

  categories: () => request<Category[]>('/api/categories'),
  createCategory: (name: string, requiresExpiration: boolean) =>
    request<Category>('/api/categories', { method: 'POST', body: { name, requiresExpiration } }),
  storageLocations: () => request<ReferenceItem[]>('/api/storage-locations'),
  createStorageLocation: (name: string) =>
    request<ReferenceItem>('/api/storage-locations', { method: 'POST', body: { name } }),
  stores: () => request<ReferenceItem[]>('/api/stores'),
  createStore: (name: string) =>
    request<ReferenceItem>('/api/stores', { method: 'POST', body: { name } }),

  shoppingList: () => request<ShoppingListResponse>('/api/reports/shopping-list'),
  expiring: (days = 7) => request<ExpiringResponse>(`/api/reports/expiring?days=${days}`),
}
