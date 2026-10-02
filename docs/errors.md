# Errors

## Envelope

Every failure has the same shape:

```json
{ "ERROR": [ { "MESSAGE": "Insufficient balance" } ], "apiversion": "1.0" }
```

Check for the `ERROR` key, not only the HTTP status: the bulk actions answer
`200` with per-row errors inside `SUCCESS` (see
[placeimeiorderbulk](actions/placeimeiorderbulk.md)).

## HTTP status codes

| Status | When |
|---|---|
| `200` | Success, including bulk responses that contain failed rows |
| `400` | Bad request: missing or malformed field, unknown action, invalid XML, a business rule refused the order |
| `401` | Wrong API key, or username does not match the key |
| `403` | Call came from an IP other than the one the key is bound to |
| `404` | Unknown service ID, or an order ID that is not yours |
| `429` | Rate limit hit |
| `503` | Site is in maintenance mode |

## Messages

### Request

| Message | Status | Cause |
|---|---|---|
| `Validation failed` | 400 | A required form field is missing, `requestformat` is not `JSON`, or `action` is not a known action |
| `Missing parameters` | 400 | `placeimeiorder` / `getimeiorder` without a `parameters` field |
| `Invalid parameters: Invalid format` | 400 | `parameters` is not well-formed XML |
| `Parameters must be valid base64-encoded JSON` | 400 | Bulk action with an unreadable `parameters` |

### Authentication

| Message | Status |
|---|---|
| `Invalid API access key or username` | 401 |
| `Unauthorized IP address` | 403 |

### Orders

| Message | Status | Cause |
|---|---|---|
| `Service ID is required` | 400 | `<ID>` missing or empty (also returned by `getimeiorder` for a missing order ID) |
| `Invalid service ID` | 404 | No such service (also returned by `getimeiorder` for an order that is not yours) |
| `"<service>" is not available for ordering right now.` | 400 | The service or its group is disabled on the site |
| `"<service>" has no price set, so it cannot be ordered right now.` | 400 | The service has no price for your account |
| `Missing required field: <fieldname>` | 400 | A required input was empty — see [Input fields](fields.md) |
| IMEI blacklist message | 400 | The identifier is on the site's blacklist for this service |
| `Insufficient balance` | 400 | Balance too low and no overdue credit allowed |
| `Overdue limit exceeded` | 400 | Balance plus remaining overdue credit is too low |
| `Invalid user` | 400 | The account behind the key no longer exists |

A refused order is **never charged**. An order that was accepted and later
rejected by the supplier is refunded automatically; its status becomes `3`.

## Rate limits

| Limit | Message |
|---|---|
| 120 requests per minute per IP | Standard `429 Too Many Attempts.` |
| `imeiservicelist` once per 5 minutes per IP | `Sorry, you are calling our service too frequent, Action [imeiservicelist] only allows once per 5 minutes, last synced N minute ago.` |

On `429`, back off and retry after the window; hammering keeps the limit hot.
For status polling, `getimeiorderbulk` with up to a few hundred IDs per call is
far cheaper than one `getimeiorder` per order.

## Response headers

Every response from the API carries:

| Header | Value |
|---|---|
| `gsmeye-api-version` | `1.0` |
| `X-Powered-By` | `GSMEYE` |
| `Cache-Control` | `no-store, no-cache, must-revalidate, max-age=0` |
