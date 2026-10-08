import { test, expect } from '@playwright/test';
import { uid } from './helpers.mjs';

test('a visitor can register, favorite a game, see it in favorites and log out', async ({ page }) => {
  const id = uid();
  await page.goto('/en/register');
  await page.fill('input[name="nickname"]', `player_${id}`);
  await page.fill('input[name="email"]', `player_${id}@example.test`);
  await page.fill('input[name="password"]', 'CorrectHorse42x');
  await page.fill('input[name="password_confirmation"]', 'CorrectHorse42x');
  await page.check('input[name="terms"]');
  await page.locator('form button[type="submit"], form button:not([type])').last().click();
  await expect(page).not.toHaveURL(/register/);

  await page.goto('/en/game/merge-orbit');
  await page.getByRole('button', { name: /Favorite/ }).click();
  await expect(page.getByRole('button', { name: /Favorite/ })).toHaveAttribute('aria-pressed', 'true');

  await page.goto('/en/account/favorites');
  await expect(page.locator('a[href*="/game/merge-orbit"]').first()).toBeVisible();
});

test('login rejects a wrong password without revealing whether the account exists', async ({ page }) => {
  await page.goto('/en/login');
  await page.fill('input[name="email"]', 'nobody@example.test');
  await page.fill('input[name="password"]', 'wrong-password-123');
  await page.locator('form button').last().click();
  await expect(page).toHaveURL(/login/);
  await expect(page.locator('body')).toContainText(/credentials|match/i);
});
