import { test, expect } from '@playwright/test';

const email = process.env.E2E_ADMIN_EMAIL || 'admin@nebulo.test';
const password = process.env.E2E_ADMIN_PASSWORD || 'AdminPass12345';

test('admin can sign in, filter games and open a game preview', async ({ page }) => {
  await page.goto('/en/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.locator('form button').last().click();
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));

  await page.goto('/admin/games?q=orbit');
  await expect(page.locator('table')).toContainText('Merge Orbit');
  await page.goto('/admin/games/merge-orbit/preview');
  await page.getByTestId('play-button').click();
  await expect(page.getByTestId('game-frame')).toHaveCount(1);
});

test('guests are sent to login from the admin panel', async ({ page }) => {
  await page.goto('/admin');
  await expect(page).toHaveURL(/\/login/);
});
