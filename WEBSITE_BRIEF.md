# Website Brief: SES Manager for Plesk

## Project Overview

Build an English product website for **SES Manager for Plesk**, a commercial Plesk extension that helps hosting providers, agencies, and server administrators connect Amazon SES to Plesk, automate SES domain verification, manage DNS records, configure smarthost delivery, monitor deliverability, and track bounce/complaint events.

The website should clearly explain what the extension does, who it is for, what each package includes, and how a user can buy, install, and activate the extension.

## Primary Goal

Convert Plesk server owners and hosting operators into extension users by making the value proposition concrete:

- Reduce manual Amazon SES setup work.
- Avoid DNS mistakes during SES verification.
- Improve visibility into deliverability health.
- Automate repetitive domain onboarding.
- Centralize SES, DNS, bounce/complaint, and reputation workflows inside Plesk.

## Target Audience

- Plesk administrators.
- Hosting providers.
- Web agencies managing many client domains.
- Email infrastructure operators.
- SaaS/product teams using Plesk and Amazon SES.
- Freelancers managing customer hosting panels.

## Positioning

**SES Manager for Plesk is a deliverability and SES automation control panel for Plesk.**

It is not just a DNS helper. It combines:

- SES setup automation.
- DNS health and auto-fix.
- Cloudflare DNS automation.
- Bounce/Complaint Center.
- SES reputation metrics.
- IP reputation monitoring.
- Bulk onboarding for agencies/providers.

## Suggested Website Structure

### 1. Home / Landing Page

Purpose: Explain the product quickly and move users toward pricing or installation.

Recommended sections:

- Hero.
- Problem/solution.
- Core workflow.
- Feature highlights.
- Screenshots/product UI area.
- Pricing preview.
- Security/trust.
- FAQ.
- Final CTA.

Hero copy:

```text
SES Manager for Plesk
Automate Amazon SES setup, DNS verification, and deliverability monitoring directly inside Plesk.
```

Supporting copy:

```text
Create SES identities, apply DKIM/SPF/DMARC records, configure custom MAIL FROM, monitor reputation, and track bounce/complaint events without leaving the Plesk panel.
```

Primary CTA:

```text
Get SES Manager
```

Secondary CTA:

```text
View Pricing
```

Hero proof points:

- Built for Plesk Obsidian 18.x.
- Amazon SES API v2 support.
- Plesk DNS and Cloudflare DNS automation.
- Encrypted credential storage.
- Free plan available.

### 2. Features Page

Purpose: Explain each product module with enough detail for technical buyers.

Recommended feature groups:

#### SES Setup Wizard

- Guided AWS region setup.
- IAM permission guidance.
- SES SMTP credential guidance.
- SES sandbox/production status detection.
- SES connection summary.

#### Domain Verification

- SES domain identity creation.
- DKIM CNAME record planning.
- Custom MAIL FROM setup.
- MAIL FROM MX/TXT generation.
- SPF and DMARC record generation.
- SES identity status refresh through AWS API.

#### DNS Health

- SPF, DKIM, DMARC, MX and MAIL FROM checks.
- Advanced SPF lookup analysis.
- DMARC policy, reporting, alignment and percentage analysis.
- DKIM CNAME match/conflict detection.
- One-click DNS auto-fix for eligible plans.

#### DNS Automation

- Plesk DNS record apply.
- SPF safe merge without overwriting existing policy.
- Conflict detection.
- Cloudflare DNS sync.
- SES-only or full Plesk DNS sync mode.

#### Smarthost

- Configure Plesk mail delivery through SES SMTP.
- Preview current SES SMTP endpoint.
- Apply smarthost configuration.
- Rollback support when verification fails.

#### Reputation Center

- SES account status.
- Sandbox/production detection.
- Daily usage and sending quota.
- Bounce and complaint rates.
- IP Reputation Center.
- DNSBL monitoring.
- Reverse DNS, SMTP banner and HELO checks.
- ASN/provider intelligence.

#### Bounce/Complaint Center

- AWS SNS webhook endpoint.
- SNS signature verification.
- Topic ARN allowlist.
- Bounce, complaint and delivery event tracking.
- Raw Message Delivery check.
- SES simulator testing guidance.
- CSV export.

#### Automation

- Optional automatic SES setup for new Plesk subscriptions/domains.
- Background job runner.
- Scheduler health checks.
- Bulk domain onboarding for agencies.
- Plesk dashboard and mail shortcuts.

#### Public Access

- No package-based feature gates.
- No license activation.
- All extension features are enabled in the public build.

### 3. Download Page

Purpose: Help users download, inspect and install the public build.

Show project status, requirements, installation steps and links to documentation.

#### Pricing Table

