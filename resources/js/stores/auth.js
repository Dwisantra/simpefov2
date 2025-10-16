import axios from 'axios'
import { defineStore } from 'pinia'
// import axios from '@/lib/axios'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token') || null,
    }),
    actions: {
        async login(username, password) {
            const { data } = await axios.post('/api/login', { username, password })
            this.token = data.token
            this.user = data.user
            axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
            localStorage.setItem('token', data.token)
            localStorage.setItem('user', JSON.stringify(data.user))
        },
        loadUserFromStorage() {
            const token = localStorage.getItem('token')
            const user = localStorage.getItem('user')
            if (token && user) {
                this.token = token
                this.user = JSON.parse(user)
                axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
            }
        },
        async fetchUser() {
            if (this.token) {
                axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
                try {
                    const res = await axios.get('/api/me')
                    this.user = res.data
                    localStorage.setItem('user', JSON.stringify(res.data))
                } catch (err) {
                    this.user = null
                    localStorage.removeItem('user')
                }
            }
        },
        logout() {
            this.token = null
            this.user = null
            localStorage.removeItem('token')
            localStorage.removeItem('user')
            delete axios.defaults.headers.common['Authorization']
        },
        async updateKodeSign(kode) {
            const { data } = await axios.post('/api/update-kode-sign', { kode_sign: kode })
            this.user = data.user
            localStorage.setItem('user', JSON.stringify(data.user))
        }
    },
    persist: true
})
