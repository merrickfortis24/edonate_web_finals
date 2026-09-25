# eDonate privacy, security, and accessibility release audit

**Assessment date:** 8 September 2026
**Release decision:** **NO-GO until the remaining OPEN P0 items below are closed**
**Target:** WCAG 2.1 Level AA and the Philippine Data Privacy Act of 2012 (DPA). GDPR is conditional on the operator's actual establishment, targeting, or monitoring activities.

This is an engineering assessment and release checklist, not legal advice, a penetration test, or a certification. A code change cannot guarantee legal compliance or eliminate liability. The actual personal information controller (PIC), its DPO/privacy representative, counsel, infrastructure owner, and accessibility testers must approve the matters assigned to them.

## Executive result

The implementation now has substantial privacy-by-default and accessibility controls: versioned policy drafts, purpose-specific acknowledgments on personal-data collection routes, an encrypted first-party preference cookie, optional integrations disabled by default, lazy Google sign-in scripts, consent-gated maps and AI, consent receipts without raw IP/email, private identity-document storage, security headers, request throttling, and automated synthetic accessibility tests.

It is **not ready for public production release**. Owner-supplied operator/contact, Philippines-only scope, and 18+ age details are now published, and the measured WCAG contrast failure is fixed. However, no approved lawful-basis and retention schedule exists for sensitive donor data; previously disclosed credentials still need evidenced rotation; opaque database/archive files remain tracked in Git/history; and live provider, hosting, access-control, deletion, breach-response, and assistive-technology checks remain outstanding.

## P0 release blockers

