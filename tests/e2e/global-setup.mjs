// Check the site, unpublish the captcha field and remove old test data; the returned
// function restores the captcha field and removes this run's test data.
import { request } from '@playwright/test';
import { config } from './support/config.mjs';
import { checkPlugin, checkSiteIsDdevProject, cleanup, recordCaptcha, restoreCaptcha, unpublishCaptcha } from './support/site.mjs';

/** Restore the captcha field without letting a restore failure hide `error`. */
function restoreAndRethrow(error) {
    try {
        restoreCaptcha();
    } catch (restoreError) {
        throw new AggregateError([error, restoreError], `${error.message}\nThe captcha field was NOT restored: ${restoreError.message}\nRun "npm run restore".`);
    }

    throw error;
}

export default async function globalSetup() {
    const http = await request.newContext({ ignoreHTTPSErrors: true });

    try {
        // First: the browser must drive the same site the database helpers change.
        await checkSiteIsDdevProject(http);
        // Before the page checks, so a disabled gateway is reported as that.
        checkPlugin();

        for (const url of [config.baseUrl + config.registerPath, config.mailpitUrl + '/api/v1/info']) {
            const res = await http.get(url);

            if (res.status() !== 200) {
                throw new Error(`${url} answered ${res.status()}, expected 200`);
            }
        }

        // CB builds form actions and redirects from Joomla's live_site. If that names another
        // site, the browser would leave the one checked above on its first submit.
        const register = await http.get(config.baseUrl + config.registerPath);
        const action = /<form[^>]*action="([^"]*cbbeforeregverify[^"]*)"/.exec(await register.text())?.[1];
        const origin = new URL(config.baseUrl).origin;

        if (!action) {
            throw new Error(`the registration page at ${register.url()} shows no gateway form`);
        }

        for (const [what, url] of [['the registration page', register.url()], ['the gateway form action', action]]) {
            if (new URL(url.replaceAll('&amp;', '&'), config.baseUrl).origin !== origin) {
                throw new Error(`${what} (${url}) is not on ${origin}; check live_site in the site's configuration.php`);
            }
        }
    } finally {
        await http.dispose();
    }

    // From here on this run owns the state file; if this throws, it owns nothing.
    recordCaptcha();

    try {
        unpublishCaptcha();
        console.log('cleanup before run:', cleanup());
    } catch (e) {
        restoreAndRethrow(e);
    }

    return async () => {
        try {
            console.log('cleanup after run:', cleanup());
        } catch (e) {
            restoreAndRethrow(e);
        }

        restoreCaptcha();
    };
}
