# LatePoint Addon - Stebby

Accept LatePoint booking payments through [Stebby](https://stebby.eu/) using the
Stebby Cashier **API v3**. The customer chooses **Stebby** as a payment method,
enters their personal **ID code**, and a redeemable Stebby **ticket** on their
account is used to pay.

The redeemed ticket covers the booked session. Any remaining amount is left as an
outstanding balance on the order to be collected separately (locally / via
invoice).

## How it works

- Stebby is registered as a LatePoint payment processor (`Settings → Payments`).
  Enable it and enter your **API key** (live Stebby API).
- Requests use the v3 API (`Api-Key` + `Api-Version: 3` headers) against
  `https://api.stebby.eu`. There is **no redirect / token flow** — v3 identifies
  the customer directly by ID code.
- On *Confirm booking* the customer's ID code is submitted with the form. During
  conversion the addon looks up their redeemable tickets (`POST /api/getTickets`
  with `idcode`) and redeems the first one (`POST /api/useTicket` with
  `ticket_code`), server-side. Tickets are not tied to a specific service.
- The same flow is available when paying an existing invoice/order balance (the
  LatePoint transaction-intent flow).

> Ticket redemption (`useTicket`) cannot be reverted via the API.

## Requirements

- LatePoint 5.x
- A Stebby Service Provider account with a configured Point of Sale and an API
  key enabled for the v3 API.

## API reference

Stebby Cashier API v3 documentation: https://app.stebby.eu/api
