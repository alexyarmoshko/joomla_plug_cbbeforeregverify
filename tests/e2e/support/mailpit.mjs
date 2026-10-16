// Read verification codes from Mailpit's HTTP API.
import { expect } from '@playwright/test';
import { config } from './config.mjs';

/**
 * Wait for the newest message to `email` and return the first run of 4 or more digits in its text.
 * Each test address gets one code, so the newest message is the one just sent.
 *
 * @param {import('@playwright/test').APIRequestContext} request
 */
export async function codeFor(request, email) {
    let id = null;

    await expect.poll(async () => {
        const res = await request.get(`${config.mailpitUrl}/api/v1/search`, { params: { query: `to:"${email}"` } });
        const body = await res.json();
        id = body.messages?.[0]?.ID ?? null;

        return id;
    }, { message: `no mail to ${email} in Mailpit`, timeout: 20_000 }).not.toBeNull();

    const message = await (await request.get(`${config.mailpitUrl}/api/v1/message/${id}`)).json();
    const code = /\b\d{4,}\b/.exec(message.Text ?? '')?.[0];

    if (!code) {
        throw new Error(`no code in the mail to ${email}`);
    }

    return code;
}