| ID | Finding | Required closure evidence | Owner |
|---|---|---|---|
| P0-1 — CLOSED (publication scope) | The owner supplied the PIC/controller, postal address, monitored privacy email, privacy representative, Philippines-only scope, and 18+ rule. The policy configuration and public notices now publish those values. | Preserve owner authority/sign-off evidence and formally confirm the representative's title/entity type in `docs/LEGAL_CONTACT_APPROVAL.md`. These governance details remain part of P0-2 but the missing-contact blocker is closed. | Operator/privacy representative |
| P0-2 — OPEN | **Policies and lawful grounds are not approved.** Birthdate/age, blood type, health-screening answers, and government IDs are sensitive personal information. A checkbox does not itself satisfy every condition for lawful processing, especially where consent is not freely given or a public/health mandate applies. | Record the DPA Section 12 ground for each ordinary-data purpose and a Section 13 condition for each sensitive-data purpose; document necessity, proportionality, alternatives, automated screening/human review, controller/processor roles, and any applicable healthcare/public-sector authority. Counsel/privacy representative and the clinical owner sign the final version and set `PRIVACY_POLICY_REVIEWED=true`. | Privacy representative/counsel/clinical owner |
| P0-3 — OPEN | **Credentials reported during development must be treated as exposed.** This includes database, mail, Gemini/Firebase and other provider credentials, plus authenticator/recovery material shown in prior troubleshooting. | Revoke and replace each exposed credential; review access logs; invalidate relevant sessions, TOTP enrollment and recovery codes. Rotate `APP_KEY` only with a planned migration/reset of every value encrypted under the old key—blind rotation can destroy access to encrypted 2FA/session data. No secret may be committed or pasted into tickets/chat. | Security/operations |
| P0-4 — OPEN | **Three opaque backup artifacts are tracked by Git:** `edonate_db (2).sql` and two “BACK UP FILE…” ZIP files. Their contents were deliberately not opened in this audit, so they must be treated as confidential until proven synthetic. `.gitignore` does not remove prior Git history. The quality workflow now deliberately blocks any tracked `.sql`, `.zip`, `.bak`, or `.dump` artifact. | Quarantine and inspect through an approved secure process; remove from the current tree; rotate any contained credentials; decide whether a coordinated Git-history rewrite and clone invalidation are required. Do not force-push history without an incident/repository migration plan. The quality job must pass after removal. | Security/repository owner |
| P0-5 — CLOSED (automated scope) | The `opacity-80` class that reduced the 12px eligibility status caption to about 3.75:1 was removed. The production assets were rebuilt. | The full synthetic browser suite now passes 19/19, including the donor-screening Axe A/AA scan. Complete the listed manual assistive-technology and visual checks before making a site-wide WCAG conformance claim. | Frontend/accessibility owner |
| P0-6 — OPEN | **Production configuration and migration are not independently evidenced.** The application must not run with local/debug settings or insecure cookies, the consent table and donor birthdate field must exist, and known underage donor records must not be present. | On the real host, set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`, and `SESSION_ENCRYPT=true`; run migrations and `php artisan privacy:check`; retain the all-PASS output without secrets. The deploy workflow enforces this gate after the automated quality job succeeds. | Operations |

The DPA requires transparency, legitimate purpose, proportionality, security, limited retention, and specific information about the controller, recipients, retention, and rights. See the [Data Privacy Act](https://privacy.gov.ph/data-privacy-act/), [DPA implementing rules](https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/), and the NPC's [right-to-be-informed guidance](https://privacy.gov.ph/the-right-to-be-informed/).

## P1 high-priority findings

1. **Provider governance is incomplete.** Firebase synchronization, server-side geocoding, and Gemini are correctly off by default, but consent is not a substitute for a processor agreement, transfer assessment, least-privilege IAM, retention terms, or documented legal ground. Existing provider copies are not erased by disabling future sync.

2. **Gemini cannot be enabled solely because the widget has a checkbox.** The operator must verify service tier, permitted region and age population, data-use terms, logging/retention, and a no-personal/no-health-data operating rule. Google's current [Gemini API Additional Terms](https://ai.google.dev/gemini-api/terms) contain material age, region, sensitive-data, and unpaid-service provisions. The current warning is not a technical DLP control.

3. **Firebase rules and IAM were not available in this repository.** Admin SDK credentials bypass Realtime Database security rules. Before enabling synchronization, inspect service-account roles, key age, audit logs, rules, backups, and every existing node/copy. Follow Firebase's [security rules guidance](https://firebase.google.com/docs/database/security).

4. **OSM services require an operational review.** Optional map tiles disclose the browser IP and tile area. Nominatim use must comply with its [public usage policy](https://operations.osmfoundation.org/policies/nominatim/), including identification, caching, app-wide rate limits, attribution, and avoiding personal/confidential submissions. Current geocoding is default-off and reduced to barangay/city/province, but free-text areas can still reveal personal data and a shared multi-server rate limit is not proven.

5. **Retention is configuration, not execution.** The cleanup command can report/delete selected temporary records, but it is not scheduled. Donation history, health answers, consent receipts, identity-review rows/files, audit logs, sessions, notifications, backups, mail-provider records, Firebase copies, and Gemini records need an approved schedule, legal holds, deletion verification, and accountable owners. Run only a dry-run until the schedule is approved.

6. **Data-subject rights lack an end-to-end operating procedure.** The drafts mention access, correction, objection, blocking/erasure, portability, and withdrawal; only chat deletion has a direct control. Define identity verification, intake/acknowledgment, deadlines, search across backups/providers, decision/appeal, legal-hold exceptions, and evidence of completion. The NPC summarizes these [data-subject rights](https://privacy.gov.ph/data-subject-rights/).

7. **Admin authorization can become stale or fail open on schema problems.** `EnsureAdminRole` trusts the role stored in the session instead of reloading it. `EnsureAdminAuthenticated` skips the database-account and 2FA checks when expected tables/columns are unavailable. A removed/disabled/demoted administrator may retain access until session expiry, and migration failure can weaken controls. Make privileged access fail closed, reload account status/role, revoke sessions on role/password/2FA changes, and add negative migration tests.

8. **Donor account lifecycle has two legacy issues.** `/signup/check-email` explicitly reveals whether an account exists. Direct POST `/signup` persists an unverified donor without sending/completing the OTP, which can create orphaned sensitive records and permanently reserve an email. The OTP flow also temporarily places the full signup payload in a server-side session; production session encryption is therefore mandatory. Consolidate registration behind one state machine and return a neutral email response.

9. **A legacy login checkbox conflates concepts.** Password login still requires a generic `terms` checkbox on every sign-in. Terms agreement, privacy-notice acknowledgment, and consent to a specific optional/sensitive purpose are legally distinct. Record versioned agreement when it is actually needed; do not make routine authentication look like fresh consent.

10. **Identity uploads need defense in depth.** Extension, MIME, size, private storage, authorized retrieval, traversal checks, and `nosniff` are present. There is no malware scan/content disarm policy, storage-at-rest evidence, reviewer download policy, or verified deletion schedule. Inline PDF/image rendering exposes reviewers to parser risk. Add malware scanning/quarantine, consider forced download or a sandboxed conversion preview, and restrict/log access.

11. **Logs contain personal data.** Audit/session/password-reset paths can retain names, account IDs, IP addresses, emails and provider error details. Establish access restrictions, redaction rules, retention, integrity protection, clock synchronization, export controls, and breach handling. Avoid logging raw request bodies, tokens, documents, health answers, and provider response bodies.

12. **CSP is intentionally incomplete.** Useful baseline headers are present, but `script-src`/`style-src` are absent because the legacy views contain extensive inline code/styles. Complete a nonce/hash-based CSP migration, remove dynamic inline execution, test report-only first, and enforce it at both application and web-server/CDN layers.

13. **Deployment is not atomic.** The workflow now has an independent quality job that rejects tracked database/backup artifacts, runs PHP tests, current dependency audits, a production build freshness check, and all browser privacy/WCAG checks before SSH deployment. It deploys the exact tested commit, targets the GitHub `production` environment, makes a corrupt checkout fail safely, installs `composer.lock`, runs `privacy:check`, and verifies critical public assets. Still add staging, health checks, atomic release directories/symlink switch, rollback, backups tested for restoration, and configure required reviewers on the production environment. Pin third-party GitHub Actions to reviewed commit SHAs and enable dependency update review.

14. **Breach readiness is not demonstrated.** Assign an incident commander/DPO, provider contacts, evidence handling, containment, risk assessment, communication templates, exercises, and documentation for all incidents. The DPA IRR can require notification to the NPC and affected subjects within 72 hours for qualifying breaches; see [Rule IX](https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/).

15. **NPC registration applicability requires a formal decision.** Processing health/government-ID data is likely high risk even below volume thresholds. Determine whether the PIC/DPO and data-processing system must be registered under NPC Circular 2022-04 and document the conclusion. See the NPC's [registration reminder](https://privacy.gov.ph/reminder-on-mandatory-data-protection-officer-and-data-processing-system-registration/).

## Implemented controls verified in code

- Privacy Policy, Terms, and Cookie Policy are versioned drafts with a visible not-reviewed state and the supplied controller, address, monitored email, privacy representative, Philippines-only scope, and 18+ rule.
- Production personal-data collection fails closed until policy review and valid controller/contact configuration are present.
- Password registration and profile completion reject birthdates below 18; Google-created donor accounts require an explicit 18+ attestation. Known underage accounts are denied by donor middleware, appointment readiness rejects missing/underage birthdates, donation completion rechecks age on the donation date, and donor matching excludes missing/underage birthdates when the schema supports them. The UI exposes the same cutoff.
- Seven donor collection routes have server-side, current-version Terms/privacy acknowledgment and purpose-specific validation: registration/OTP, profile completion, health screening, identity verification, appointment booking, and blood-request interest.
- Consent was **not** indiscriminately added to logout, cancellation, deletion, authentication, or staff operational actions. Consent must be freely given and purpose-specific; staff cannot consent on a donor's behalf. Other lawful grounds and authority still require documentation.
- Optional analytics, maps, and AI are off by default. No analytics/advertising script was found. Reject and selected-choice controls are available; closing the banner does not grant consent; withdrawal unloads optional integrations.
- Google/Firebase browser scripts are lazy-loaded only after a deliberate Google sign-in action. Bootstrap and SweetAlert are served locally instead of by passive CDN requests.
- The preference cookie is first-party, encrypted, HttpOnly, SameSite=Lax, versioned, and expiring. Consent receipts use a keyed pseudonym and omit raw email, IP, user-agent, health answers, and documents.
- AI conversation IDs are bound to the Laravel session; API keys stay server-side; outputs are inserted as text; prompts/provider bodies are not logged; deletion is available after withdrawal. Remote latency no longer holds a SQL transaction open.
- Identity documents use private storage and admin-only, throttled retrieval with path, extension, MIME, size, and `nosniff` checks.
- App responses receive baseline anti-framing, MIME-sniffing, referrer, permissions, cache, and HSTS protections where applicable. Dynamic JSON embedded in the admin layout is escaped against `</script>` breakout.
- Keyboard focus visibility, skip links, dialog focus restoration, table-region access, labels, status announcements, reduced-motion behavior, and 320px cookie-control reflow are covered by automated fixtures.
- The obsolete unauthenticated web deployment endpoint was removed. SSH deployment remains the authorized mechanism.

## Third-party data-flow inventory

| Party/service | Trigger and likely disclosure | Current technical state | Required decision before release/use |
|---|---|---|---|
| Hostinger | Hosting necessarily receives traffic metadata and stores application/database/files | Required infrastructure; live controls not inspected | DPA/contract, location/transfers, subprocessors, encryption, backups, support access, deletion and incident terms |
| Gmail SMTP/Google | Email address, OTP/reset/notification content and mail metadata | Operational mail; not controlled by cookie banner | Minimize templates, DPA/terms, account MFA, retention/log access, alternative contact path |
| Firebase Authentication | Browser/account data when user requests Google sign-in; backend token validation | Lazy-loaded on deliberate action | Authorized domains, OAuth consent/branding, API restrictions, account-linking rules, provider notice |
| Firebase Realtime Database/Admin SDK | Potential copy of donor, location, eligibility, appointment and notification records | Future synchronization default-off | Exact field allowlist, lawful ground, rules/IAM/key review, DPA/transfers/retention, erase prior copies |
| Gemini API | Staff chat prompts and replies; server/provider metadata | Default-off plus explicit optional choice | Paid/unpaid tier, adult-user restriction, no-PII/no-health enforcement, region, DPA/transfers, retention, human review |
| OpenStreetMap tile servers | Browser IP, user agent, referrer policy, requested tile coordinates | Default-off until maps choice | Tile policy/attribution, privacy notice, caching/alternate provider, withdrawal behavior |
| Nominatim | Server IP/user agent and area query | Default-off; reduced area only | Usage-policy compliance, shared throttling/cache, no personal/confidential data, provider alternative/SLA |
| Self-hosted Bootstrap/SweetAlert | No third-party browser request | Local pinned copies | Track upstream security notices and licenses |

No iframe was identified as an active required integration in the audited templates. Any future iframe must have a descriptive title, least-privilege `sandbox`/`allow`, `referrerpolicy="no-referrer"` where compatible, lazy loading, CSP allowance, and consent gating when non-essential. `sandbox` without an explicit capability grants nothing; do not blindly combine `allow-scripts` and `allow-same-origin` for untrusted same-origin content.

## Test evidence

- PHP: **129 passed, 1,015 assertions, 0 failures, 1 skipped**. The skipped pre-existing eligibility E2E test reports that its legacy eligibility tables are unavailable in the in-memory test schema; it is not evidence that the production eligibility path works.
- Browser: **19 of 19 passed** using synthetic fixtures and local Chrome. Fourteen representative page scans included WCAG 2.0/2.1 A and AA Axe rules; the additional tests covered default-off third-party traffic, consent keyboard behavior, map/AI gating, modal Escape/focus restoration, and 320px reflow. P0-5 is closed within this automated scope.
- Synthetic tests observed **no passive third-party request before consent** on the covered pages. This does not prove every live route/state is free of third-party traffic.
- The dependency remediation run reported **0 Composer advisories and 0 npm advisories**. Re-run both in CI immediately before release because advisory data changes.
- `git diff --check` completes without whitespace errors after the authorized eligibility/test cleanup.
- No live production data, provider account, Firebase rules/IAM, Hostinger settings, mail logs, CDN/WAF, DNS/TLS, backups, or real browser extension state was inspected.

Automated scans are not a WCAG conformance determination. [WCAG 2.1 conformance](https://www.w3.org/TR/WCAG21/#conformance-reqs) applies to complete pages and all states/processes, not a sample. Complete manual keyboard-only review, 200%/400% zoom and text-spacing tests, screen-reader tests (NVDA/Firefox and NVDA/Chrome at minimum), mobile orientation/touch, error recovery, timeouts, charts/non-text alternatives, document/PDF accessibility, and testing with disabled users before claiming AA.

## Production approval sequence

1. Complete the still-blank governance fields and signatures in `docs/LEGAL_CONTACT_APPROVAL.md`; obtain privacy representative/counsel/clinical approval of the policies, purpose/legal-basis matrix, rights process, provider list, and retention schedule. The contact, territory, and age publication details are already populated.
2. Rotate exposed credentials and investigate/remove the tracked SQL/ZIP artifacts. The quality job intentionally remains blocked while those artifacts are tracked. Do not publish a history rewrite without coordinating all clones and deployment keys.
3. Set the production values below only after approval. Keep optional integrations `false` until their individual reviews close.

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://edonate.online
   SESSION_SECURE_COOKIE=true
   SESSION_ENCRYPT=true

   PRIVACY_CONTROLLER_NAME="City Health Office of Lipa City"
   PRIVACY_CONTROLLER_ADDRESS="City Hall Compound, Lipa City, Batangas, Philippines"
   PRIVACY_CONTACT_EMAIL=fortismerrick@gmail.com
   PRIVACY_REPRESENTATIVE_NAME="John Merrick F. Fortis"
   PRIVACY_REPRESENTATIVE_TITLE="Privacy representative"
   PRIVACY_PHILIPPINES_ONLY=true
   PRIVACY_MINIMUM_AGE=18
   PRIVACY_POLICY_REVIEWED=true

   PRIVACY_FIREBASE_SYNC_ENABLED=false
   PRIVACY_GEOCODING_ENABLED=false
   PRIVACY_AI_ENABLED=false
   ```

