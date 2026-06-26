# LatePoint Addon - Stebby

Accept LatePoint booking payments paid with a [Stebby](https://stebby.eu/)
voucher. The customer chooses **Stebby** as a payment method and enters their
**voucher code**.

There is no API integration: the code is only format-checked (`SB`/`VV` prefix +
10 alphanumerics) and saved to the booking comments. Staff redeem the voucher
manually in Stebby.

The voucher covers the booked session. Any remaining amount is left as an
outstanding balance on the order to be collected separately.

## How it works

- Stebby is registered as a LatePoint payment processor (`Settings → Payments`);
  enable it there. No configuration is needed.
- On *Confirm booking* the customer's voucher code is submitted with the form.
  During conversion the code is format-checked and recorded; the booking goes
  through and the code is appended to the order and booking comments.
- The same flow is available when paying an existing invoice/order balance (the
  LatePoint transaction-intent flow).

## Requirements

- LatePoint 5.x
