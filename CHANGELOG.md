# Changelog

Changes to the GSM EYE Reseller API as seen by a client. The API version in
responses stays `1.0`: everything below is backward compatible.

## 2026-10 — documentation rewrite

- Documentation split into per-action pages, with field rules, error
  reference, and PHP / Python / Node.js / cURL / Postman examples.
- The reference controller source was removed from this repository; the docs
  describe the behaviour, which is what clients depend on.

## Behaviour added since the first publication (June 2026)

- **Bulk actions.** `placeimeiorderbulk` (alias `placebulkorder`) and
  `getimeiorderbulk`.
- **Service IDs.** `SERVICEID` is the site's own, unique service ID. Supplier
  IDs saved by older integrations are still accepted until they re-sync.
- **Service types.** `SERVER` now reports the real type (`0` IMEI, `1` SERVER,
  `2` REMOTE) instead of always `0`, so Dhru-style clients draw the right input
  boxes for SERVER and REMOTE services.
- **Identifier box.** IMEI services publish their identifier as a `CUSTOM` block
  with the admin's own label (`customname`) and a `bulk` flag that follows the
  service's bulk setting; remaining inputs are published under `Requires.Custom`.
- **Order values.** For SERVER/REMOTE services an empty first field is filled
  from `<IMEI>`, for clients that send their single box there.
- **Order checks.** Disabled services, services without a price and blacklisted
  identifiers are refused before any charge, with a message saying which.
- **Instant fulfilment.** Stock-code and instant-link services settle during
  the order request.
- **Statements.** API orders now appear in the account statement.
- **Security.** API keys are stored encrypted and compared in constant time.
- **Errors.** Error responses carry a 4xx HTTP status instead of `200`.
- **Headers.** Every response carries `gsmeye-api-version` and no-cache headers.
- **Limits.** 120 requests per minute per IP across all actions, in addition to
  the existing once-per-5-minutes limit on `imeiservicelist`.
