export type UnitType = 'unit' | 'g' | 'kg' | 'ml' | 'l'
export type MovementReason = 'purchase' | 'consume' | 'discard' | 'adjust'

export interface User {
  id: number
  email: string
  name: string
  roles: string[]
}

export interface Category {
  id: number
  name: string
  slug: string
  requiresExpiration: boolean
}

export interface ReferenceItem {
  id: number
  name: string
}

export interface Product {
  id: number
  name: string
  brand: string | null
  category: Category
  storageLocation: ReferenceItem | null
  preferredStore: ReferenceItem | null
  unitType: UnitType
  quantity: string
  minStock: string
  expirationDate: string | null
  notes: string | null
  belowMinStock: boolean
  createdAt: string
  updatedAt: string
}

export interface ProductPayload {
  name: string
  brand?: string | null
  categoryId: number
  storageLocationId?: number | null
  preferredStoreId?: number | null
  unitType: UnitType
  quantity: string
  minStock: string
  expirationDate?: string | null
  notes?: string | null
}

export interface ReportRow {
  id: number
  name: string
  brand: string | null
  quantity: string
  minStock: string
  unitType: UnitType
  expirationDate: string | null
  category: string
  preferredStore: string | null
}

export interface ShoppingListResponse {
  items: ReportRow[]
}

export interface ExpiringResponse {
  days: number
  items: ReportRow[]
}

export interface ApiErrorBody {
  error?: string
  fields?: Record<string, string>
}
