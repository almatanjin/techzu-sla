import { setupServer } from 'msw/node'

// Network-level mock shared by all tests; each test registers its own handlers.
export const server = setupServer()
