# `placeimeiorderbulk`

Place many orders in one call. Each row is handled on its own: one bad row
fails alone and the rest still go through.

`placebulkorder` (the GSM Theme spelling) is an alias and behaves identically.

## Request

| Field | Value |
|---|---|
| `action` | `placeimeiorderbulk` or `placebulkorder` |
| `parameters` | `base64(JSON)` of the rows, see below |

Plus the [authentication fields](../authentication.md).

The JSON is an object keyed by **your own reference** for each row. The
reference comes back on that row's result, so use something you can match —
your own order ID, for example.

```json
{
  "A-1001": { "ID": "6904", "IMEI": "356789104512345", "CUSTOMFIELD": "eyJQaWN0dXJlIG9uIHNpZ24taW4gcGFnZSI6Imh0dHBzOi8vLi4uIn0=" },
  "A-1002": { "ID": "6904", "IMEI": "356789104599999", "CUSTOMFIELD": { "Picture on sign-in page": "https://..." } },
  "A-1003": { "ID": "812", "QNT": "10", "CUSTOMFIELD": { "Username": "shop-42" } }
}
```

| Key | Required | Meaning |
|---|---|---|
| `ID` | yes | `SERVICEID` |
| `QNT` | no | Quantity, default `1` |
| `IMEI` | IMEI services | The identifier |
| `CUSTOMFIELD` | when needed | The other inputs: `base64(JSON)` string, or a plain JSON object |

```php
$parameters = base64_encode(json_encode($rows));
```

## Response

HTTP `200`, with one result per row under `SUCCESS`, keyed by your reference:

```json
{
  "SUCCESS": {
    "A-1001": { "status": "success", "message": "Order received", "referenceid": 98214 },
    "A-1002": { "status": "error", "message": "Missing required field: Picture on sign-in page" },
    "A-1003": { "status": "success", "message": "Order received", "referenceid": 98215 }
  },
  "apiversion": "1.0"
}
```

| Key | Meaning |
|---|---|
| `status` | `success` or `error` |
| `message` | `Order received`, or why the row failed |
| `referenceid` | On success: the order ID for [`getimeiorder`](getimeiorder.md) / [`getimeiorderbulk`](getimeiorderbulk.md) |

Only the rows that succeed are charged. Row errors use the same messages as
[`placeimeiorder`](placeimeiorder.md#errors), plus `Missing service ID` for a
row without `ID`.

If `parameters` itself is unreadable, the whole call fails with HTTP `400`:
`Parameters must be valid base64-encoded JSON`.

## Tips

- Rows are processed in order, each in its own transaction. A balance that runs
  out mid-batch fails only the rows it can no longer pay for, with
  `Insufficient balance`; earlier rows stay placed.
- Keep batches to a few hundred rows so a single request stays well inside
  your HTTP client's timeout.
