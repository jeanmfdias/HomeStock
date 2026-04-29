import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'

export const server = setupServer(
  http.get('/api/auth/me', () =>
    HttpResponse.json({
      id: 1,
      email: 'demo@homestock.local',
      name: 'Demo',
      roles: ['ROLE_USER'],
    }),
  ),
  http.post('/api/auth/login', async ({ request }) => {
    const body = (await request.json()) as { email: string; password: string }
    if (body.password !== 'demopass123') {
      return HttpResponse.json({ error: 'bad_credentials' }, { status: 401 })
    }

    return HttpResponse.json({
      id: 1,
      email: body.email,
      name: 'Demo',
      roles: ['ROLE_USER'],
    })
  }),
)
