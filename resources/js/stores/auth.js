import axios from 'axios'
import { defineStore } from 'pinia'
import { startIdleTimer, stopIdleTimer, getIdleTimeoutMinutes } from '@/lib/idleTimeout'

const TOKEN_KEY = 'token'
const USER_KEY = 'user'
const SESSION_EXPIRY_KEY = 'sessionExpiresAt'

const setAuthorizationHeader = (token = null) => {
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
        return
    }

    delete axios.defaults.headers.common['Authorization']
}

const buildTimeoutMessage = (minutes = null) => {
    if (Number.isFinite(minutes) && minutes > 0) {
        const normalized = Math.round(minutes)
        return `Sesi telah berakhir setelah ${normalized} menit. Silakan masuk kembali.`
    }

    return 'Sesi telah berakhir. Silakan masuk kembali.'
}

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem(TOKEN_KEY) || null,
        logoutMessage: null,
        sessionTimeoutMinutes: null,
        sessionExpiresAt: null,
    }),
    actions: {
        updateSessionExpiry(timestamp = null) {
            if (timestamp) {
                this.sessionExpiresAt = timestamp
                localStorage.setItem(SESSION_EXPIRY_KEY, String(timestamp))
                return
            }

            this.sessionExpiresAt = null
            localStorage.removeItem(SESSION_EXPIRY_KEY)
        },
        initializeSessionTimer() {
            stopIdleTimer()

            const minutes = getIdleTimeoutMinutes()

            if (!Number.isFinite(minutes) || minutes <= 0) {
                this.sessionTimeoutMinutes = null
                this.updateSessionExpiry(null)
                return
            }

            this.sessionTimeoutMinutes = minutes

            const duration = minutes * 60 * 1000
            this.updateSessionExpiry(Date.now() + duration)

            startIdleTimer(() => {
                this.handleSessionTimeout()
            }, {
                initialDurationMs: duration,
                onSchedule: (nextTimestamp) => {
                    this.updateSessionExpiry(nextTimestamp)
                }
            })
        },
        ensureSessionTimer() {
            if (!Number.isFinite(this.sessionExpiresAt)) {
                this.initializeSessionTimer()
                return
            }

            const remaining = this.sessionExpiresAt - Date.now()

            if (remaining <= 0) {
                this.handleSessionTimeout()
                return
            }

            stopIdleTimer()

            const minutes = getIdleTimeoutMinutes()

            if (!Number.isFinite(minutes) || minutes <= 0) {
                this.sessionTimeoutMinutes = null
                this.updateSessionExpiry(null)
                this.handleSessionTimeout()
                return
            }

            this.sessionTimeoutMinutes = minutes

            startIdleTimer(() => {
                this.handleSessionTimeout()
            }, {
                initialDurationMs: remaining,
                onSchedule: (nextTimestamp) => {
                    this.updateSessionExpiry(nextTimestamp)
                }
            })
        },
        async login(username, password) {
            const { data } = await axios.post('/api/login', { username, password })

            this.token = data.token
            this.user = data.user
            this.logoutMessage = null

            setAuthorizationHeader(data.token)
            localStorage.setItem(TOKEN_KEY, data.token)
            localStorage.setItem(USER_KEY, JSON.stringify(data.user))

            this.initializeSessionTimer()
        },
        loadUserFromStorage() {
            const token = localStorage.getItem(TOKEN_KEY)
            const user = localStorage.getItem(USER_KEY)
            const expiresAt = Number(localStorage.getItem(SESSION_EXPIRY_KEY))

            if (token && user) {
                let parsedUser = null

                try {
                    parsedUser = JSON.parse(user)
                } catch (error) {
                    this.logout()
                    return
                }

                if (Number.isFinite(expiresAt)) {
                    if (expiresAt <= Date.now()) {
                        this.logoutMessage = buildTimeoutMessage(getIdleTimeoutMinutes())
                        this.logout({ preserveMessage: true })
                        return
                    }

                    this.sessionExpiresAt = expiresAt
                } else {
                    this.sessionExpiresAt = null
                }

                this.token = token
                this.user = parsedUser
                setAuthorizationHeader(token)

                if (Number.isFinite(expiresAt)) {
                    this.ensureSessionTimer()
                } else {
                    this.initializeSessionTimer()
                }
            }
        },
        async fetchUser() {
            if (!this.token) {
                return
            }

            setAuthorizationHeader(this.token)

            try {
                const res = await axios.get('/api/me')
                this.user = res.data
                localStorage.setItem(USER_KEY, JSON.stringify(res.data))
                this.ensureSessionTimer()
            } catch (err) {
                this.handleUnauthorized()
            }
        },
        logout(options = {}) {
            const { preserveMessage = false } = options

            stopIdleTimer()

            this.sessionTimeoutMinutes = null
            this.token = null
            this.user = null
            this.updateSessionExpiry(null)

            localStorage.removeItem(TOKEN_KEY)
            localStorage.removeItem(USER_KEY)

            setAuthorizationHeader(null)

            if (!preserveMessage) {
                this.logoutMessage = null
            }
        },
        async handleSessionTimeout(options = {}) {
            const { skipRemote = false } = options
            const minutes = this.sessionTimeoutMinutes ?? getIdleTimeoutMinutes()
            this.logoutMessage = buildTimeoutMessage(minutes)

            if (!skipRemote) {
                try {
                    await axios.post('/api/logout')
                } catch (error) {
                    // Abaikan kesalahan saat memutus sesi yang sudah tidak valid
                }
            }

            this.logout({ preserveMessage: true })
        },
        handleUnauthorized(customMessage = null) {
            const minutes = this.sessionTimeoutMinutes ?? getIdleTimeoutMinutes()
            this.logoutMessage = customMessage ?? buildTimeoutMessage(minutes)
            this.logout({ preserveMessage: true })
        },
        clearLogoutMessage() {
            this.logoutMessage = null
        },
        async updateKodeSign(kode) {
            const { data } = await axios.post('/api/update-kode-sign', { kode_sign: kode })
            this.user = data.user
            localStorage.setItem(USER_KEY, JSON.stringify(data.user))
            this.ensureSessionTimer()
        }
    },
    persist: {
        paths: ['user', 'token', 'logoutMessage', 'sessionExpiresAt']
    }
})
