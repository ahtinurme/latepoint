# LatePoint Addon - Stebby

Accept LatePoint booking payments through [Stebby](https://stebby.eu/) using the
Stebby Cashier API (v4). Customers choose **Stebby** as a payment method when
booking and then either:

1. **Pay with their Stebby account** — they enter their personal ID code and
   Stebby is charged for whatever portion of the booking it can cover.
2. **Redeem a voucher** — if they already bought the service on Stebby, they
   enter the voucher (ticket) code, which is validated and marked as used.

In both cases the amount Stebby covers is deducted from the booking total. Any
remaining amount is left as an outstanding balance on the order to be collected
separately (locally / via invoice).

> **Note:** The "pay with my Stebby account" (ID-code) flow is off by default —
> only voucher redemption is offered. It needs the `EST_PIN` context enabled on
> your Stebby API key. Once Stebby has enabled it, turn on **Allow paying with a
> Stebby account (ID code)** under `Settings → Payments → Stebby`.

## How it works

- Stebby is registered as a LatePoint payment processor (`Settings → Payments`).
  Enable it and enter your **Live** and **Sandbox** API keys. The sandbox key is
  used whenever LatePoint payments are in test mode.
- Each LatePoint service that can be paid for with Stebby must be mapped to a
  Stebby purchasable. Edit a service and enter its **Stebby Reference Code**
  (the reference code configured for the matching service in your Stebby Point
  of Sale). The settings page lists the available reference codes from your
  Stebby account for convenience.
- On the payment step the customer picks a flow, enters their ID code or voucher
  code, and clicks *Check*. The addon calls Stebby's `calculate` (or ticket
  lookup) and shows how much Stebby will cover and what (if anything) remains.
- When the booking is submitted, the charge is finalized server-side: a fresh
  `calculate` + `purchase` for the account flow, or `tickets/use` for the
  voucher flow.
- The same two flows are available when paying an existing invoice/order
  balance (the LatePoint transaction-intent flow).

## Reliability

- If a Stebby purchase succeeds but the LatePoint order/transaction then fails
  to be created, the account-flow purchase is automatically reverted
  (`purchase/revert`) at the end of the request. Voucher (`tickets/use`)
  redemptions cannot be reverted via the API, so those are logged for manual
  follow-up instead.

## Requirements

- LatePoint 5.x
- A Stebby Service Provider account with a configured Point of Sale and an API
  key. To use the ID-code (`EST_PIN` / `LVA_PIN` / `LTU_PIN`) identification
  contexts, ask Stebby to enable them for your API key — keys are restricted to
  `TOKEN` by default.

## API reference

Stebby Cashier API documentation: https://api.stebby.eu/docs
