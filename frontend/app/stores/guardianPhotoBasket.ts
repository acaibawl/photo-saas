import { defineStore } from 'pinia'

export type GuardianPhotoBasketItem = {
  photo_id: string
  price: number
}

export const useGuardianPhotoBasketStore = defineStore('guardian-photo-basket', {
  state: () => ({
    selectedPhotosById: {} as Record<string, GuardianPhotoBasketItem>,
  }),

  getters: {
    selectedPhotos: (state): GuardianPhotoBasketItem[] => Object.values(state.selectedPhotosById),
    selectedPhotoCount: (state): number => Object.keys(state.selectedPhotosById).length,
    selectedTotalAmount: (state): number => Object.values(state.selectedPhotosById).reduce(
      (totalAmount, photo) => totalAmount + photo.price,
      0,
    ),
  },

  actions: {
    isSelected(photoId: string): boolean {
      return this.selectedPhotosById[photoId] !== undefined
    },

    toggle(photo: GuardianPhotoBasketItem): void {
      if (this.selectedPhotosById[photo.photo_id]) {
        this.removePhotoIds([photo.photo_id])

        return
      }

      this.selectedPhotosById = {
        ...this.selectedPhotosById,
        [photo.photo_id]: photo,
      }
    },

    removePhotoIds(photoIds: string[]): void {
      const removedPhotoIds = new Set(photoIds)
      const selected = Object.fromEntries(
        Object.entries(this.selectedPhotosById).filter(([photoId]) => !removedPhotoIds.has(photoId)),
      )

      if (Object.keys(selected).length !== Object.keys(this.selectedPhotosById).length) {
        this.selectedPhotosById = selected
      }
    },

    clear(): void {
      this.selectedPhotosById = {}
    },
  },
})