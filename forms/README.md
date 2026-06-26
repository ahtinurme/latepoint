# Handwritten consent forms → LatePoint customer submissions

Imports the scanned paper consent forms (folders `1`–`6`) as LatePoint form
submissions, one per customer, with the original scan attached. Submissions
render in the customer dashboard via the `latepoint-ninja-forms` addon
("Your Form Submissions").

## Source layout
- Folders **1 + 2** = one 2-page form (page 2 = page-1 image number + 1)
- Folders **3 + 6** = one 2-page form (same pairing)
- Folder **4** = single-page form (with e-mail/phone)
- Folder **5** = single-page form (with e-mail/phone)

## Steps (run on the server — needs DB + WordPress)
The one script parses the transcripts, matches customers and imports.
```bash
# dry run: reports matched / ambiguous / unmatched customers, writes nothing
php wp-content/plugins/forms/import-consent-forms.php

# apply once the dry-run report looks right
php wp-content/plugins/forms/import-consent-forms.php --commit
```

Matching: e-mail first (folders 4 & 5), then normalized full name
(case / word-order / Estonian-diacritic insensitive). Ambiguous and unmatched
names are listed for manual handling — nothing is guessed.

Idempotent: re-running skips entries already imported (`sub_id = paper_IMG_xxxx`)
and reuses already-uploaded scans (attachment meta `_yumefit_consent_src`).
