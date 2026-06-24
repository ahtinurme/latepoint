# LatePoint Addon - Stebby

Accept LatePoint booking payments through [Stebby](https://stebby.eu/) using the
Stebby Cashier API (v4). Customers choose **Stebby** as a payment method when
booking, identify themselves through Stebby, and a redeemable Stebby **ticket**
on their account is used to pay.

The redeemed ticket's value is deducted from the booking total. Any remaining
amount is left as an outstanding balance on the order to be collected separately
(locally / via invoice).

## How it works

- Stebby is registered as a LatePoint payment processor (`Settings → Payments`).
  Enable it and enter your **API key** (always the live Stebby API).
- Stebby is a redirect processor. On *Confirm booking* the addon requests an
  authorization URL (`identify/request-token`) and sends the customer to Stebby
  to log in. Stebby returns them to the addon, which exchanges the reference for
  a token (`identify/token`).
- With that token the addon lists the customer's redeemable tickets
  (`tickets`) and redeems the first usable one (`tickets/use`) — server-side,
  during conversion of the booking. Tickets are not tied to a specific service.
- The same flow is available when paying an existing invoice/order balance (the
  LatePoint transaction-intent flow).

> Ticket redemption (`tickets/use`) cannot be reverted via the API.

## Requirements

- LatePoint 5.x
- A Stebby Service Provider account with a configured Point of Sale and an API
  key (the default `TOKEN` identification context is sufficient).

## API reference

Stebby Cashier API documentation: https://api.stebby.eu/docs
