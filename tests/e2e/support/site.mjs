// Site-side helpers. Everything goes through `ddev` in the test site's project directory.
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { config, stateFile, TEST_DOMAIN } from './config.mjs';

const cleanupScript = fileURLToPath(new URL('./cb_cleanup.php', import.meta.url));
const FIELDS = config.dbPrefix + 'comprofiler_fields';
const PLUGINS = config.dbPrefix + 'comprofiler_plugin';
const USERS = config.dbPrefix + 'users';
const COMPROFILER = config.dbPrefix + 'comprofiler';
const VERIFY = config.dbPrefix + 'comprofiler_plugin_beforeregverify';

function ddev(args, input) {
    try {
        return execFileSync('ddev', args, { cwd: config.ddevDir, encoding: 'utf8', input, stdio: ['pipe', 'pipe', 'pipe'] });
    } catch (e) {
        // cb_cleanup.php reports which users it couldn't delete on stdout, then exits 1.
        throw new Error(`ddev ${args.join(' ')} failed: ${e.message}\n${e.stdout ?? ''}${e.stderr ?? ''}`.trim(), { cause: e });
    }
}

export function sql(query) {
    return ddev(['mysql', '-N', '-B', '-e', query]).trim();
}

// Only addresses the tests generate are interpolated into SQL; anything else is refused.
function testEmail(email) {
    if (!new RegExp(`^[a-z0-9.+-]+@${TEST_DOMAIN.replaceAll('.', '\\.')}$`).test(email)) {
        throw new Error(`Not a test address: ${email}`);
    }

    return email;
}

export function cacheClean() {
    ddev(['exec', 'php', 'cli/joomla.php', 'cache:clean']);
}

const PLACEHOLDER = JSON.stringify({ antispam_captcha: null }) + '\n';

/**
 * What getBool('gateway_enabled', true) returns for a decoded JSON value. CB returns the
 * default for null and for an array or object (CBLib/Input/Get.php:44-62), and casts the rest
 * with PHP's (bool).
 */
function gatewayOn(value) {
    if (value === null || typeof value === 'object') {
        return true;
    }

    return typeof value === 'string' ? value !== '' && value !== '0' : Boolean(value);
}

/** Refuse to run unless the plugin is published, the gateway is on and a secret is set. Never reads the secret's value. */
export function checkPlugin() {
    const row = sql(
        `SELECT published, JSON_VALID(params), COALESCE(JSON_EXTRACT(params,'$.gateway_enabled'),'missing'), ` +
        // getSecret() trims with PHP trim() and issueCode() refuses a falsy string. Checked in
        // SQL, so the secret's value never leaves the database.
        `COALESCE(JSON_TYPE(JSON_EXTRACT(params,'$.secret'))='STRING' AND ` +
        `REGEXP_REPLACE(JSON_UNQUOTE(JSON_EXTRACT(params,'$.secret')),'^[[:space:]]+|[[:space:]]+$','') NOT IN ('','0'), 0) ` +
        `FROM ${PLUGINS} WHERE element='cbbeforeregverify'`
    );
    const [published, validJson, gateway, secretSet] = row.split('\t');
    // getBool('gateway_enabled', true): a missing key counts as on.
    const enabled = gateway === 'missing' || gatewayOn(JSON.parse(gateway));

    if (published !== '1' || validJson !== '1' || !enabled || secretSet !== '1') {
        throw new Error('cbbeforeregverify must be installed and published, with gateway_enabled on and a secret set');
    }
}

/**
 * Record the captcha field's published state in the state file. The file is created
 * exclusively before the field is read, so a run that finds one already there (an earlier run
 * that didn't restore, or a run still going) fails here and owns nothing it could wrongly
 * restore, and no run reads the field while another has it unpublished.
 */
export function recordCaptcha() {
    mkdirSync(dirname(stateFile), { recursive: true });

    try {
        // Placeholder: restoreCaptcha() treats anything but 1 as "leave the field alone".
        writeFileSync(stateFile, PLACEHOLDER, { flag: 'wx' });
    } catch (e) {
        if (e.code === 'EEXIST') {
            throw new Error(`${stateFile} exists: another run is going, or an earlier run did not restore the captcha field. If no run is going, run "npm run restore".`);
        }

        throw e;
    }

    try {
        const published = sql(`SELECT published FROM ${FIELDS} WHERE name='antispam_captcha'`);
        const tmp = `${stateFile}.${process.pid}.tmp`;

        // Write and rename, so a kill mid-write can't leave a half-written state file.
        writeFileSync(tmp, JSON.stringify({ antispam_captcha: published === '' ? null : Number(published) }) + '\n');
        renameSync(tmp, stateFile);
    } catch (e) {
        rmSync(stateFile);
        throw e;
    }
}

