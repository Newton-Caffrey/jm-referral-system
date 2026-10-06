# Staff User Guide — JM Referral System v1.0.0

Guide for Care Coordinators, Assessors, Support Workers, and other JM staff using the **Staff Portal** (when enabled) and/or WordPress Admin.

---

## Before you start

Your organisation administrator must:

1. Create your WordPress user
2. Assign a JM role (or capabilities)
3. Enable the Staff Portal (optional) under Settings

If the portal is not enabled, use **WordPress Admin → J&M Referrals** instead.

---

## Portal login

1. Open the portal URL (default: `/staff-portal/`).
2. If you are not signed in, you are redirected to the WordPress login page.
3. After login you return to the portal page you requested.

<!-- Screenshot: portal login redirect / portal top bar -->

**Log out** uses the portal header action and returns you to the public site.

---

## Portal dashboard

Shows widgets based on your permissions, for example:

| Managers / coordinators | Support Workers |
| --- | --- |
| Total / new referrals | My referrals / clients |
| Visits today / awaiting review | Today’s visits / completed |
| Operational alert count | Medication exceptions (mine) |
| Recent referrals | Recent my referrals |

Open **Referrals** / **My Referrals** from the sidebar or dashboard actions.

---

## Referral list

- Search by text
- Filter by status and priority
- Assignee filter (when your role allows)
- Archive filter (when permitted)
- Page size 20 / 50 / 100
- **View** opens the portal referral page

Support Workers see **My Referrals** only (scoped by assignment / care team policy).

<!-- Screenshot: portal referral list filters -->

---

## Referral view (read-only in v1.0)

Displays the same operational picture your permissions allow in admin, including:

- Summary, client, referrer, care requirements
- Documents (secure download links)
- Assessment summary and detail sections
- Care plan summary and content
- Care team
- Recent visits
- Medications
- Activity timeline

Archived referrals show archive information and remain read-only.

**Not available in the portal yet (use WordPress Admin if you have access):**

- Editing referrals
- Notes, uploads, stage changes
- Care plan editing, scheduling, visit execution, MAR
- Reports screens

---

## Document downloads

Use **Download** on the documents table. Files are served through the plugin’s secure download handler — never share raw private storage paths.

If download fails while “redirect staff away from wp-admin” is on, ask an administrator to verify download allowlisting (see Troubleshooting).

---

## Uploading a referral form

If you can prepare referrals from the Referral Inbox, you can upload a completed referral form instead of typing it in.

1. Open **Referral Inbox** and choose **Upload Referral Form**.
2. Pick the form. It must be a Word file (`.docx`) or a PDF, up to 10 MB. Upload one form at a time.
3. Choose **Upload and Read Form**. **Prepare Referral** opens with the details that were found already filled in.
4. Check every field against the form. Fields marked “Suggested from uploaded form” came from the file. Open **Show the text read from the form** to copy anything that was missed.
5. Choose the service type, referral source, and priority yourself. They are never filled in for you, though a suggestion may be shown.
6. Choose **Validate Details**, tick the confirmation, then **Create Referral**.

Things to know:

- Nothing is created until you confirm. Uploading only adds the form to the Inbox.
- If the form gives two different values for one field, such as a telephone and a mobile number, the field is left blank and both values are listed. Enter the right one.
- A scanned or photographed form cannot be read. It is still stored, and you enter the details by hand.
- An older `.doc` file must be saved as `.docx` or PDF first.
- The same file uploaded twice takes you to the Inbox item it already created.
- The form is added to the referral's documents when the referral is created.

If a form's details are rarely picked up, tell an administrator which labels the form uses. They can be added under Settings.

---

## Permissions

You only see what your capabilities and AccessPolicy allow. If a referral is missing or returns “Not found”, it may be inaccessible rather than absent — this is intentional.

---

## Future portal roadmap

Later releases are planned to move more workflows into the portal (editing, notes, visits, MAR, reports). Until then, administrators may grant wp-admin access for full clinical work.

---

## Related documents

- `docs/STAFF_PORTAL.md`
- `docs/FAQ.md` (Staff FAQ)
- `docs/TROUBLESHOOTING.md`
