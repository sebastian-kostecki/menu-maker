// Types for MealPlan components and API responses
export type MealPlanStatus = 'pending' | 'processing' | 'done' | 'error'

export interface StatusOption {
  value: MealPlanStatus
  label: string
}

export interface MealPlanListItem {
  id: number
  start_date: string // 'YYYY-MM-DD'
  end_date: string   // 'YYYY-MM-DD'
  status: MealPlanStatus
  created_at: string
  updated_at: string
  meals_count: number
  logs_count: number
  links: {
    self: string
  }
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface MealPlanCollection {
  data: MealPlanListItem[]
  meta: PaginationMeta
  links: PaginationLinks
}

export type SortDirection = 'asc' | 'desc'

export interface FiltersState {
  status?: MealPlanStatus | ''
  sort?: 'start_date' | 'end_date' | 'status' | 'created_at'
  direction?: SortDirection
  perPage?: number
}

export interface MealPlanCollectionProps {
  mealPlans: MealPlanCollection
  filters: {
    'filter.status'?: MealPlanStatus
    sort?: FiltersState['sort']
    direction?: SortDirection
    perPage?: number
  }
  statuses: StatusOption[]
}