| Plan | Price | Limit | Best For |
| --- | ---: | --- | --- |
| Free | $0/mo | 3 domains | Testing SES Manager on one Plesk server |
| Starter | $2/mo | 10 domains | Small Plesk servers that need DNS auto-fix and Cloudflare |
| Professional | $5/mo | 50 domains | Production SES automation with SNS and event tracking |
| Agency | $10/mo | 250 domains | Agencies managing many client domains |
| Provider | $25/mo | 1000 domains or 25 servers | Hosting providers and multi-server operations |

Provider rule:

```text
Provider includes up to 1000 domains or 25 servers. The first reached limit applies.
```

#### Free

Included:

- AWS SES API connection test.
- SES SMTP connection test.
- SES account status.
- SES domain identity creation.
- DKIM, MAIL FROM, SPF and DMARC record visibility.
- DNS Health Check.
- SPF, DKIM, DMARC and MX checks.
- Deliverability score.
- SES sending quota and daily usage.
- SES reputation view.
- Up to 3 domains.

Not included:

- Automatic DNS setup.
- Cloudflare integration.
- SNS automation.
- Bounce/Complaint Center.
- Auto-fix.
- Bulk operations.
- CSV export.
- RBL monitoring.

CTA:

```text
Start Free
```

#### Starter - $2/month

Included:

- Up to 10 domains.
- Everything in Free.
- Cloudflare DNS automation.
- SPF Auto Fix.
- DKIM Auto Fix.
- DMARC Auto Fix.
- MAIL FROM Auto Fix.
- Cloudflare SES DNS sync.

Not included:

- SNS automation.
- Bounce/Complaint Center.
- Delivery analytics.
- Bulk setup.

CTA:

```text
Choose Starter
```

#### Professional - $5/month

Included:

- Up to 50 domains.
- Everything in Starter.
- Full Setup Wizard.
- Automatic SES setup for new domains.
- Automatic DNS setup.
- Cloudflare Sync.
- SNS Topic automation.
- SNS Subscription automation.
- Bounce tracking.
- Complaint tracking.
- Delivery tracking.
- Event viewer.
- SES feedback metrics.

CTA:

```text
Choose Professional
```

Recommended badge:

```text
Most Popular
```

#### Agency - $10/month

Included:

- Up to 250 domains.
- Everything in Professional.
- Bulk domain onboarding.
- Bulk SES identity creation.
- Bulk DNS creation.
- Bulk Health Check.
- CSV export.
- Mail Health Reports.
- Plesk notification support.
- RBL monitoring and recovery event history.

CTA:

```text
Choose Agency
```

#### Provider - $25/month

Included:

- Up to 1000 domains or 25 servers.
- Everything in Agency.
- Multi-server dashboard.
- Priority support.
- Optional white label.

CTA:

```text
Contact Sales
```

### 4. Documentation Page

Purpose: Reduce setup friction and support load.

Recommended documentation categories:

- Installation.
- First setup.
- AWS IAM permissions.
- SES SMTP credentials.
- SES sandbox to production.
- Custom MAIL FROM setup.
- Plesk DNS automation.
- Cloudflare DNS automation.
- SNS bounce/complaint setup.
- Raw Message Delivery troubleshooting.
- Smarthost configuration.
- Troubleshooting.

Important documentation CTA:

```text
Read Setup Guide
```

### 5. Security Page

Purpose: Build trust with technical buyers and Plesk Catalog reviewers.

Topics to cover:

- AWS credentials are encrypted with Plesk `pm_Crypt`.
- SES SMTP credentials are encrypted.
- Cloudflare token is encrypted.
- Secrets are never shown back in forms.
- Logs mask credentials, tokens and Authorization headers.
- Public SNS webhook requires AWS SNS signature verification.
- Optional SNS Topic ARN allowlist.
- DNS records are not blindly overwritten.
- SPF records are safely merged.
- DMARC auto-fix preserves existing policy/alignment.

Security summary copy:

```text
SES Manager is designed for production Plesk environments: encrypted secrets, signed SNS webhooks, safe DNS changes, and no plaintext credential logging.
```

### 6. FAQ Page / Section

Recommended questions:

#### Does SES Manager send emails by itself?

No. SES Manager configures and monitors Amazon SES usage from Plesk. Mail delivery is performed through Amazon SES SMTP or SES APIs depending on the configured workflow.

#### Do I need an AWS SES account?

Yes. You need AWS SES access, SES SMTP credentials, and production access for real customer sending.

#### Does the extension work while SES is in sandbox?

Yes, but SES sandbox restrictions still apply. SES can only send to verified identities until production access is approved by AWS.

#### Can it configure DNS automatically?

Yes. Plesk DNS automation is supported. Cloudflare DNS automation is available in paid plans.

#### Will it overwrite existing DNS records?

No. SPF is safely merged, and conflicting DKIM/DMARC records are marked as conflicts instead of being overwritten.

#### How are bounce and complaint events received?

