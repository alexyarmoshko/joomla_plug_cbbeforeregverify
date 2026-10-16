// Test settings from tests/e2e/.env. See .env.example for what each one means.
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const envFile = fileURLToPath(new URL('../.env', import.meta.url));

if (!existsSync(envFile)) {
    throw new Error(`Missing ${envFile}. Copy .env.example to .env and set it up for your test site.`);
}

process.loadEnvFile(envFile);

function required(name) {
    const value = (process.env[name] ?? '').trim();

    if (value === '') {
        throw new Error(`${name} is not set in ${envFile}`);
    }

    return value;
}

const dbPrefix = required('E2E_DB_PREFIX');

if (!/^[A-Za-z0-9_]+$/.test(dbPrefix)) {
    throw new Error('E2E_DB_PREFIX may contain only letters, digits and underscores');
}

const formDelayMs = Number(process.env.E2E_FORM_DELAY_MS || 3500);

if (!Number.isInteger(formDelayMs) || formDelayMs < 0) {
    throw new Error('E2E_FORM_DELAY_MS must be a whole number of milliseconds');
}

let regFields;

try {
    regFields = JSON.parse(process.env.E2E_REG_FIELDS || '{}');
} catch (e) {
    throw new Error(`E2E_REG_FIELDS is not valid JSON: ${e.message}`);
}

if (regFields === null || typeof regFields !== 'object' || Array.isArray(regFields)) {
    throw new Error('E2E_REG_FIELDS must be a JSON object of selector -> value');
}

const registerPath = required('E2E_REGISTER_PATH');

if (!registerPath.startsWith('/')) {
    throw new Error('E2E_REGISTER_PATH must start with "/"');
}

export const config = {
    baseUrl: required('E2E_BASE_URL').replace(/\/+$/, ''),
    registerPath,
    mailpitUrl: required('E2E_MAILPIT_URL').replace(/\/+$/, ''),
    ddevDir: required('E2E_DDEV_DIR'),
    dbPrefix,
    regFields,
    afterRegisterClick: (process.env.E2E_AFTER_REGISTER_CLICK ?? '').trim(),
    formDelayMs,
};

// Every address the tests create is under this domain, and cleanup deletes every user and
// verification row under it. tests/concurrency reserves the same domain.
export const TEST_DOMAIN = 'example.test';

export const stateFile = fileURLToPath(new URL('../.state/captcha.json', import.meta.url));
