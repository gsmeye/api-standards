# Input fields

Each service declares the inputs an order needs: an IMEI, a serial number, a
username, a model, a link to a photo, and so on. The catalogue publishes them;
an order sends values back for them.

## How the catalogue describes inputs

### IMEI services

An IMEI service's **first** input is its identifier. It is published as a
`CUSTOM` block, and any further inputs are published under `Requires.Custom`:

```json
"5512": {
  "SERVICEID": 5512,
  "SERVICETYPE": "IMEI",
  "SERVER": "0",
  "CUSTOM": {
    "allow": "1",
    "bulk": "1",
    "customname": "IMEI",
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
```

| `CUSTOM` key | Meaning |
|---|---|
| `allow` | Always `"1"` — the identifier box is present |
| `customname` | The identifier's label, e.g. `IMEI`, `imei`, `Serial Number`, `IMEI / SN` |
| `bulk` | `"1"` when the service accepts many identifiers in one order form (one per line) |
| `maxlength` | Maximum characters in the identifier box |

Read the label from `customname` instead of assuming `IMEI`: a service may ask
for a serial number or anything else in that box. Whatever the label, the value
travels in `<IMEI>` (see below).

### SERVER and REMOTE services

There is no identifier box. Every input is listed under `Requires.Custom`, in
order, and `SERVER` is `"1"` (SERVER) or `"2"` (REMOTE).

### `required`

`"on"` means the order is refused when the field is empty. Anything else means
optional.

## How an order sends values

| Where | What goes there |
|---|---|
| `<IMEI>` | IMEI services: the identifier (the `CUSTOM` box). SERVER/REMOTE: optional, see below |
| `<CUSTOMFIELD>` | `base64(JSON)` of every other field, keyed by its exact `fieldname` |

```php
$custom = base64_encode(json_encode([
    'Picture on sign-in page' => 'https://i.imgur.com/abc123.jpg',
]));
```

```xml
<PARAMETERS>
  <ID>5512</ID>
  <IMEI>356789104512345</IMEI>
  <CUSTOMFIELD>eyJQaWN0dXJlIG9uIHNpZ24taW4gcGFnZSI6Imh0dHBzOi8vaS5pbWd1ci5jb20vYWJjMTIzLmpwZyJ9</CUSTOMFIELD>
</PARAMETERS>
```

Rules the server applies:

1. **Keys are matched exactly**, case and spaces included. Copy `fieldname`
   from the catalogue rather than retyping it — `"Model"` and `"model "` are
   different fields.
2. **IMEI services:** the identifier is always read from `<IMEI>`. A value for
   it inside `CUSTOMFIELD` is ignored.
3. **SERVER/REMOTE services:** `CUSTOMFIELD` is authoritative. As a fallback for
   clients that put their single box in `<IMEI>`, an empty **first** field is
   filled from `<IMEI>`.
4. `CUSTOMFIELD` may also be sent as plain JSON instead of base64 JSON; both
   are accepted.
5. Keys that do not match a declared field are ignored.
6. The first required field left empty fails the order with
   `Missing required field: <fieldname>`.

## Keep your copy in sync

Field names, required flags and the identifier label can change when the site
admin edits a service. Re-run `imeiservicelist` regularly (no more than once
every 5 minutes) and refresh your saved field lists, or orders start failing
with `Missing required field`.
