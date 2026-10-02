# `imeiservicelist`

The full catalogue: every active service, grouped, with **your** price and the
inputs each one needs. Despite the name it lists IMEI, SERVER and REMOTE
services together.

**Limit:** once per 5 minutes per IP. Cache the result and re-sync on a timer.

## Request

| Field | Value |
|---|---|
| `action` | `imeiservicelist` |

Plus the [authentication fields](../authentication.md). No `parameters`.

## Response

```json
{
  "SUCCESS": [
    {
      "MESSAGE": "IMEI Service List",
      "LIST": {
        "Tecno / Infinix / iTel": {
          "GROUPNAME": "Tecno / Infinix / iTel",
          "GROUPTYPE": "IMEI",
          "SERVICES": {
            "6904": {
              "SERVICEID": 6904,
              "SERVICETYPE": "IMEI",
              "QNT": 0,
              "SERVER": "0",
              "MINQNT": 0,
              "MAXQNT": 0,
              "SERVICENAME": "TECNO - INFINIX - ITEL ID Removal",
              "CREDIT": 12.5,
              "TIME": "1-30 Minutes",
              "INFO": "",
              "CUSTOM": {
                "allow": "1",
                "bulk": "0",
                "customname": "imei",
                "custominfo": "",
                "customlen": "1",
                "maxlength": "300",
                "regex": "",
                "isalpha": "1"
              },
              "Requires.Custom": [
                {
                  "type": "serviceimei",
                  "fieldname": "Picture on sign-in page",
                  "fieldtype": "text",
                  "description": "",
                  "fieldoptions": "",
                  "regexpr": "",
                  "adminonly": "",
                  "required": "on"
                }
              ]
            }
          }
        },
        "Server Activations": {
          "GROUPNAME": "Server Activations",
          "GROUPTYPE": "SERVER",
          "SERVICES": {
            "812": {
              "SERVICEID": 812,
              "SERVICETYPE": "SERVER",
              "QNT": 1,
              "SERVER": "1",
              "MINQNT": 1,
              "MAXQNT": 50,
              "SERVICENAME": "Tool Credits",
              "CREDIT": 1.2,
              "TIME": "Instant",
              "INFO": "",
              "Requires.Custom": [
                { "type": "serviceimei", "fieldname": "Username", "fieldtype": "text", "description": "", "fieldoptions": "", "regexpr": "", "adminonly": "", "required": "on" }
              ]
            }
          }
        }
      },
      "ACCOUNTINFO": { "credit": "$25.40", "creditraw": 25.4, "mail": "you@example.com", "currency": "USD" }
    }
  ],
  "apiversion": "1.0"
}
```

`LIST` is keyed by group name; `SERVICES` is keyed by service ID.

## Service fields

| Field | Meaning |
|---|---|
| `SERVICEID` | The ID to send back in `<ID>` when ordering |
| `SERVICETYPE` | `IMEI`, `SERVER` or `REMOTE` |
| `SERVER` | Dhru-style type flag: `"0"` IMEI, `"1"` SERVER, `"2"` REMOTE |
| `SERVICENAME` | Display name |
| `CREDIT` | **Your** price for one unit, after your group and personal pricing, in the site's base currency |
| `TIME` | Delivery time as the admin wrote it, free text |
| `QNT` | `1` when the service is ordered in quantities, else `0` |
| `MINQNT` / `MAXQNT` | Allowed quantity range when `QNT` is `1`; both `0` otherwise |
| `CUSTOM` | IMEI services only: the identifier box — see [Input fields](../fields.md) |
| `Requires.Custom` | Additional inputs, in display order — see [Input fields](../fields.md) |
| `INFO` | Reserved, currently empty |

An order costs `CREDIT × QNT`.

## Notes

- Only **active** services in groups that have at least one active service are
  listed. A service you saved earlier can disappear from the list when the admin
  disables it; ordering it then fails with
  `"<service>" is not available for ordering right now.`
- Service IDs are unique on the site and stable. Older integrations that saved
  the site's upstream supplier IDs keep working until they re-sync, but new code
  should always use `SERVICEID`.
- The list carries an `ACCOUNTINFO` block identical to
  [`accountinfo`](accountinfo.md), so one call gives both.
