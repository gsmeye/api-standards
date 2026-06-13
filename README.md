# GSM EYE — Public API Standard

This folder is the **published reference** for the GSM EYE public API. It documents
how an external client / reseller panel connects to a GSM EYE server. It is
documentation only — the live implementation runs from
`app/Http/Controllers/Api/SiteApiController.php`.

The API follows the Dhru Fusion / GSM-style convention, so existing reseller
panels can integrate with little or no change.

## Endpoint

```
POST https://YOUR-DOMAIN/api/index.php
Content-Type: application/x-www-form-urlencoded
```

## Required parameters (every request)

| Param           | Value                                                            |
| --------------- | ---------------------------------------------------------------- |
| `username`      | Your account email                                               |
| `apiaccesskey`  | Your API access key (from the API settings page)                 |
| `requestformat` | `JSON`                                                           |
| `action`        | `imeiservicelist` \| `accountinfo` \| `placeimeiorder` \| `getimeiorder` |

## Authentication rules

- The `apiaccesskey` must belong to the given `username`.
- The first call **binds your account to the calling IP**; later calls from a
  different IP return `403`. Ask the admin to reset the bound IP if it changes.
- `imeiservicelist` is rate-limited to **once per 5 minutes per IP**.

## Response envelope

```jsonc
// success
{ "SUCCESS": [ { /* ...action specific... */ } ] }

// error
{ "ERROR": [ { "MESSAGE": "..." } ] }
```

## Actions

### `accountinfo`
Returns the account balance, email and currency.
```json
{ "SUCCESS": [ { "message": "Your Account Info",
  "AccountInfo": { "credit": "$10.00", "creditraw": 10, "mail": "you@mail.com", "currency": "USD" } } ] }
```

### `imeiservicelist`
Returns every active service grouped by service group plus your account info.
Each service exposes `SERVICEID, SERVICETYPE, MINQNT, MAXQNT, SERVICENAME,
CREDIT, TIME` and a `Requires.Custom` array of input fields.

### `placeimeiorder`
Send an XML `parameters` string:
```xml
<PARAMETERS>
  <ID>123</ID>                          <!-- service_id (required) -->
  <QNT>1</QNT>                          <!-- optional, default 1 -->
  <IMEI>356xxxxxxxxxxxx</IMEI>          <!-- required if the service needs IMEI -->
  <CUSTOMFIELD>base64(json fields)</CUSTOMFIELD>
</PARAMETERS>
```
Returns: `{ "SUCCESS": [ { "MESSAGE": "Order received", "REFERENCEID": 456 } ] }`

### `getimeiorder`
Send `parameters = <PARAMETERS><ID>orderReferenceId</ID></PARAMETERS>`.
Returns the order status:

| STATUS | Meaning     |
| ------ | ----------- |
| 0      | Waiting     |
| 1      | In process  |
| 3      | Rejected    |
| 4      | Success     |

## Example (cURL)

```bash
curl -X POST https://YOUR-DOMAIN/api/index.php \
  -d "username=you@mail.com" \
  -d "apiaccesskey=YOUR_KEY" \
  -d "requestformat=JSON" \
  -d "action=accountinfo"
```
