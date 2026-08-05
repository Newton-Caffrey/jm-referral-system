/**
 * Chart.js vendor note
 * ----------------------
 * Reports enqueue Chart.js only on the J&M Referrals → Reports admin page.
 *
 * Preferred: place a pinned Chart.js UMD build at:
 *   assets/vendor/chart.umd.min.js
 * (Chart.js v4.4.6 — https://github.com/chartjs/Chart.js/releases/tag/v4.4.6)
 *
 * Fallback: jsDelivr CDN, version pinned to 4.4.6:
 *   https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js
 *
 * Chart.js is never loaded on other WordPress admin screens.
 */
