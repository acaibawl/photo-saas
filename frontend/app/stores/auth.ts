import { defineStore } from 'pinia'

export type StaffUser = {
  id: number
  kindergarten_id: number
  name: string
  email: string
  role: 'owner' | 'staff'
}

export type GuardianUser = {
  id: string
  name: string
  email: string
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    staffAccessToken: null as string | null,
    guardianAccessToken: null as string | null,
    staffUser: null as StaffUser | null,
    guardianUser: null as GuardianUser | null,
    staffSessionRestored: false,
    guardianSessionRestored: false,
  }),

  getters: {
    isStaffAuthenticated: (state) => Boolean(state.staffAccessToken),
    isGuardianAuthenticated: (state) => Boolean(state.guardianAccessToken),
  },

  actions: {
    setStaffAccessToken(token: string): void {
      this.staffAccessToken = token
    },

    setGuardianAccessToken(token: string): void {
      this.guardianAccessToken = token
    },

    setStaffUser(user: StaffUser): void {
      this.staffUser = user
    },

    setGuardianUser(user: GuardianUser): void {
      this.guardianUser = user
    },

    clearStaffSession(): void {
      this.staffAccessToken = null
      this.staffUser = null
    },

    clearGuardianSession(): void {
      this.guardianAccessToken = null
      this.guardianUser = null
    },

    markStaffSessionRestored(): void {
      this.staffSessionRestored = true
    },

    markGuardianSessionRestored(): void {
      this.guardianSessionRestored = true
    },
  },
})
