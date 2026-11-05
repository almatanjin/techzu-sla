import { createBrowserRouter } from 'react-router'
import { AuthProvider } from '../features/auth/AuthProvider'
import { ForgotPasswordPage } from '../features/auth/ForgotPasswordPage'
import { GuestOnly } from '../features/auth/guards/GuestOnly'
import { RequireAuth } from '../features/auth/guards/RequireAuth'
import { RequireRole } from '../features/auth/guards/RequireRole'
import { LoginPage } from '../features/auth/LoginPage'
import { RegisterPage } from '../features/auth/RegisterPage'
import { ResetPasswordPage } from '../features/auth/ResetPasswordPage'
import { CategoriesPage } from '../features/categories/CategoriesPage'
import { SlaSettingsPage } from '../features/sla/SlaSettingsPage'
import { NewTicketPage } from '../features/tickets/NewTicketPage'
import { TicketDetailPage } from '../features/tickets/TicketDetailPage'
import { TicketsListPage } from '../features/tickets/TicketsListPage'
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
                { path: '/tickets', element: <TicketsListPage /> },
                { path: '/tickets/new', element: <NewTicketPage /> },
                { path: '/tickets/:ticketNumber', element: <TicketDetailPage /> },
                {
                  element: <RequireRole role="admin" />,
                  children: [
                    { path: '/admin/categories', element: <CategoriesPage /> },
                    { path: '/admin/users', element: <UsersListPage /> },
                    { path: '/admin/users/new', element: <UserFormPage /> },
                    { path: '/admin/users/:id/edit', element: <UserFormPage /> },
                  ],
                },
                {
                  element: <RequireRole roles={['admin', 'agent']} />,
                  children: [
                    { path: '/admin/sla', element: <SlaSettingsPage /> },
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
