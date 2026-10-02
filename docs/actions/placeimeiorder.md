# `placeimeiorder`

Place one order. Works for IMEI, SERVER and REMOTE services.

## Request

| Field | Value |
|---|---|
| `action` | `placeimeiorder` |
| `parameters` | XML, see below |

Plus the [authentication fields](../authentication.md).

```xml
<PARAMETERS>
  <ID>6904</ID>
  <QNT>1</QNT>
  <IMEI>356789104512345</IMEI>
  <CUSTOMFIELD>base64(JSON)</CUSTOMFIELD>
</PARAMETERS>
```

| Element | Required | Meaning |
|---|---|---|
| `ID` | yes | `SERVICEID` from [`imeiservicelist`](imeiservicelist.md) |
| `QNT` | no | Quantity, a positive whole number. Anything else counts as `1` |
| `IMEI` | IMEI services | The identifier — whatever `CUSTOM.customname` calls it |
| `CUSTOMFIELD` | when the service has more inputs | `base64(JSON)` of the other inputs, keyed by exact `fieldname` |

How values are matched to inputs is described in [Input fields](../fields.md).

### Examples

**IMEI service with one extra field**

```php
$parameters = '<PARAMETERS>'
    . '<ID>6904</ID>'
    . '<IMEI>356789104512345</IMEI>'
    . '<CUSTOMFIELD>' . base64_encode(json_encode([
        'Picture on sign-in page' => 'https://i.imgur.com/abc123.jpg',
    ])) . '</CUSTOMFIELD>'
    . '</PARAMETERS>';
```

**SERVER service, 10 credits**

```php
$parameters = '<PARAMETERS>'
    . '<ID>812</ID>'
    . '<QNT>10</QNT>'
    . '<CUSTOMFIELD>' . base64_encode(json_encode(['Username' => 'shop-42'])) . '</CUSTOMFIELD>'
    . '</PARAMETERS>';
```

Escape XML special characters (`&`, `<`, `>`) in `<IMEI>`; the base64 in
`<CUSTOMFIELD>` needs no escaping.

## Response

```json
{
  "SUCCESS": [ { "MESSAGE": "Order received", "REFERENCEID": 98213 } ],
  "apiversion": "1.0"
}
```

Store `REFERENCEID` — it is the order ID for
[`getimeiorder`](getimeiorder.md).

## What happens on the server

1. The service is looked up and checked: it must exist, be enabled and have a
   price for your account.
2. Every required input must have a value, and the identifier must not be on
   the site's blacklist for this service.
3. `CREDIT × QNT` is taken from your balance (or from overdue credit, when the
   admin allows it for your account) and the order is created. The charge
   appears in your account statement.
4. Fulfilment starts:
   - **Stock-code services** deliver at once. The order can already be
     `4` (success) on the first [`getimeiorder`](getimeiorder.md) call, with
     the code in `CODE`.
   - **Instant link services** are settled within the same request.
   - **Everything else** is queued for processing and moves through the
     [status codes](getimeiorder.md#status-codes).

A request refused at steps 1–3 is **not charged**. An order that is accepted and
then rejected is refunded in full, automatically.

## Errors

See [Errors](../errors.md#orders). The most common:

| Message | Fix |
|---|---|
| `Missing required field: <fieldname>` | Send that field — check the name against the catalogue |
| `Invalid service ID` | Re-sync the catalogue; the ID is wrong or the service is gone |
| `"<service>" is not available for ordering right now.` | The admin disabled it; drop or hide it in your panel |
| `Insufficient balance` | Top up |
