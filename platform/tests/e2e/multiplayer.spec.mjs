import { test, expect } from '@playwright/test';

test('two players can join a tic-tac-toe room and play to a win', async ({ browser }) => {
  const host = await (await browser.newContext()).newPage();
  const guest = await (await browser.newContext()).newPage();

  await host.goto('/en/play-together');
  await host.getByTestId('create-tictactoe').click();
  await expect(host).toHaveURL(/play-together\/[A-Z0-9]+/);
  await guest.goto(host.url());
  await guest.getByTestId('join-room').click();

  await host.getByTestId('ready').click();
  await guest.getByTestId('ready').click();

  // Host is X and moves first; X takes the top row.
  const move = async (page, cell) => {
    await expect(page.getByTestId('room-message')).toHaveText(/Your turn/, { timeout: 10_000 });
    await page.getByTestId(`cell-${cell}`).click();
  };
  await move(host, 0);
  await move(guest, 3);
  await move(host, 1);
  await move(guest, 4);
  await move(host, 2);

  await expect(host.getByTestId('room-message')).toHaveText(/You won/, { timeout: 10_000 });
  await expect(guest.getByTestId('room-message')).toHaveText(/friend won/, { timeout: 10_000 });
});
