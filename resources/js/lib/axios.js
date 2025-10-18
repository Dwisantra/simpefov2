import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

const instance = axios.create({
    baseURL: '/api'
})

instance.interceptors.request.use(config => {
    const auth = useAuthStore()

    if (Number.isFinite(auth.sessionExpiresAt) && auth.sessionExpiresAt <= Date.now()) {
        void auth.handleSessionTimeout({ skipRemote: true })
        return Promise.reject(new Error('Session expired'))
    }

    if (auth.token) {
        config.headers.Authorization = `Bearer ${auth.token}`
    }
    return config
})

instance.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error?.response?.status === 401) {
            const auth = useAuthStore()
            const message = error?.response?.data?.message
            auth.handleUnauthorized(message)
        }

        return Promise.reject(error)
    }
)

export default instance
