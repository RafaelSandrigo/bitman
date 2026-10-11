import { render } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { App } from '../app/App'
import { createAppRouter } from '../app/router'

export function renderAt(path: string) {
  const router = createAppRouter({ memory: [path] })
  return { user: userEvent.setup(), router, ...render(<App router={router} />) }
}
