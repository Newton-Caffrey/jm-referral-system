# FAQ — JM Referral System v1.3.0

---

## Administrator FAQ

**Q: Does activating wipe my site?**
A: No. Activation migrates plugin tables and registers roles. It does not delete WordPress content.

**Q: Where are referral documents stored?**
A: New files in `wp-content/uploads/jmrs-private/`. Legacy files may still be in the Media Library until migrated.

**Q: Should I enable staff portal wp-admin redirect immediately?**
A: No. Leave it off until portal UAT passes (downloads, AJAX, exports).

**Q: Can I delete all plugin data on uninstall?**
A: Only if you set `JMRS_DELETE_DATA_ON_UNINSTALL` to `true` in `wp-config.php` on a disposable site after backups.

**Q: Why is DB version `2.28.0` while the product is `1.3.0`?**
A: Product semver and schema version are independent. Schema bumps only when tables/columns change. Product `1.3.0` ships schema `2.28.0` (acquisition pipeline).

**Q: Can I upgrade directly from v1.2.0 (DB `2.21.0`)?**
A: Yes. One migrate pass applies additive steps through `2.28.0`. Legacy referrals are not remapped; no migration emails.

**Q: Is there a REST API or mobile app?**
A: Not in v1.3. See roadmap for later phases.

---

## Staff FAQ

**Q: Why can’t I edit a referral in the portal?**
A: v1.0 portal is read-only. Use WordPress Admin if your role allows, or wait for a later portal release.

**Q: I only see some referrals.**
A: Support Worker (and similar) scoping limits rows to assignments you may view.

**Q: A referral URL says Not found.**
A: It may be missing or outside your permission — the portal does not distinguish these cases.

**Q: How do I download a document?**
A: Open the referral → Documents → Download. Stay signed in.

**Q: Where is the admin bar?**
A: Hidden on the frontend for most JM staff when the portal is enabled; Administrators may still see it.

---

## Public FAQ

**Q: Is my submission saved if I don’t get an email?**
A: Often yes — email depends on site mail. Contact the organisation with the approximate time of submit; staff can search the referral list.

**Q: Can I save and resume later?**
A: Not in v1.0. Completing the form in one session is required.

**Q: Are uploaded documents public on the internet?**
A: New uploads are stored privately and are not Media Library public URLs.

**Q: Who do I contact about privacy?**
A: Use the organisation’s privacy policy contact linked on the form / site.

**Q: Is this for emergencies?**
A: Use the organisation’s published emergency / safeguarding channels, not the web form alone.

---

## Related documents

- `docs/INSTALLATION_GUIDE.md`
- `docs/TROUBLESHOOTING.md`
- `docs/KNOWN_LIMITATIONS.md`
