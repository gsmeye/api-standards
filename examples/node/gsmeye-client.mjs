/**
 * Minimal client for the GSM EYE Reseller API. Node 18+ (built-in fetch), no packages.
 *
 *   import { GsmEyeClient } from './gsmeye-client.mjs';
 *
 *   const api = new GsmEyeClient('https://YOUR-DOMAIN', 'you@example.com', 'YOUR_API_KEY');
 *   console.log(await api.accountInfo());
 *   const order = await api.placeOrder('6904', {
 *     imei: '356789104512345',
 *     fields: { 'Picture on sign-in page': 'https://i.imgur.com/abc123.jpg' },
 *   });
 *   console.log(await api.getOrder(order.REFERENCEID));
 */

export const STATUS = Object.freeze({ WAITING: 0, IN_PROCESS: 1, REJECTED: 3, SUCCESS: 4 });

export const isFinal = (status) => status === STATUS.SUCCESS || status === STATUS.REJECTED;

/** An API error. `httpStatus`: 401 bad key, 403 wrong IP, 404 not found, 429 rate limited, 503 maintenance. */
export class GsmEyeError extends Error {
    constructor(message, httpStatus = 0) {
        super(message);
        this.name = 'GsmEyeError';
        this.httpStatus = httpStatus;
    }
}

const b64json = (value) => Buffer.from(JSON.stringify(value), 'utf8').toString('base64');
const xmlEscape = (value) => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

export class GsmEyeClient {
    constructor(siteUrl, username, apiKey, { timeoutMs = 60_000 } = {}) {
        this.endpoint = `${siteUrl.replace(/\/+$/, '')}/api/index.php`;
        this.username = username;
        this.apiKey = apiKey;
        this.timeoutMs = timeoutMs;
    }

    async accountInfo() {
        return (await this.#call('accountinfo')).SUCCESS[0].AccountInfo;
    }

    /** The full catalogue, keyed by group name. Allowed once per 5 minutes. */
    async serviceList() {
        return (await this.#call('imeiservicelist')).SUCCESS[0].LIST;
    }

    async placeOrder(serviceId, { imei = '', fields = null, quantity = 1 } = {}) {
        let xml = `<PARAMETERS><ID>${xmlEscape(serviceId)}</ID><QNT>${Number(quantity)}</QNT>`;
        if (imei) xml += `<IMEI>${xmlEscape(imei)}</IMEI>`;
        if (fields && Object.keys(fields).length) xml += `<CUSTOMFIELD>${b64json(fields)}</CUSTOMFIELD>`;
        xml += '</PARAMETERS>';

        return (await this.#call('placeimeiorder', xml)).SUCCESS[0];
    }

    /** rows: { 'A-1001': { ID: '6904', IMEI: '35...', CUSTOMFIELD: { Field: 'value' } }, ... } */
    async placeBulkOrders(rows) {
        return (await this.#call('placeimeiorderbulk', b64json(rows))).SUCCESS;
    }

    async getOrder(orderId) {
        return (await this.#call('getimeiorder', `<PARAMETERS><ID>${xmlEscape(orderId)}</ID></PARAMETERS>`)).SUCCESS[0];
    }

    /** orderIds: { 'A-1001': 98214, ... } -> { 'A-1001': { STATUS: 4, CODE: '...' }, ... } */
    async getOrders(orderIds) {
        const rows = Object.fromEntries(Object.entries(orderIds).map(([ref, id]) => [ref, { ID: String(id) }]));
        const { apiversion, ...response } = await this.#call('getimeiorderbulk', b64json(rows));

        return Object.fromEntries(Object.entries(response).map(([ref, row]) => [ref, row.SUCCESS[0]]));
    }

    async #call(action, parameters) {
        const form = new URLSearchParams({
            username: this.username,
            apiaccesskey: this.apiKey,
            requestformat: 'JSON',
            action,
        });
        if (parameters !== undefined) form.set('parameters', parameters);

        let response;
        try {
            response = await fetch(this.endpoint, {
                method: 'POST',
                body: form,
                headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(this.timeoutMs),
            });
        } catch (error) {
            throw new GsmEyeError(`Connection failed: ${error.message}`);
        }

        let data;
        try {
            data = await response.json();
        } catch {
            throw new GsmEyeError(`Unexpected response (HTTP ${response.status})`, response.status);
        }

        if (data.ERROR) throw new GsmEyeError(data.ERROR[0]?.MESSAGE || 'Unknown error', response.status);
        if (! response.ok) throw new GsmEyeError(data.message || `HTTP ${response.status}`, response.status);

        return data;
    }
}

// node gsmeye-client.mjs  (with GSMEYE_URL, GSMEYE_USER, GSMEYE_KEY set)
if (import.meta.url === `file://${process.argv[1]}` || process.argv[1]?.endsWith('gsmeye-client.mjs')) {
    const { GSMEYE_URL, GSMEYE_USER, GSMEYE_KEY } = process.env;
    if (GSMEYE_URL && GSMEYE_USER && GSMEYE_KEY) {
        const api = new GsmEyeClient(GSMEYE_URL, GSMEYE_USER, GSMEYE_KEY);
        console.log(await api.accountInfo());
    }
}
