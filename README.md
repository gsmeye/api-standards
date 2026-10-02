# GSM EYE Reseller API

The reseller API every GSM EYE server exposes. A reseller panel, a desktop tool
or a script uses it to read the service catalogue, check its balance, place
orders and collect results from a GSM EYE site.

It speaks the **Dhru Fusion / GSM Theme** dialect, so a panel that already
talks to a Dhru Fusion supplier usually connects with no code changes: add the
site as a new supplier, enter your email and API key, and sync.

| | |
|---|---|
| **Endpoint** | `POST https://YOUR-DOMAIN/api/index.php` |
| **Body** | `application/x-www-form-urlencoded` |
| **Response** | JSON, UTF-8 |
| **API version** | `1.0` (sent as `apiversion` in every body and as the `gsmeye-api-version` header) |

## Quick start

```bash
curl -X POST https://YOUR-DOMAIN/api/index.php \
  -d "username=you@example.com" \
  -d "apiaccesskey=YOUR_API_KEY" \
  -d "requestformat=JSON" \
  -d "action=accountinfo"
```

```json
{
  "SUCCESS": [
    {
      "message": "Your Account Info",
      "AccountInfo": { "credit": "$25.40", "creditraw": 25.4, "mail": "you@example.com", "currency": "USD" }
    }
  ],
  "apiversion": "1.0"
}
```

Your API key is on the site under **Dashboard → API Settings**. The first
successful call binds the key to the IP it came from — read
[Authentication](docs/authentication.md) before calling from a new server.

## Actions

| Action | What it does |
|---|---|
| [`accountinfo`](docs/actions/accountinfo.md) | Balance, email and currency |
| [`imeiservicelist`](docs/actions/imeiservicelist.md) | Full catalogue with your prices and input fields (once per 5 minutes) |
| [`placeimeiorder`](docs/actions/placeimeiorder.md) | Place one order |
| [`placeimeiorderbulk`](docs/actions/placeimeiorderbulk.md) | Place many orders in one call (`placebulkorder` is accepted too) |
| [`getimeiorder`](docs/actions/getimeiorder.md) | Status and result of one order |
| [`getimeiorderbulk`](docs/actions/getimeiorderbulk.md) | Status and result of many orders |

Despite the names, every action covers all three service types: **IMEI**,
**SERVER** and **REMOTE**.

## Documentation

- [Authentication and IP binding](docs/authentication.md)
- [Input fields: the identifier, `CUSTOM`, `Requires.Custom` and `CUSTOMFIELD`](docs/fields.md)
- [Order status codes](docs/actions/getimeiorder.md#status-codes)
- [Errors, HTTP codes and rate limits](docs/errors.md)
- [Changelog](CHANGELOG.md)

## Client examples

Ready-to-run clients covering every action:

- [PHP](examples/php/GsmEyeClient.php) — needs only the curl extension
- [Python](examples/python/gsmeye_client.py) — standard library only
- [Node.js](examples/node/gsmeye-client.mjs) — Node 18+, no packages
- [cURL](examples/curl.sh)
- [Postman collection](postman/GSM-EYE-Reseller-API.postman_collection.json)

## Support

For help integrating, or a response that does not match this document, open
an issue in this repository or contact the support team of the GSM EYE site
you are connecting to.
