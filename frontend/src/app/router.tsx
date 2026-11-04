import { createBrowserRouter } from 'react-router'
import { AuthProvider } from '../features/auth/AuthProvider'
import { ForgotPasswordPage } from '../features/auth/ForgotPasswordPage'
import { GuestOnly } from '../features/auth/guards/GuestOnly'
import { RequireAuth } from '../features/auth/guards/RequireAuth'
import { RequireRole } from '../features/auth/guards/RequireRole'
import { LoginPage } from '../features/auth/LoginPage'
import { RegisterPage } from '../features/auth/RegisterPage'
import { ResetPasswordPage } from '../features/auth/ResetPasswordPage'
import { UserFormPage } from '../features/users/UserFormPage'
import { UsersListPage } from '../features/users/UsersListPage'
import { AppLayout } from './AppLayout'
import { HomePage } from './HomePage'
import { NotFoundPage } from './NotFoundPage'

export function createAppRouter() {
  return createBrowserRouter([
    {
      element: <AuthProvider />,
      children: [
        {
          element: <GuestOnly />,
          children: [
            { path: '/login', element: <LoginPage /> },
            { path: '/register', element: <RegisterPage /> },
            { path: '/forgot-password', element: <ForgotPasswordPage /> },
          ],
        },
        {
          path: '/reset-password',
          element: <ResetPasswordPage />,
        },
        {
          element: <RequireAuth />,
          children: [
            {
              element: <AppLayout />,
              children: [
                { index: true, element: <HomePage /> },
                {
                  element: <RequireRole role="admin" />,
                  children: [
                    { path: '/admin/users', element: <UsersListPage /> },
                    { path: '/admin/users/new', element: <UserFormPage /> },
                    { path: '/admin/users/:id/edit', element: <UserFormPage /> },
                  ],
                },
              ],
            },
          ],
        },
        { path: '*', element: <NotFoundPage /> },
      ],
    },
  ])
}
