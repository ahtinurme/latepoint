# LatePoint Addon - EveryPay

A LatePoint add-on that lets you accept booking payments through the
[EveryPay](https://every-pay.com/) payment gateway, without modifying LatePoint
itself.

## Requirements

- WordPress
- The [LatePoint](https://latepoint.com/) plugin, installed and active
- An EveryPay merchant account

## Installation

1. Copy the `latepoint-addon-everypay` folder into your `wp-content/plugins/`
   directory.
2. Activate **LatePoint Addon - EveryPay** from the WordPress Plugins screen.
3. Make sure the LatePoint plugin is active — the add-on only loads when
   LatePoint is present.

## Setup

Once the add-on is active, configure it with the credentials from your EveryPay
account:

- **API username** — the API username issued by EveryPay for your account.
- **API secret** — the matching API secret. Keep this value private.
- **Processing account** — the EveryPay processing account name used to route
  transactions.
- **Callback URL** — the URL EveryPay calls to notify the store of a payment
  result. Register this URL in the EveryPay portal so payment statuses are
  confirmed reliably.

Use the EveryPay test environment to verify the integration before switching to
live credentials.

## License

Distributed under the same terms as the LatePoint plugin.
