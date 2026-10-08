export function trackErrors(page) {
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => {
    if (m.type() === 'error' && !/favicon/.test(m.location()?.url || '')) errors.push(m.text());
  });
  return errors;
}

export function uid() {
  return Math.random().toString(36).slice(2, 9);
}
