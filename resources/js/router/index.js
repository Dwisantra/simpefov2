import { createRouter, createWebHistory } from 'vue-router'
import Login from '@/pages/Auth/Login.vue'
import Register from '@/pages/Auth/Register.vue'
import FeatureRequestIndex from '@/pages/FeatureRequest/Index.vue'
import FeatureRequestCreate from '@/pages/FeatureRequest/Create.vue'
import FeatureRequestDetail from '@/pages/FeatureRequest/Detail.vue'
import AdminMaster from '@/pages/Admin/Master.vue'
import { useAuthStore } from '@/stores/auth'
import { ROLE } from '@/constants/roles'

const routes = [
    { path: '/', redirect: '/login' },

    { path: '/login', name: 'login', component: Login, meta: { requiresGuest: true } },
    { path: '/register', name: 'register', component: Register, meta: { requiresGuest: true } },
    {
        path: '/feature-request',
        name: 'feature-request.index',
        component: FeatureRequestIndex,
        meta: { requiresAuth: true }
    },
    {
        path: '/feature-request/create',
        name: 'feature-request.create',
        component: FeatureRequestCreate,
        meta: { requiresAuth: true, requiresRole: ROLE.USER }
    },
    {
        path: '/feature-request/:id',
        name: 'feature-request.detail',
        component: FeatureRequestDetail,
        meta: { requiresAuth: true }
    },
    {
        path: '/admin/master',
        name: 'admin.master',
        component: AdminMaster,
        meta: { requiresAuth: true, requiresRole: ROLE.ADMIN }
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Protect route
router.beforeEach(async (to, from, next) => {
    const auth = useAuthStore()

    if (!auth.user && auth.token) {
        await auth.fetchUser()
    }

    if (to.meta.requiresAuth && !auth.user) {
        return next('/login')
    }

    if (to.meta.requiresRole && auth.user?.level !== to.meta.requiresRole) {
        return next('/feature-request')
    }

    if (to.meta.requiresGuest && auth.user) {
        return next('/feature-request')
    }

    next()
})

export default router
