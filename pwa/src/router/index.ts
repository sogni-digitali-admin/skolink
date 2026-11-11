import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { Role } from '@/services/types'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { public: true },
  },
  {
    path: '/p',
    component: () => import('@/views/parent/ParentLayout.vue'),
    meta: { role: 'parent' as Role },
    children: [
      { path: '', redirect: '/p/home' },
      { path: 'home', name: 'parent-home', component: () => import('@/views/parent/ChildrenView.vue') },
      { path: 'attendance', name: 'parent-attendance', component: () => import('@/views/parent/AttendanceView.vue') },
      { path: 'homework', name: 'parent-homework', component: () => import('@/views/parent/HomeworkView.vue') },
      { path: 'results', name: 'parent-results', component: () => import('@/views/parent/ResultsView.vue') },
      { path: 'fees', name: 'parent-fees', component: () => import('@/views/parent/FeesView.vue') },
      { path: 'notices', name: 'parent-notices', component: () => import('@/views/parent/NoticesView.vue') },
      { path: 'leave', name: 'parent-leave', component: () => import('@/views/parent/LeaveView.vue') },
      { path: 'profile', name: 'parent-profile', component: () => import('@/views/parent/ProfileView.vue') },
    ],
  },
  {
    path: '/t',
    component: () => import('@/views/teacher/TeacherLayout.vue'),
    meta: { role: 'teacher' as Role },
    children: [
      { path: '', redirect: '/t/home' },
      { path: 'home', name: 'teacher-home', component: () => import('@/views/teacher/ClassesView.vue') },
      { path: 'attendance', name: 'teacher-attendance', component: () => import('@/views/teacher/AttendanceSheetView.vue') },
      { path: 'homework', name: 'teacher-homework', component: () => import('@/views/teacher/HomeworkListView.vue') },
      { path: 'homework/assign', name: 'teacher-homework-assign', component: () => import('@/views/teacher/HomeworkAssignView.vue') },
      { path: 'homework/:id/grade', name: 'teacher-homework-grade', component: () => import('@/views/teacher/HomeworkGradeView.vue') },
      { path: 'notices', name: 'teacher-notices', component: () => import('@/views/teacher/NoticeComposerView.vue') },
      { path: 'profile', name: 'teacher-profile', component: () => import('@/views/teacher/TeacherProfileView.vue') },
    ],
  },
  { path: '/', redirect: '/login' },
  { path: '/:pathMatch(.*)*', redirect: '/login' },
]

export const router = createRouter({
  history: createWebHistory('/'),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.public) {
    // Already signed in? Go straight to the role home.
    if (to.name === 'login' && auth.isAuthenticated) return auth.homeRoute
    return true
  }
  if (!auth.isAuthenticated) return { name: 'login' }
  const requiredRole = to.meta.role as Role | undefined
  if (requiredRole && auth.role !== requiredRole) {
    // Role guard: parents stay in /p, teachers in /t. No student role exists.
    return auth.homeRoute
  }
  return true
})
