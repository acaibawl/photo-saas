export type GuardianOrderStatus = 'pending' | 'paid' | 'failed' | 'refunded'

export type GuardianOrderItem = {
  order_item_id: string
  photo_id: string
  price: number
}

export type GuardianOrder = {
  order_id: string
  status: GuardianOrderStatus
  total_amount: number
  created_at: string | null
  items: GuardianOrderItem[]
}

export type GuardianOrderPageResponse = {
  data: GuardianOrder[]
  meta: {
    current_page: number
    total: number
  }
}

export type GuardianPurchasedPhoto = {
  photo_id: string
  album_id: string | null
  downloadable: boolean
  purchased_at: string | null
  event_date: string | null
  preview_url: string | null
}

export type GuardianPurchasedPhotoPageResponse = {
  data: GuardianPurchasedPhoto[]
  meta: {
    current_page: number
    total: number
  }
}
