# SES Manager for Plesk

[![Latest Release](https://img.shields.io/github/v/release/opphisseagency/ses-manager-for-plesk?display_name=tag)](https://github.com/opphisseagency/ses-manager-for-plesk/releases)
[![CI](https://github.com/opphisseagency/ses-manager-for-plesk/actions/workflows/ci.yml/badge.svg)](https://github.com/opphisseagency/ses-manager-for-plesk/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Plesk](https://img.shields.io/badge/Plesk-Obsidian%2018.x-blue)](https://www.plesk.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](composer.json)

Open-source Plesk extension for routing mail through Amazon SES, automating DKIM/SPF/DMARC DNS records, and tracking SES bounce/complaint events.

SES Manager helps a Plesk administrator connect AWS SES, create SES domain identities, generate DKIM/SPF/DMARC/MAIL FROM DNS records, apply records to Plesk DNS or Cloudflare, configure Plesk smarthost delivery through SES SMTP, send test messages, monitor DNS health, and collect SES bounce/complaint/delivery events through AWS SNS.

This public build does not require a license key and does not enforce package tiers or domain limits.

Download the latest installable ZIP from the [GitHub Releases page](https://github.com/opphisseagency/ses-manager-for-plesk/releases/latest).

## Why Use It?

Plesk can host many domains, while Amazon SES requires identity verification, DKIM, SPF, DMARC, MAIL FROM records, SMTP credentials, and feedback handling. SES Manager puts that operational workflow inside the Plesk panel so administrators can configure, validate, and monitor mail delivery without manually stitching every provider step together.

## Safety Defaults

- No license server, telemetry, package tiers, or domain limits in the public build.
- AWS, SES SMTP, and Cloudflare secrets are stored with Plesk `pm_Crypt` where supported.
- Mutating panel actions use CSRF validation.
- DNS changes are previewed before apply.
- SPF records are merged rather than overwritten.
- AWS SNS webhook requests are size-limited and signature-verified.

SES Manager is not affiliated with, endorsed by, or sponsored by Plesk, Amazon Web Services, or Cloudflare.

## Features

- Guided setup wizard for legal acceptance, AWS region, AWS API credentials, SES sandbox status, SES SMTP credentials, DNS defaults, Cloudflare, SNS, and summary review.
- AWS SES API v2 connection check.
- SES SMTP STARTTLS credential test.
- SES domain identity creation from the Plesk panel.
- DKIM CNAME, SPF, DMARC, and custom MAIL FROM MX/TXT record planning.
- Plesk DNS apply with safe SPF merge behavior.
- Cloudflare DNS preview and apply.
- Optional full Cloudflare DNS sync mode.
- Smarthost preview, apply, connection test, and rollback.
- Mail test tool with recent delivery history.
- DNS health checks for SES, SPF, DKIM, DMARC, and MAIL FROM/MX.
- Deliverability score per tracked domain.
- SES account reputation snapshot.
- IP reputation checks for DNSBL, reverse DNS, forward-confirmed PTR, SMTP banner, HELO/EHLO identity, and ASN/provider data.
- AWS SNS webhook for SES bounce, complaint, and delivery events.
- SNS signature validation and optional Topic ARN allowlist.
- Bounce/Complaint center with event list and CSV export.
- Bulk domain verification through Plesk long tasks.
- Optional automatic SES onboarding for newly created Plesk domains.
- Advanced settings for retention cleanup and sanitized support bundle export.
- English and Turkish UI locales. Other Plesk panel languages fall back to English.

## Requirements

- Plesk Obsidian 18.x on Linux.
- PHP runtime provided by Plesk.
- `curl`, `openssl`, DNS functions, and standard Plesk extension APIs.
- Amazon SES account.
- AWS IAM access key and secret key with the required SES/SNS permissions.
- SES SMTP credentials.
- Optional Cloudflare API token for Cloudflare DNS automation.
- Public HTTPS URL for the SNS webhook if bounce/complaint tracking is used.

## Installation

Download or build the extension ZIP, then upload it to the target Plesk server.

Install from SSH:

```bash
plesk bin extension --install /root/ses-manager-1.0.1-1.zip
```

Upgrade an existing installation:

```bash
plesk bin extension --upgrade /root/ses-manager-1.0.1-1.zip
```

If panel assets do not refresh immediately after an upgrade, restart or reload the Plesk panel services:

```bash
systemctl restart sw-engine
systemctl reload sw-cp-server || systemctl restart sw-cp-server
```

Check that the extension is installed:

```bash
plesk bin extension --list | grep ses-manager
```

Open Plesk and go to:

```text
Extensions > My Extensions > SES Manager
```

## Build a Release ZIP

The release build script must be run on a Plesk server because it uses Plesk's extension pack command.

From the extension directory on the Plesk server:

```bash
cd /usr/local/psa/admin/plib/modules/ses-manager
./scripts/build-release.sh
```

The script exports the package to:

```text
/tmp/ses-manager-release-build/
```

It also validates that private development files, tests, debug scripts, and release-build artifacts are not included in the ZIP.

## First Setup

After installation, open SES Manager and run the setup wizard.

The setup wizard asks for:

- EULA and Privacy Policy acceptance.
- AWS SES region.
- AWS Access Key ID.
- AWS Secret Access Key.
- SES SMTP username.
- SES SMTP password.
- Custom MAIL FROM subdomain, for example `bounce`.
- Default DMARC policy: `none`, `quarantine`, or `reject`.
- Optional automatic SES onboarding for new domains.
- Optional Cloudflare API token and account ID.
- Optional AWS SNS Topic ARN.

Secrets are stored with `pm_Crypt` and are not displayed back in forms.

## AWS Setup

### Choose an SES Region

Use the same AWS region for:

- SES domain identities.
- SES SMTP endpoint.
- SES account reputation checks.
- SNS feedback topics, when possible.

Example SES SMTP endpoint:

```text
email-smtp.eu-central-1.amazonaws.com:587
```

### Create an IAM User

In AWS IAM:

1. Create a dedicated IAM user for SES Manager.
2. Create an access key for application/API use.
3. Store the Access Key ID and Secret Access Key in the SES Manager setup wizard.
4. Use least-privilege permissions where possible.

Minimum useful permissions depend on enabled workflows. A practical starting policy is available in:

```text
docs/iam-policy.json
```

Common permissions used by the extension include:

- `ses:GetAccount`
- `ses:CreateEmailIdentity`
- `ses:GetEmailIdentity`
- `ses:PutEmailIdentityMailFromAttributes`
- `ses:SetIdentityNotificationTopic`
- `ses:GetIdentityNotificationAttributes`
- `ses:BatchGetMetricData`
- `sns:CreateTopic`
- `sns:Subscribe`
- `sns:ListSubscriptionsByTopic`
- `sns:GetSubscriptionAttributes`
- `sns:SetSubscriptionAttributes`

### Request SES Production Access

If your SES account is still in sandbox mode, Amazon only allows sending to verified recipients. Request production access in the AWS SES console before routing real customer mail through SES.

SES Manager can detect sandbox/production status, but AWS approval must be done from the AWS console.

## SES SMTP Credentials

SES SMTP credentials are different from AWS IAM access keys.

To create SES SMTP credentials:

1. Open the AWS SES console.
2. Select the same region used in SES Manager.
3. Go to SMTP settings.
4. Create SMTP credentials.
5. Copy the SMTP username and SMTP password.
6. Enter them in SES Manager.

SES Manager tests SMTP authentication using STARTTLS on port `587`.

## Domain Verification Flow

For each Plesk domain:

1. Open SES Manager > Domains.
2. Click Manage for the domain.
3. Click Create SES Identity.
4. SES Manager creates or reads the SES identity.
5. SES Manager generates required DNS records.
6. Review the planned records.
7. Apply DNS changes through Plesk DNS or Cloudflare.
8. Refresh DNS Health after DNS propagation.

Generated records may include:

- Three DKIM CNAME records.
- SPF TXT record including `include:amazonses.com`.
- DMARC TXT record.
- Custom MAIL FROM MX record.
- Custom MAIL FROM SPF TXT record.

SPF records are merged instead of overwritten.

## Cloudflare Setup

Cloudflare integration is optional.

Create a Cloudflare API token with access to the zones you want SES Manager to manage.

Recommended token permissions:

- Account: Account Settings Read, if account discovery is needed.
- Zone: Zone Read.
- Zone: DNS Read.
- Zone: DNS Edit.

Recommended token scope:

- Include only the needed Cloudflare account or zones.

In SES Manager:

1. Open Settings or the setup wizard.
2. Paste the Cloudflare API token.
3. Optionally enter the Cloudflare Account ID.
4. Choose sync mode:
   - SES records only.
   - All Plesk DNS records.
5. Save.

Cloudflare changes use a preview/apply workflow. The extension plans changes first and applies them only after confirmation.

## AWS SNS Bounce and Complaint Tracking

SES Manager includes a public webhook for signed AWS SNS messages:

```text
https://your-plesk-host/modules/ses-manager/public/sns-webhook.php
```

Use HTTPS. The webhook rejects unsigned messages and rejects SNS Raw Message Delivery payloads.

Recommended setup:

1. Create an SNS Standard topic in the same AWS region as SES.
2. Subscribe the SES Manager webhook URL as an HTTPS endpoint.
3. Keep Raw Message Delivery disabled.
4. Configure SES identity feedback notifications:
   - Bounce topic.
   - Complaint topic.
   - Delivery topic, optional.
5. Set the allowed SNS Topic ARN in SES Manager.
6. Confirm that test events appear in Bounce/Complaint Center.

SES Manager can also automate parts of the SNS setup if the configured IAM user has the required SNS and SES permissions.

For deterministic tests, send mail to Amazon SES simulator addresses:

```text
bounce@simulator.amazonses.com
complaint@simulator.amazonses.com
```

## Smarthost Setup

The Smarthost page configures Plesk outgoing mail to relay through Amazon SES SMTP.

Flow:

1. Enter and test SES SMTP credentials.
2. Open SES Manager > Smarthost.
3. Review the generated SMTP relay configuration.
4. Click Apply.
5. SES Manager stores a snapshot of the previous relay configuration.
6. SES Manager applies SES SMTP relay settings.
7. SES Manager performs a connection test.
8. If the test fails, SES Manager attempts rollback.

Review the current mail server relay settings before applying this on production servers.

## Mail Test

The Mail Test tool sends a message through the configured SES SMTP endpoint.

Use it to verify:

- SMTP credentials.
- Region-specific endpoint access.
- Sending permissions.
- Sandbox limitations.
- Basic recipient delivery.

Test history is stored locally and can be cleaned by retention settings.

## DNS Health and Deliverability Score

DNS Health checks tracked domains for:

- SES identity verification status.
- SPF record presence and SES include.
- DKIM CNAME match.
- DMARC record and policy.
- MAIL FROM MX readiness.

The score is calculated from SES, DKIM, SPF, DMARC, and MX readiness.

Use the DNS Health page after every DNS change and after propagation windows.

## Advanced Settings and Support Bundle

Advanced Settings includes retention periods for:

- Mail test history.
- Bounce/complaint/delivery events.
- SNS webhook attempts.
- Background job history.
- SES reputation snapshots.
- IP reputation checks.
- Local log files.

It also generates a sanitized support bundle containing:

- Extension metadata.
- Plesk/server summary.
- Masked extension settings.
- Database table counts and date ranges.
- Installed Plesk extensions and versions, when readable.
- Local log file metadata.

Secrets are reported only as `configured` or `missing`.

## Security Notes

- AWS credentials, SES SMTP credentials, and Cloudflare tokens are encrypted with Plesk `pm_Crypt`.
- Secret values are not rendered back into forms.
- Mutating panel actions require POST and CSRF validation.
- Public webhook payloads are size-limited.
- SNS signatures are verified before payload processing.
- SNS signing certificates must be fetched from trusted AWS SNS certificate URLs.
- Optional SNS Topic ARN allowlist can restrict accepted messages to one topic.
- DNS changes are previewed before apply.
- SPF records are merged rather than overwritten.
- Support bundle output masks secrets.

## Third-Party Costs

SES Manager does not include AWS, Cloudflare, hosting, DNS, or other third-party service costs.

Amazon SES and SNS are usage-based services. Always check current provider pricing before production use.

Useful pricing pages:

- https://aws.amazon.com/ses/pricing/
- https://aws.amazon.com/sns/pricing/
- https://www.cloudflare.com/plans/

Simple SES outbound example, excluding attachments, VDM, dedicated IPs, SNS delivery, data transfer, taxes, and free-tier effects:

- 120,000 emails/year at USD 0.10 per 1,000 emails is about USD 12/year.
- 1,200,000 emails/year at USD 0.10 per 1,000 emails is about USD 120/year.

## Uninstall

Uninstall from Plesk:

```bash
plesk bin extension --uninstall ses-manager
```

The uninstall scripts clean scheduled tasks and temporary/cache files. They do not intentionally delete user data tables.

## Development Layout

Important directories:

- `controllers/` - Plesk MVC controllers.
- `library/` - services, repositories, clients, forms, DTOs, and shared controller code.
- `views/` - Plesk panel templates.
- `hooks/` - Plesk navigation and custom button hooks.
- `htdocs/` - public extension assets and SNS webhook endpoint.
- `scripts/` - install/uninstall/build scripts.
- `sbin/` - scheduled/background command entrypoints.
- `resources/locales/` - translation files.
- `docs/` - technical and user documentation.

## License

The source code is published under the MIT License. See `LICENSE`.

This public build is provided without runtime license activation, package restrictions, or domain limits.
