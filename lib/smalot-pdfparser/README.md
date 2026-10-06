# smalot/pdfparser (bundled)

Third-party library used to read text and form-field values from uploaded PDF
referral forms. See `src/ReferralInbox/Document/PdfTextReader.php`.

| | |
| --- | --- |
| Library | [smalot/pdfparser](https://github.com/smalot/pdfparser) |
| Version | `v2.12.5` (unmodified copy of the `src/` directory) |
| Licence | LGPL-3.0 — see `LICENSE.txt` in this folder |
| Needs | PHP `mbstring`, `zlib`, `iconv` |

The files in `src/` are an unmodified copy of the upstream release. They are
loaded on demand by `lib/smalot-pdfparser/autoload.php`, only when a PDF is
being read, and only if another plugin has not already loaded the same library.

To update: replace `src/` with the `src/` directory of a newer upstream release
and change the version above. Do not edit the library files in place.
