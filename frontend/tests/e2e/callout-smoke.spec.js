import { test, expect } from '@playwright/test';

/**
 * Smoke tests for Callout functionality
 * These tests verify that critical pages load without errors
 */
test.describe('Callout Smoke Tests', () => {
  test('callout create page loads', async ({ page }) => {
    const response = await page.goto('/callout/create');
    expect(response.status()).toBeLessThan(400);
    
    // Verify page loaded by checking for common elements
    await expect(page).toHaveTitle(/Subterra/i);
  });

  test('callout active page loads', async ({ page }) => {
    const response = await page.goto('/callout/active');
    expect(response.status()).toBeLessThan(400);
    
    await expect(page).toHaveTitle(/Subterra/i);
  });

  test('admin callout page loads', async ({ page }) => {
    const response = await page.goto('/admin/callout');
    expect(response.status()).toBeLessThan(400);
    
    await expect(page).toHaveTitle(/Subterra/i);
  });
});
