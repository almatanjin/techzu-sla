import { Outlet } from 'react-router'

/** Page frame shared by the signed-out pages: canvas background, padding and a centered card column. */
export function AuthLayout() {
  return (
    <div className="min-h-dvh bg-canvas px-4 py-10 text-fg sm:py-16">
      <Outlet />
    </div>
  )
}
