import { test as setup } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const authFile = path.join(__dirname, '../.auth/admin.json');

setup('authenticate as admin', async ({ page, baseURL }) => {
    fs.mkdirSync(path.dirname(authFile), { recursive: true });

    await page.goto(`${baseURL}/wp-login.php`);
    await page.fill('#user_login', process.env.WP_ADMIN_USER ?? 'admin');
    await page.fill('#user_pass',  process.env.WP_ADMIN_PASSWORD ?? 'admin');
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**');

    await page.context().storageState({ path: authFile });
});
