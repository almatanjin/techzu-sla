export type Category = {
  id: number
  name: string
  is_active: boolean
}

export type CreateCategoryPayload = {
  name: string
}

export type UpdateCategoryPayload = {
  name?: string
  is_active?: boolean
}

