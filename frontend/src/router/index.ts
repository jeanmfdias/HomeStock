import { createRouter, createWebHistory } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/products' },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/LoginPage.vue'),
      meta: { public: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/pages/RegisterPage.vue'),
      meta: { public: true },
    },
    { path: '/products', name: 'products', component: () => import('@/pages/ProductsList.vue') },
    {
      path: '/products/new',
      name: 'product-new',
      component: () => import('@/pages/ProductForm.vue'),
    },
    {
      path: '/products/:id/edit',
      name: 'product-edit',
      component: () => import('@/pages/ProductForm.vue'),
      props: (route) => ({ id: Number(route.params.id) }),
    },
    {
      path: '/shopping-list',
      name: 'shopping-list',
      component: () => import('@/pages/ShoppingList.vue'),
    },
    { path: '/expiring', name: 'expiring', component: () => import('@/pages/ExpiringSoon.vue') },
    { path: '/settings', name: 'settings', component: () => import('@/pages/SettingsPage.vue') },
    { path: '/profile', name: 'profile', component: () => import('@/pages/ProfilePage.vue') },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.user === null && !auth.loading) {
    await auth.loadSession()
  }

  if (!to.meta.public && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.public && auth.isAuthenticated) {
    return { name: 'products' }
  }
})

window.addEventListener('homestock:unauthorized', () => {
  const auth = useAuthStore()
  auth.user = null
  if (router.currentRoute.value.name !== 'login') {
    void router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
  }
})

export default router
