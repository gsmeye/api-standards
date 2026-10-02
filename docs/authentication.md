# Authentication

Every request carries four form fields:

| Field | Required | Value |
|---|---|---|
| `username` | yes | The email address of your account on the site |
| `apiaccesskey` | yes | Your API key, from **Dashboard → API Settings** |
| `requestformat` | yes | Always `JSON` |
| `action` | yes | One of the [actions](../README.md#actions) |

Actions that need more input take a fifth field, `parameters`. Its format is
described on each action's page.

## Key and username

The key identifies your account and the username must be that account's email.
A wrong key, a key that belongs to someone else, or a mismatched email all
return the same answer, so a failed call never tells a stranger which half was
right:

```json
{ "ERROR": [ { "MESSAGE": "Invalid API access key or username" } ], "apiversion": "1.0" }
```

HTTP status `401`.

Keys are stored encrypted on the server and compared in constant time. Treat
yours like a password: keep it out of client-side code, browser extensions and
public repositories. If it leaks, generate a new one in API Settings — the old
key stops working at once.

## IP binding

The **first successful call** with a key binds that key to the IP address it
came from. From then on, a call from any other IP is refused:

```json
{ "ERROR": [ { "MESSAGE": "Unauthorized IP address" } ], "apiversion": "1.0" }
```

HTTP status `403`.

What this means in practice:

- Make your first call from the server that will use the key in production —
  not from your laptop to "test it quickly".
- If your server's IP changes (new VPS, new hosting, a dynamic IP), press
  **Reset IP** in **Dashboard → API Settings** (or ask the site admin to). The
  next successful call then binds the new IP.
- **API Settings** shows the IP your key is currently bound to.
- A panel behind a load balancer or a pool of outbound IPs needs a single
  egress IP, or every request from the other IPs fails.

## Limits

| Limit | Scope |
|---|---|
| 120 requests per minute | Per calling IP, across all actions |
| `imeiservicelist` once per 5 minutes | Per calling IP |

Both answer HTTP `429`. See [Errors](errors.md#rate-limits).

## Maintenance mode

While the site is in maintenance mode the API is offline with it. Retry later
rather than treating the answer as a rejected order.
