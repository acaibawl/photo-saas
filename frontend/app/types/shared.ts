export type Child = {
  id: string
  name: string
  class_name: string
}

export type Album = {
  id: string
  title: string
  event_date: string
}

export type SelectOption = {
  value: string
  label: string
}

export type Photo = {
  photo_id: string
  album_id: string | null
  price: number | null
  is_sellable: boolean
  preview_status: 'queued' | 'ready' | 'failed'
  preview_url: string | null
  created_at: string | null
  tagged_child_ids: string[]
}

export type PhotoDetail = Photo & {
  album_title: string | null
  original_url: string | null
  tagged_children: Array<{
    child_id: string
    name: string
    class_name: string
  }>
}

export type PageResponse<T> = {
  data: T[]
  meta: {
    current_page: number
    per_page: number
    total: number
  }
}

export type BatchStatus = {
  batch_id: string
  status: string
  accepted_count: number
  total_files: number
}

export type OptionsResponse = {
  data: SelectOption[]
}
