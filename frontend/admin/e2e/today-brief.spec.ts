import { test, expect } from '@playwright/test'
import { setupClaudrielAdminMocks } from './fixtures/claudrielSession'

test.describe('Today brief', () => {
  test.beforeEach(async ({ page }) => {
    await setupClaudrielAdminMocks(page)
    await page.route('**/brief**', (route) => {
      if (route.request().method() !== 'GET') {
        return route.fallback()
      }
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          schedule: [],
          temporal_suggestions: [],
          people: [],
          triage: [],
          commitments: { pending: [], drifting: [], waiting_on: [] },
          follow_ups: [],
          counts: {
            due_today: 0,
            drifting: 0,
            waiting_on: 0,
            follow_ups: 0,
            triage: 0,
          },
          github: {
            mentions: [],
            review_requests: [],
            ci_failures: [],
            activity: [],
          },
        }),
      })
    })
  })

  test('root redirects to Today', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveURL(/\/admin\/today\/?$/)
  })

  test('Today shows ops sections', async ({ page }) => {
    await page.goto('/today')
    await expect(page.getByRole('heading', { name: 'Today' })).toBeVisible()
    await expect(page.getByRole('heading', { name: /Do — outbound commitments/i })).toBeVisible()
  })
})
