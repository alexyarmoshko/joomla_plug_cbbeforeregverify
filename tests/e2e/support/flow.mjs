// Browser steps of the sign-up flow.
import { expect } from '@playwright/test';
import { config, TEST_DOMAIN } from './config.mjs';
import { codeFor } from './mailpit.mjs';

const run = Date.now().toString(36);
let serial = 0;

/** A unique test address and username for this run. */
export function newIdentity(label) {
    serial += 1;
    const local = `e2e-${run}-${serial}-${label}`;

    return { email: `${local}@${TEST_DOMAIN}`, username: `e2e${run}${serial}`, password: 'E2e-Passw0rd!' + run };
}

export const registrationForm = (page) => page.locator('#cbcheckedadminForm');
export const messages = (page) => page.locator('#system-message-container');
export const errorMessages = (page) => messages(page).locator('joomla-alert[type="danger"], joomla-alert[type="error"], .alert-danger, .alert-error');

/** Absolute URL on the site. A path starting with "/" given to page.goto() would drop any path in E2E_BASE_URL. */
export const siteUrl = (path) => config.baseUrl + path;

export async function openRegistration(page) {
    await page.goto(siteUrl(config.registerPath));
}

/**
 * Click something that loads a new page, and wait until that page has loaded, after any
 * redirect. The old page gets a marker; only a new document lacks it. A load-state or
 * framenavigated wait could still return on the old page, or on an in-page URL change.
 */
export async function clickAndLoad(page, target) {
    await page.evaluate(() => { window.__e2eOldPage = true; });
    await target.click();

    try {
        await page.waitForFunction(() => !window.__e2eOldPage, null, { timeout: 30_000 });
    } catch (e) {
        throw new Error(`clicking ${target} loaded no new page within 30 s; did form validation block the submit?`, { cause: e });
    }

    await page.waitForLoadState('load');
}

/** Enter an email on the gateway's email step and land on the code step. */
export async function requestCode(page, email) {
    await openRegistration(page);
    await page.locator('#cbbeforeregverify_email').fill(email);
    await page.locator('form.cbBeforeRegVerifyForm:has(#cbbeforeregverify_email) [type=submit]').click();
    await expect(page).toHaveURL(/func=step_code/);
}

export async function submitCode(page, code) {
    await page.locator('#cbbeforeregverify_code').fill(code);
    await clickAndLoad(page, page.locator('form.cbBeforeRegVerifyFormCode [type=submit]'));
}

/** Run the gateway from the email step to the CB registration form. */
export async function verify(page, email) {
    await requestCode(page, email);
    await submitCode(page, await codeFor(page.request, email));
    await expect(registrationForm(page)).toBeVisible();
}

/** Fill the CB registration form, except the email, with the identity and the site's extra fields. */
export async function fillRegistration(page, identity) {
    const form = registrationForm(page);
    const fillIfPresent = async (selector, value) => {
        const field = form.locator(selector);

        if (await field.count()) {
            await field.fill(value);
        }
    };

    await form.locator('#username').fill(identity.username);
    await form.locator('#password').fill(identity.password);
    await fillIfPresent('input[name="password__verify"]', identity.password);
    await fillIfPresent('#firstname', 'Etoe');
    await fillIfPresent('#lastname', 'Tester');

    for (const [selector, value] of Object.entries(config.regFields)) {
        const field = form.locator(selector);
        const matches = await field.count();

        if (matches !== 1) {
            throw new Error(`E2E_REG_FIELDS selector ${selector} matches ${matches} fields in the registration form, expected 1`);
        }

        const tag = await field.evaluate((el) => el.tagName + ':' + (el.type || ''));

        if (tag.startsWith('SELECT')) {
            await field.selectOption(String(value));
        } else if (/:(checkbox|radio)$/.test(tag)) {
            await field.setChecked(Boolean(value));
        } else {
            await field.fill(String(value));
        }
    }
}

/** Wait out the CB AntiSpam timer, then submit the registration form (not the restart button). */
export async function submitRegistration(page) {
    await page.waitForTimeout(config.formDelayMs);
    await clickAndLoad(page, registrationForm(page).locator('[type=submit]:not([form])'));
}
