# `getimeiorderbulk`

Status and result of many orders in one call — the right way to poll.

## Request

| Field | Value |
|---|---|
| `action` | `getimeiorderbulk` |
| `parameters` | `base64(JSON)` of the orders, see below |

Plus the [authentication fields](../authentication.md).

The JSON is an object keyed by **your own reference**, each row holding the
order's `ID` (the `REFERENCEID` you got when placing it):

```json
{
  "A-1001": { "ID": "98214" },
  "A-1003": { "ID": "98215" }
}
```

```php
$parameters = base64_encode(json_encode($rows));
```

## Response

```json
{
  "A-1001": { "SUCCESS": [ { "STATUS": 4, "CODE": "Unlocked successfully." } ] },
  "A-1003": { "SUCCESS": [ { "STATUS": 1, "CODE": "" } ] },
  "apiversion": "1.0"
}
```

Each reference maps to a `SUCCESS` block with the same `STATUS` and `CODE` as
[`getimeiorder`](getimeiorder.md#status-codes).

> An order ID that is missing, wrong, or belongs to another account comes back
> as `STATUS: 0` with an empty `CODE` — the same as a waiting order. Only send
> IDs that `placeimeiorder` / `placeimeiorderbulk` returned to you, and if an
> order stays at `0` far beyond its `TIME`, check it once with `getimeiorder`,
> which answers `Invalid service ID` for an ID that is not yours.

If `parameters` is unreadable, the call fails with HTTP `400`:
`Parameters must be valid base64-encoded JSON`.
