// Sign-up works with a confirmed email address and is refused without one.
import { expect, test } from '@playwright/test';
import { config } from '../support/config.mjs';
import { codeFor } from '../support/mailpit.mjs';
import { findUser, verificationOutcomes } from '../support/site.mjs';
import {
    clickAndLoad, errorMessages, fillRegistration, messages, newIdentity, openRegistration, registrationForm,
    requestCode, siteUrl, submitCode, submitRegistration, verify,
} from '../support/flow.mjs';

test('the registration page shows the email step until the email is verified', async ({ page }) => {
    await openRegistration(page);

    await expect(page.locator('#cbbeforeregverify_email')).toBeVisible();
    await expect(registrationForm(page)).toHaveCount(0);
});

test('a wrong code does not open the registration form', async ({ page }) => {
    const { email } = newIdentity('wrongcode');

    await requestCode(page, email);
    const code = await codeFor(page.request, email);
    const wrong = code.replace(/\d$/, (d) => String((Number(d) + 1) % 10));

    await submitCode(page, wrong);
    await expect(page).toHaveURL(/func=step_code/);
    await expect(errorMessages(page)).toContainText('The verification code is incorrect');

    await openRegistration(page);
    await expect(registrationForm(page)).toHaveCount(0);
    // The code row is still pending; the wrong code adds a 'failed' row.
    expect(verificationOutcomes(email)).toEqual(['pending', 'failed']);
});

test('posting the registration form without verification creates no user', async ({ page }) => {
    const identity = newIdentity('noverify');

    await openRegistration(page);
    // A valid form token for this session, taken from the gateway's own form.
    const token = await page.locator('form.cbBeforeRegVerifyForm input[type=hidden][value="1"]').first().getAttribute('name');
    expect(token).toMatch(/^[0-9a-f]{32}$/);

    const res = await page.request.post(siteUrl('/index.php?option=com_comprofiler&view=saveregisters'), {
        form: {
            [token]: '1',
            username: identity.username,
            email: identity.email,
            password: identity.password,
            password__verify: identity.password,
            firstname: 'Etoe',
            lastname: 'Tester',
        },
        maxRedirects: 0,
    });

    expect([301, 302, 303]).toContain(res.status());
    expect(res.headers().location).toContain('func=step_email');

    await page.goto(res.headers().location);
    await expect(errorMessages(page)).toContainText('Complete email verification before submitting registration');
    expect(findUser(identity.email)).toBeNull();
});

test('registration with an email other than the verified one is refused', async ({ page }) => {
    const verified = newIdentity('verified');
    const other = newIdentity('other');

    await verify(page, verified.email);
    await fillRegistration(page, verified);

    // Defeat the read-only lock in the page, the way a tampered request would.
    await registrationForm(page).locator('input[name="email"]').evaluate((el, email) => {
        el.removeAttribute('readonly');
        el.value = email;
    }, other.email);

    await submitRegistration(page);

    await expect(page).toHaveURL(/func=step_email/);
    await expect(errorMessages(page)).toContainText('Verified email does not match submitted email');
    expect(findUser(verified.email)).toBeNull();
    expect(findUser(other.email)).toBeNull();
});

test('changing a verified email needs the new address confirmed before registering', async ({ page }) => {
    const first = newIdentity('first');
    const second = newIdentity('second');

    await verify(page, first.email);
    await registrationForm(page).locator('button[form="cbBeforeRegVerifyRestartForm"]').click();

    await expect(page).toHaveURL(/func=step_email/);
    await expect(messages(page)).toContainText('Email verification was reset');
    await expect(page.locator('#cbbeforeregverify_email')).toHaveValue('');

    // The first address no longer opens the form; the new one has to be confirmed.
    await openRegistration(page);
    await expect(registrationForm(page)).toHaveCount(0);

    await verify(page, second.email);
    await expect(registrationForm(page).locator('input[name="email"]')).toHaveValue(second.email);

    await fillRegistration(page, second);
    await submitRegistration(page);

    await expect(page).toHaveURL(/saveregisters/);
    await expect(errorMessages(page)).toHaveCount(0);

    const user = findUser(second.email);
    expect(user, 'user row for the second email').not.toBeNull();
    expect(user.confirmed).toBe(1);
    expect(findUser(first.email)).toBeNull();
});

test('a visitor with a verified email can register', async ({ page }) => {
    const identity = newIdentity('happy');

    await verify(page, identity.email);

    const email = registrationForm(page).locator('input[name="email"]');
    await expect(email).toHaveValue(identity.email);
    await expect(email).toHaveAttribute('readonly', /.*/);

    await fillRegistration(page, identity);
    await submitRegistration(page);

    await expect(page).toHaveURL(/saveregisters/);
    await expect(errorMessages(page)).toHaveCount(0);

    const user = findUser(identity.email);
    expect(user, 'user row for the verified email').not.toBeNull();
    expect(user.confirmed).toBe(1);
    expect(verificationOutcomes(identity.email)).toEqual(['verified']);

    if (config.afterRegisterClick) {
        const button = page.locator(config.afterRegisterClick);
        await expect(button).toBeVisible();
        await clickAndLoad(page, button);
        await expect(page).not.toHaveURL(/saveregisters/);
        await expect(errorMessages(page)).toHaveCount(0);
    }
});
