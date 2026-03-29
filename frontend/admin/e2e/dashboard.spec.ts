import { test, expect } from '@playwright/test'
import { setupClaudrielAdminMocks, CLAUDRIEL_MOCK_ENTITY_TYPES } from './fixtures/claudrielSession'

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await setupClaudrielAdminMocks(page)
  })

  test('renders entity type cards with labels from session', async ({ page }) => {
    await page.goto('/data')
    const main = page.locator('main')
    for (const et of CLAUDRIEL_MOCK_ENTITY_TYPES) {
      await expect(main.getByRole('heading', { name: et.label })).toBeVisible()
    }
  })

  test('each card links to the entity type route under /admin', async ({ page }) => {
    await page.goto('/data')
    const main = page.locator('main')
    for (const et of CLAUDRIEL_MOCK_ENTITY_TYPES) {
      await expect(main.locator(`a[href="/admin/${et.id}"]`)).toBeVisible()
    }
  })

  test('clicking a card navigates to the entity list', async ({ page }) => {
    await page.goto('/data')
    await page.locator('main a[href="/admin/person"]').click()
    await expect(page).toHaveURL(/\/admin\/person\/?$/)
  })
})