4. Push through a pull request and require the new quality job. It must retain the verified PHP, dependency-audit, deterministic-build, and 19/19 browser results before deployment.
5. Deploy to staging; run migrations and `php artisan privacy:check`; exercise registration, OTP, sign-in/2FA/recovery, sensitive forms, rights/deletion, consent withdrawal, exports, uploads, error pages, and rollback using synthetic accounts.
6. Validate live headers/TLS/cookies/CSP, filesystem permissions, public-root exclusions, provider requests, IAM/rules, database encryption, backup restore/deletion, monitoring/alerting, and incident contacts.
7. Obtain written release sign-off from the PIC, DPO/privacy representative, security owner, clinical owner, accessibility tester, and operations owner. Preserve evidence and schedule periodic reassessment.

## Conditional GDPR work

Do not state “GDPR compliant” based on worldwide website availability. First document whether Article 3 territorial scope applies. If it does, complete the controller/processor and Article 6/9 basis matrix, transparent automated-decision information, data protection impact assessment where required, DPO/representative analysis, transfer mechanism and transfer impact assessment, processor contracts, rights/deadline workflow, supervisory authority, breach process, and records of processing. Use the official [GDPR text](https://eur-lex.europa.eu/eli/reg/2016/679/oj) and qualified counsel. Consent design should also be checked against the EDPB's [Guidelines 05/2020 on consent](https://www.edpb.europa.eu/our-work-tools/our-documents/guidelines/guidelines-052020-consent-under-regulation-2016679_en).

## “Vibe-coded” maintainability risks

- `AdminAuthController` is a multi-thousand-line controller spanning authentication, RBAC, reports, dashboard data, settings, and operational workflows. Split bounded services/controllers and add policy/authorization tests before changes become unreviewable.
- Repeated large inline scripts and duplicated modal/table request logic make CSP, escaping, focus management, and consistent error handling fragile. Move to typed modules and shared UI primitives.
- Runtime `Schema::hasTable/hasColumn` compatibility branches can hide failed migrations and create fail-open behavior. Use explicit deployment preconditions and versioned migrations, then remove legacy branches after migration.
- Consent receipt insertion occurs before some business handlers, so it can evidence an attempted submission rather than only a successful transaction. Define the evidentiary requirement and transaction boundary. Receipts are pseudonymous but not cryptographically append-only, and their retention is undecided.
- The service worker may remain installed in older browsers even after feature changes. Version and explicitly retire obsolete workers/subscriptions; verify no historic number-matching push flow remains active.
- Generated assets are committed while builds are performed on a different machine. Add deterministic build verification and a required CI check to prevent stale source/build mismatches.

These findings are a prioritized, evidence-based list from the inspected code and tests—not a claim that every possible vulnerability or legal issue has been found.
