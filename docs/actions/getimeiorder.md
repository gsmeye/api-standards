# `getimeiorder`

Status and result of one order.

## Request

| Field | Value |
|---|---|
| `action` | `getimeiorder` |
| `parameters` | `<PARAMETERS><ID>98213</ID></PARAMETERS>` |

`ID` is the `REFERENCEID` that [`placeimeiorder`](placeimeiorder.md) returned.
Plus the [authentication fields](../authentication.md).

## Response

```json
{
  "ID": "98213",
  "SUCCESS": [
    {
      "IMEI": "",
      "STATUS": 4,
      "CODE": "Unlocked successfully. Reboot the device.",
      "COMMENTS": ""
    }
  ],
  "apiversion": "1.0"
}
```

| Field | Meaning |
|---|---|
| `STATUS` | See [Status codes](#status-codes) |
| `CODE` | The result: unlock code, reply text, or rejection reason. Empty until there is one |
| `IMEI`, `COMMENTS` | Kept for Dhru compatibility, always empty |

## Status codes

| `STATUS` | Meaning | Final? |
|---|---|---|
| `0` | Waiting — accepted, not started yet | no |
| `1` | In process | no |
| `3` | Rejected — refunded; reason in `CODE` | **yes** |
| `4` | Success — result in `CODE` | **yes** |

Stop polling an order once it reaches `3` or `4`.

## Polling

- Poll waiting orders every few minutes, not every second. Instant services
  usually finish within one or two polls; others can take hours or days — see
  the service's `TIME`.
- Have many open orders? Use [`getimeiorderbulk`](getimeiorderbulk.md): one
  call for all of them, and far less load on the 120-requests-per-minute limit.

## Errors

You can only read orders placed by your own account.

| Message | Status | Cause |
|---|---|---|
| `Missing parameters` | 400 | No `parameters` field |
| `Service ID is required` | 400 | `<ID>` missing or empty |
| `Invalid service ID` | 404 | No order with that ID on your account |
