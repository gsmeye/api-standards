# `accountinfo`

Your balance, email and currency.

## Request

| Field | Value |
|---|---|
| `action` | `accountinfo` |

Plus the [authentication fields](../authentication.md). No `parameters`.

## Response

```json
{
  "SUCCESS": [
    {
      "message": "Your Account Info",
      "AccountInfo": {
        "credit": "৳2,794.00",
        "creditraw": 2794,
        "mail": "you@example.com",
        "currency": "BDT"
      }
    }
  ],
  "apiversion": "1.0"
}
```

| Field | Meaning |
|---|---|
| `credit` | Balance formatted for display, with the currency symbol and thousands separators |
| `creditraw` | Balance as a number, two decimals — use this one for arithmetic |
| `mail` | Account email |
| `currency` | ISO currency code of your account |

The balance is converted into **your account's currency** at the site's rate.

> **Currency note.** Service prices (`CREDIT` in
> [`imeiservicelist`](imeiservicelist.md)) are in the **site's base currency**,
> not converted. When your account currency differs from the base currency,
> convert before comparing a price with `creditraw`. Most accounts use the base
> currency, where the two match.
