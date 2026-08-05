# Troubleshooting — JM Referral System v1.0.0

Common issues and checks. Prefer staging reproduction first.

---

## SMTP / notifications

| Symptom | Checks |
| --- | --- |
| No emails | Confirm SMTP plugin / host mail; check spam; test `wp_mail` |
| Public confirmation missing | Referral may still exist in admin list; fix mail then resend manually if needed |
| Template errors | WP_DEBUG log may show generic template failure (paths not exposed to users) |

---

## Uploads / private documents

| Symptom | Checks |
| --- | --- |
| Upload fails | PHP `upload_max_filesize` / `post_max_size`; plugin type/size limits |
| Download 403 / fail | User capability; AccessPolicy; nonce; logged-in session |
| Direct URL to file works | Private dir protection missing (nginx); migrate away from legacy Media Library URLs |
| Migration stuck | Re-run batch; already-private rows skipped |

---

## Permissions

| Symptom | Checks |
| --- | --- |
| Menu missing | User lacks `jmrs_*` capability / wrong role |
| Empty list for Support Worker | Expected if unassigned; check assignment / care team |
| Cannot edit archived | Expected — restore first if permitted |

---

## Staff portal

| Symptom | Checks |
| --- | --- |
| 404 on `/staff-portal/` | Enable portal in Settings; save to flush rewrites; or **Settings → Permalinks → Save** |
| Login loop | Clear cookies; ensure `redirect_to` is same-site HTTPS |
| 403 Access denied | User has no portal entry capability |
| Blank medication dose/status | Fixed in portal view field mapping — update to latest 1.0.0 package |
| Download fails with wp-admin redirect on | Confirm download query args are allowlisted; temporarily disable redirect to test |

---

## Public referral form

| Symptom | Checks |
| --- | --- |
| Form not shown | Shortcode on page; form enabled in Settings |
| Submit appears to hang | Ensure latest public JS (submitter preserved); check browser console/network |
| Receipt missing | PRG uses receipt query arg + transient; avoid aggressive page cache on that page |
| Spam submissions | Honeypot/rate limit active; consider later CAPTCHA (not in v1.0) |

---

## Reports / alerts

| Symptom | Checks |
| --- | --- |
| Slow alerts page | Expected under large datasets — see Known Limitations |
| Charts missing | Chart.js vendor/CDN; tables still show data |
| Empty scoped report | Support Worker scope may legitimately be empty |

---

## General WordPress

| Symptom | Checks |
| --- | --- |
| White screen | Enable `WP_DEBUG_LOG`; check PHP version |
| Plugin won’t activate | Missing `vendor/autoload.php` — release ZIP must include `vendor/` |
| Odd UI | Conflict with admin CSS from other plugins; test with default admin colour scheme |

---

## Getting help

Collect: plugin version, DB version (`jmrs_db_version`), PHP/WP versions, role of test user, exact URL, and whether staging reproduces.

See `docs/FAQ.md` and `docs/KNOWN_LIMITATIONS.md`.
