import { test, expect } from '@playwright/test'
import { setupClaudrielAdminMocks, CLAUDRIEL_MOCK_ENTITY_TYPES } from './fixtures/claudrielSession'

test.describe('Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await setupClaudrielAdminMocks(page)
    await page.goto('/')
  })

  test('renders the Dashboard link', async ({ page }) => {
    await expect(page.locator('nav').getByText('Dashboard')).toBeVisible()
  })

  test('renders grouped nav section headings', async ({ page }) => {
    const sections = page.locator('.nav-section')
    await expect(sections.first()).toBeVisible()
  })

  test('renders entity type labels in the nav', async ({ page }) => {
    for (const et of CLAUDRIEL_MOCK_ENTITY_TYPES) {
      await expect(page.locator('nav').getByText(et.label, { exact: true })).toBeVisible()
    }
  })
})
