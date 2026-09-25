# Legal contact and policy approval record

**Status:** PUBLICATION DETAILS SUPPLIED — substantive legal/governance approval and signatures remain incomplete, so production personal-data collection must remain blocked.
**Policy version awaiting approval:** 2026-09-08-v2

Do not infer these values from logos, product text, developer email addresses, domain-registration data, or a facility name. The signer must have authority to identify the actual personal information controller (PIC) for eDonate.

## Owner-supplied publication details

| Required item | Approved value |
|---|---|
| Exact legal name of PIC/controller | City Health Office of Lipa City |
| Entity type (LGU office, government agency, private entity, individual, etc.) | **CONFIRM AT SIGN-OFF**; the supplied name describes a city health office, but the formal entity classification was not separately supplied. |
| Principal/registered complete postal address | City Hall Compound, Lipa City, Batangas, Philippines |
| DPO/privacy representative full name and title | John Merrick F. Fortis — Privacy representative. **Confirm any formal DPO appointment/title at sign-off.** |
| Dedicated, monitored privacy/DPO email | fortismerrick@gmail.com |
| Privacy telephone/accessible alternative contact channel | **OWNER MUST SUPPLY** |
| NPC registration number, or signed reason registration is not required | **OWNER MUST SUPPLY** |
| Service territory (Philippines only, or countries actively offered/monitored) | Philippines only |
| Minimum donor/account age and guardian process | Minimum 18 years old for account creation and blood donation; minor and guardian-managed accounts are not offered. |
| Supervisory authority/complaint details beyond the Philippine NPC, if applicable | None identified from the supplied Philippines-only scope; reassess before any foreign offering or monitoring. |

The supplied publication values map to the following environment configuration:

```dotenv
PRIVACY_CONTROLLER_NAME="City Health Office of Lipa City"
PRIVACY_CONTROLLER_ADDRESS="City Hall Compound, Lipa City, Batangas, Philippines"
PRIVACY_CONTACT_EMAIL=fortismerrick@gmail.com
PRIVACY_REPRESENTATIVE_NAME="John Merrick F. Fortis"
PRIVACY_REPRESENTATIVE_TITLE="Privacy representative"
PRIVACY_PHILIPPINES_ONLY=true
PRIVACY_MINIMUM_AGE=18
```

The owner states that the listed privacy email is monitored. The PIC must still formally designate and govern the mailbox, protect it with MFA, arrange coverage during staff absence, and connect it to documented rights-request and incident processes.

These details were supplied for publication on 8 September 2026. This record does not itself prove authority, a formal DPO appointment, NPC registration, or approval of the processing-purpose matrix below.

## Processing-purpose approval matrix

Counsel/DPO must complete the exact statutory basis and retention—not merely write “consent.” Add an attachment if needed.

| Processing purpose/data | DPA Section 12 ground | DPA Section 13 sensitive-data condition | Retention + deletion event | Approved recipients/processors | Approved? |
|---|---|---|---|---|---|
| Registration: identity, contact, age, blood type, address |  |  |  |  | No |
| Health eligibility screening and human review |  |  |  |  | No |
| Identity document verification |  |  |  |  | No |
| Appointment and donation history |  |  |  |  | No |
| Blood-request matching/contact |  |  |  |  | No |
| Security sessions, login/2FA and audit logs |  |  |  |  | No |
| Operational email/OTP/password reset |  |  |  |  | No |
| Google sign-in/Firebase Authentication |  |  |  |  | No |
| Firebase Realtime Database synchronization |  |  |  |  | No |
| OpenStreetMap tiles/Nominatim geocoding |  |  |  |  | No |
| Gemini staff assistant/chat records |  |  |  |  | No |
| Backups, disaster recovery and legal holds |  |  |  |  | No |

## Required governance decisions

- Identify PIC versus PIP roles for the application owner, City Health Office/facilities (if actually involved), Hostinger, Google services, mail provider, and any support/development personnel.
- Approve a data inventory, purpose limitation, field minimization, role matrix, access review cadence, processor agreements, international transfer analysis, and provider/subprocessor list.
- Approve a retention schedule covering active records, unsuccessful/orphaned registrations, rejected documents, audit logs, consent receipts, sessions, mail, provider copies and every backup generation.
- Approve the data-subject request workflow, identity checks, response deadlines, lawful exceptions, provider/backup searches, appeal, and evidence of completion.
- Approve age/guardian rules and an accessible offline/assisted alternative where consent or web access is not appropriate.
- Approve automated eligibility logic, clinical oversight, human review, error correction, and language stating that the result is not a diagnosis or final clinical clearance.
- Determine DPO/DPS registration, DPIA or privacy impact assessment, records of processing, and any government-sensitive-data requirements.
- Approve incident severity criteria, 72-hour assessment/notification capability, contacts, evidence preservation, exercises and communications.
- Determine whether GDPR or another foreign law applies based on actual establishment, targeting, monitoring and users—not merely internet availability.

## Sign-off

| Role | Printed name | Signature/reference | Date | Approved policy/version |
|---|---|---|---|---|
| PIC authorized representative |  |  |  |  |
| DPO/privacy representative |  |  |  |  |
| Legal counsel |  |  |  |  |
| Clinical/service owner |  |  |  |  |
| Security/operations owner |  |  |  |  |
| Accessibility test owner |  |  |  |  |

After all required approvals, configure production, migrate, cache configuration, and run `php artisan privacy:check`. An all-PASS command is a technical preflight only; retain this signed record and the substantive assessments supporting it.
