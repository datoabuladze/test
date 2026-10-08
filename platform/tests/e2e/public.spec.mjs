import { test, expect } from '@playwright/test';
import { trackErrors } from './helpers.mjs';

for (const locale of ['en', 'ka', 'tr', 'ru']) {
  test(`home renders in ${locale} without errors @mobile`, async ({ page }) => {
    const errors = trackErrors(page);
    const res = await page.goto(`/${locale}`);
    expect(res.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
    await expect(page.locator('link[rel="alternate"][hreflang="x-default"]')).toHaveCount(1);
    await expect(page.locator('a[href*="/game/"]').first()).toBeVisible();
    expect(errors).toEqual([]);
  });
}

test('search suggests games while typing and the results page lists them', async ({ page }) => {
  await page.goto('/en');
  const box = page.locator('input[type="search"]').first();
  await box.fill('snake');
  await expect(page.locator('a[href*="/game/neon-snake"]').first()).toBeVisible();
  await box.press('Enter');
  await expect(page).toHaveURL(/\/en\/search\?q=snake/);
  await expect(page.locator('a[href*="/game/neon-snake"]').first()).toBeVisible();
});

test('typo tolerant search finds a game', async ({ page }) => {
  await page.goto('/en/search?q=snaek');
  await expect(page.locator('a[href*="/game/neon-snake"]').first()).toBeVisible();
});

test('game loads in the sandboxed player after pressing play @mobile', async ({ page }) => {
  const errors = trackErrors(page);
  await page.goto('/en/game/merge-orbit');
  const frame = page.getByTestId('game-frame');
  await expect(frame).toHaveCount(0); // nothing loads before Play
  await page.getByTestId('play-button').click();
  await expect(frame).toHaveAttribute('sandbox', /allow-scripts/);
  await expect(frame).not.toHaveAttribute('sandbox', /allow-same-origin/);
  // The game announces readiness over postMessage; the player then hides its loading screen.
  await expect(page.getByText(/Loading/)).toBeHidden({ timeout: 20_000 });
  expect(errors).toEqual([]);
});

test('category, legal page, sitemap and robots respond', async ({ page, request }) => {
  expect((await page.goto('/en/categories')).status()).toBe(200);
  expect((await page.goto('/ka/p/privacy')).status()).toBe(200);
  const sitemap = await request.get('/sitemap.xml');
  expect(sitemap.status()).toBe(200);
  expect(await sitemap.text()).toContain('<sitemapindex');
  expect((await request.get('/robots.txt')).status()).toBe(200);
});

test('unknown pages return a localized 404', async ({ page }) => {
  const res = await page.goto('/en/game/this-game-does-not-exist');
  expect(res.status()).toBe(404);
});