Amazon SES publishes bounce and complaint events to Amazon SNS. SES Manager receives signed SNS messages through its public webhook and stores verified events.

#### Do I need Raw Message Delivery in SNS?

No. Raw Message Delivery must remain disabled. SES Manager needs the signed SNS envelope to verify the request.

#### Is there a free version?

Yes. The Free plan supports up to 3 domains and includes SES setup visibility and DNS health checks.

#### Which plan should I choose?

- Free: testing or very small usage.
- Starter: DNS auto-fix and Cloudflare for small servers.
- Professional: automated SES onboarding and SNS event tracking.
- Agency: bulk onboarding and reporting.
- Provider: multi-server hosting operations.

### 7. Download / Install Page

Purpose: Explain install and activation steps.

Suggested flow:

1. Download or purchase the extension.
2. Install ZIP from Plesk Extensions.
3. Open SES Manager in Plesk.
4. Run Setup Wizard.
5. Add AWS SES API credentials.
6. Add SES SMTP credentials.
7. Configure DNS automation.
8. Optional: connect Cloudflare.
9. Optional: connect SNS bounce/complaint events.

CLI install example:

```bash
plesk bin extension -i ses-manager.zip
```

Package build note for internal team:

```bash
./scripts/build-release.sh
```

The release package must exclude:

- local notes,
- debug scripts,
- test fixtures,
- temporary cache,
- logs,
- local secrets.

## Visual Direction

The site should feel like a serious infrastructure product, not a generic SaaS landing page.

Recommended style:

- Clean, technical, operational.
- Light background.
- High contrast product cards.
- Tables for pricing and feature comparison.
- Plesk-like clarity, but more polished.
- Avoid decorative gradients as the main visual language.
- Use product UI screenshots where possible.
- Use small status badges for SES/DNS/Reputation examples.

Suggested visual sections:

- Hero with product UI screenshot.
- Workflow diagram: AWS SES -> SES Manager -> Plesk DNS/Cloudflare -> Mail Delivery.
- DNS Health card example.
- Bounce/Complaint event table example.
- Pricing cards.
- Security checklist.

## Product Messaging

Primary headline options:

```text
Amazon SES automation for Plesk.
```

```text
Set up SES, DNS, and deliverability monitoring without leaving Plesk.
```

```text
Turn Plesk into a complete SES deliverability control panel.
```

Value proposition options:

```text
SES Manager helps Plesk administrators create SES identities, apply DNS records, configure smarthost delivery, and monitor reputation from a single extension.
```

```text
Replace manual SES verification steps with guided setup, safe DNS automation, and clear deliverability health checks.
```

Short product description:

```text
SES Manager for Plesk is a Plesk extension for Amazon SES setup, DNS automation, smarthost configuration, bounce/complaint tracking, and deliverability monitoring.
```

## Conversion CTAs

Primary:

- Get SES Manager
- Download Public Build
- Read Documentation

Secondary:

- Read Documentation
- Contact Sales

Public build note:

```text
All extension features are enabled in the public build. No license activation is required.
```

## Required Website Assets

Recommended assets to prepare:

- Product logo.
- Extension icon.
- Dashboard screenshot.
- Setup Wizard screenshot.
- DNS Health screenshot.
- Advanced DNS Analysis screenshot.
- Reputation Center screenshot.
- Bounce/Complaint Center screenshot.

## SEO Keywords

Primary:

- Plesk Amazon SES extension
- Amazon SES Plesk
- Plesk SES smarthost
- Plesk SES DNS automation
- Amazon SES DNS verification

Secondary:

- SES DKIM Plesk
- SES bounce complaint SNS
- Plesk email deliverability
- Cloudflare SES DNS automation
- Plesk mail reputation monitoring

## Technical Notes for Website Implementation

- The website should be in English.
- Pricing should be easy to edit.
- Feature matrix should be data-driven if possible.
- Use clear CTA links for each plan.
- Add structured FAQ markup if the stack supports it.
- Add privacy and support links matching `meta.xml`.
- Avoid overpromising inbox placement. Use “deliverability monitoring”, “DNS health”, and “reputation signals” instead of guaranteed inboxing.
- Make it clear that AWS SES account approval, SES quotas, and production access are controlled by AWS.

## Compliance Notes

Do not claim:

- Guaranteed inbox delivery.
- Guaranteed removal from blacklists.
- AWS partnership unless formally approved.
- Plesk Catalog approval until it is actually approved.

Safe claims:

- Automates SES setup workflows.
- Helps detect DNS and reputation issues.
- Safely applies DNS records.
- Tracks SES bounce/complaint events through SNS.
- Stores secrets encrypted using Plesk mechanisms.

## Final Website Objective

The website should make a technical buyer think:

```text
This extension saves setup time, reduces SES/DNS mistakes, gives me deliverability visibility, and scales from a single Plesk server to agency/provider operations.
```
