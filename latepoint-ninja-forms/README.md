# LatePoint Addon — Ninja Forms

Attaches a [Ninja Forms](https://ninjaforms.com/) form to LatePoint services. After a customer
books a service, the link to its form is delivered in the booking confirmation email and shown on
the confirmation screen. When the customer submits the form, the admin and customer are notified and
the submission is stored against the booking — visible both in the customer account and in the
LatePoint admin order view. A single Ninja Form is shared by all services, while **whether** booking
a given service delivers the form link is configurable per service.

This is a standalone WordPress plugin built on LatePoint's addon hook system — it does **not** modify
LatePoint or Ninja Forms core, so both can update freely. It is modelled on the official
[latepoint-addon-starter](https://github.com/brainstormforce/latepoint-addon-starter) and the bundled
`latepoint-mailchimp` addon.

## Requirements

- LatePoint (active)
- Ninja Forms (active)

## How it works

```
Book service ──> confirmation email + screen show form link
                 (email link auto-appended; also available as {{order_ninja_form_link}})
                                   │
                  customer opens link ──> [latepoint_ninja_form] page renders the
                                          form (order id + token auto-filled
                                          into hidden fields)
                                   │
                  customer submits ──> ninja_forms_after_submission
                                   │
            ┌──────────────────────┼──────────────────────┐
       store on order meta    store on customer meta    email admin + customer
            │                      │
   admin order view        customer dashboard
```

The link carries the order id and an HMAC token (`token_for_order()`), so links can't be guessed or
enumerated. One global form (set in **LatePoint → Ninja Forms**) is shared by every service; the link
is delivered only when at least one booked service has its **Send Ninja Form link** toggle on
(defaults to on per service).

## Setup

1. **Build the Ninja Form.** Add two **Hidden** fields with the field keys `lp_nf_order` and
   `lp_nf_token`. The addon auto-populates them when the form is opened through the link, and reads
   them back on submission to attach the entry to the booking.
2. **Configure the addon.** In **LatePoint → Ninja Forms**, set the **Ninja Form ID** (the numeric
   form id used for all services; leave empty to disable), select the form page (below), and set the
   admin notification email / toggles.
3. **Create the form page.** Make a WordPress page containing the shortcode `[latepoint_ninja_form]`
   and select it in the settings.
4. **Per-service toggle.** Each LatePoint Service edit page has a **Send Ninja Form link for this
   service** switch (on by default). Turn it off for services that should not deliver the form.
5. **Email link.** The link is **auto-appended** to the customer booking email when a form is set
   (the *Auto-append* toggle). To place it yourself instead, turn that off and insert
   `{{order_ninja_form_link}}` (HTML link) or `{{order_ninja_form_url}}` (raw URL) into your
   *Booking Created → Customer* email template. The on-screen confirmation link is always automatic.

## Smart variables

Usable in any LatePoint notification template (email/SMS) and in the confirmation message:

| Variable | Output |
| --- | --- |
| `{{order_ninja_form_url}}` | Raw form URL for the order |
| `{{order_ninja_form_link}}` | `<a>` link to the form |

Both resolve to empty when no form id or no form page is set.

## Where data is stored

- **Submissions are native Ninja Forms `nf_sub` posts** — the single source of truth, also visible
  under Ninja Forms → Submissions. Each is linked to LatePoint by postmeta FKs stamped on the sub:
  `_latepoint_order_id` and `_latepoint_customer_id`. The admin order view and customer dashboard
  list them by querying `nf_sub` on those keys — no denormalised copy.
- **Service meta** `ninja_form_enabled` — per-service `on`/`off` link toggle (defaults to on).
- **Settings** `ninja_forms_form_id`, `ninja_forms_page_id`, `ninja_forms_admin_email`,
  `ninja_forms_notify_customer`, `ninja_forms_notify_admin`, `ninja_forms_auto_append_email`.

## Files

```
latepoint-ninja-forms.php                     Main class — hook wiring only
lib/helpers/ninja_forms_helper.php            OsNinjaFormsHelper — all logic
lib/controllers/ninja_forms_controller.php    Admin settings page
lib/views/ninja_forms/settings.php            Settings + setup instructions
public/stylesheets/...-front.css              Front-end styling
```

## Note on testing

Ninja Forms is not installed in this repository, so the Ninja-Forms-side hooks
(`ninja_forms_render_default_value`, `ninja_forms_after_submission`, the `[ninja_form]` shortcode,
and the `$form_data` shape consumed in `handle_form_submission()`) are coded against Ninja Forms'
documented API and must be verified on a live site with both plugins active.