/**
 * Refuse to run unless E2E_BASE_URL serves the DDEV project in E2E_DDEV_DIR. A random token
 * written into the project's web root must come back through E2E_BASE_URL; a configured
 * hostname alone could still resolve to another site.
 *
 * @param {import('@playwright/test').APIRequestContext} http
 */
export async function checkSiteIsDdevProject(http) {
    const token = randomUUID();
    const name = `e2e-site-check-${token}.txt`;

    ddev(['exec', 'tee', name], token);

    try {
        const res = await http.get(`${config.baseUrl}/${name}`);

        if (res.status() !== 200 || (await res.text()) !== token) {
            throw new Error(`E2E_BASE_URL (${config.baseUrl}) does not serve the DDEV project in E2E_DDEV_DIR (${config.ddevDir})`);
        }
    } finally {
        ddev(['exec', 'rm', '-f', name]);
    }

    const { hostnames = [] } = JSON.parse(ddev(['describe', '-j'])).raw ?? {};
    const mailpitHost = new URL(config.mailpitUrl).hostname;

    if (!hostnames.includes(mailpitHost)) {
        throw new Error(`E2E_MAILPIT_URL host ${mailpitHost} is not a hostname of the DDEV project (${hostnames.join(', ')})`);
    }
}

/** Unpublish the captcha field if the recorded state says it was published. */
export function unpublishCaptcha() {
    if (JSON.parse(readFileSync(stateFile, 'utf8')).antispam_captcha === 1) {
        sql(`UPDATE ${FIELDS} SET published=0 WHERE name='antispam_captcha'`);
        cacheClean();
    }
}

/** Put the captcha field back to the recorded state and remove the state file. */
export function restoreCaptcha() {
    if (!existsSync(stateFile)) {
        return false;
    }

    // A read error (permissions, I/O) throws and keeps the file for a later restore.
    const text = readFileSync(stateFile, 'utf8');
    let published;

    try {
        ({ antispam_captcha: published } = JSON.parse(text));
    } catch (e) {
        // Only the placeholder write is not atomic, and it happens before the field is touched.
        // Anything else that doesn't parse is kept for a person to look at.
        if (!PLACEHOLDER.startsWith(text)) {
            throw new Error(`${stateFile} is not valid state (${e.message}); check the captcha field by hand, then delete the file.`, { cause: e });
        }

        console.warn(`${stateFile} holds a partly written placeholder; removing it and leaving the captcha field as it is.`);
        rmSync(stateFile);

        return false;
    }

    if (published === 1) {
        sql(`UPDATE ${FIELDS} SET published=1 WHERE name='antispam_captcha'`);
        cacheClean();
    }

    rmSync(stateFile);

    return true;
}

/** Delete every test-domain user through CB, and every test-domain verification row. */
export function cleanup() {
    return ddev(['exec', 'php', '--', TEST_DOMAIN], readFileSync(cleanupScript, 'utf8')).trim();
}

/** @returns {{id: number, confirmed: number | null} | null} confirmed is null when CB has no profile row. */
export function findUser(email) {
    const row = sql(
        `SELECT u.id, c.confirmed FROM ${USERS} u LEFT JOIN ${COMPROFILER} c ON c.id=u.id WHERE u.email='${testEmail(email)}'`
    );

    if (row === '') {
        return null;
    }

    const [id, confirmed] = row.split('\t');

    return { id: Number(id), confirmed: confirmed === 'NULL' ? null : Number(confirmed) };
}

/** Outcomes of every verification row for the address, oldest first. A wrong code adds a 'failed' row. */
export function verificationOutcomes(email) {
    const rows = sql(`SELECT outcome FROM ${VERIFY} WHERE email='${testEmail(email)}' ORDER BY id`);

    return rows === '' ? [] : rows.split('\n');
}
