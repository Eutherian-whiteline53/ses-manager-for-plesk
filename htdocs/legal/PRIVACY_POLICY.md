# SES Manager for Plesk Privacy Policy

Version: 2026-06-16

This Privacy Policy explains how SES Manager for Plesk ("Extension"), provided by Opphisse Agency ("Provider", "we", "us"), processes data when installed on a Plesk server.

## 1. Scope

This policy applies to the Extension and its related support and documentation processes. It does not replace the privacy policies of Plesk, Amazon Web Services, Cloudflare, DNS providers, hosting providers, or other third-party services used with the Extension.

## 2. Data Processed Locally on the Plesk Server

The Extension may store and process the following data locally on the Plesk server:

- Plesk domain names and related internal IDs.
- SES identity status, DKIM records, SPF, DMARC, MX, and DNS health status.
- Deliverability score and health check timestamps.
- AWS region and SES configuration.
- AWS access key, AWS secret key, SES SMTP username, SES SMTP password, and Cloudflare API token when entered by an administrator.
- Smarthost configuration snapshot used for rollback.
- Mail test metadata such as sender, recipient, subject, status, provider response, and timestamp.
- SNS webhook events such as bounce, complaint, delivery, recipient, message ID, event type, and raw SNS payload where configured.
- Job queue records for automation and bulk operations.
- EULA acceptance record and related timestamps.
- Operational logs needed for troubleshooting.

Secrets are stored using Plesk encryption facilities where supported by the Extension.

## 3. Data Sent to Third-Party Services

Depending on administrator configuration and enabled features, the Extension may send data to:

### Amazon Web Services

The Extension may call AWS SES and SNS APIs to test credentials, read SES account status, create or inspect SES identities, configure MAIL FROM records, configure notification topics, and send test email.

Data sent to AWS may include domain names, email addresses used in tests, AWS region, SES identity names, SNS topic ARNs, and API request metadata.

### Cloudflare

If Cloudflare automation is enabled, the Extension may call Cloudflare APIs to detect zones and create, update, or inspect DNS records.

Data sent to Cloudflare may include domain names, DNS record names, record values, zone IDs, and Cloudflare account or zone metadata.

### DNS Infrastructure

DNS health checks query public DNS records for configured domains. These DNS queries may disclose the queried domain names to recursive resolvers used by the server.

## 4. Data Sent to Provider

The Extension is designed to operate primarily on the Plesk server. Provider does not need routine access to AWS secrets, SMTP passwords, Cloudflare tokens, private keys, mailbox passwords, or full DNS zone exports.

Data may be sent to Provider only when:

- You contact support and provide logs, screenshots, diagnostics, or configuration details.
- You use a Provider-hosted documentation, telemetry, or support endpoint where explicitly enabled.
- Plesk or the Extensions Catalog provides installation or support metadata to Provider under the applicable program.

You should remove secrets and unnecessary personal data before sending support material.

## 5. Public SNS Webhook

The Extension includes a public webhook endpoint for AWS SNS when bounce, complaint, or delivery tracking is enabled.

The webhook accepts SNS messages only after signature verification. It may store event data such as recipient address, event type, message ID, bounce type, delivery status, raw payload, and timestamps.

Administrators should keep AWS SNS Raw Message Delivery disabled for this webhook unless the Extension documentation states otherwise.

## 6. Legal Basis and Administrator Responsibility

The Plesk server administrator is responsible for determining the legal basis for processing email addresses, domain names, log data, and deliverability events in their environment.

Depending on jurisdiction and use case, data may be processed for legitimate interests, contract performance, security, service operation, compliance, or consent-based purposes.

## 7. Data Retention

Local retention depends on Extension settings and administrator actions.

Recommended defaults:

- Mail test records: 90 days.
- Operational logs: 30 days.
- Bounce, complaint, and delivery events: 180 days.
- Health check and reputation cache: refreshed periodically and overwritten as needed.
- EULA acceptance records: retained while the Extension remains installed or as required for compliance.

Administrators may delete local data according to their backup, retention, and compliance policies.

## 8. Security

The Extension uses Plesk facilities and local server storage to protect configuration and secrets where available. Administrators should:

- Restrict Plesk administrator access.
- Use least-privilege AWS IAM policies.
- Use scoped Cloudflare API tokens.
- Keep Plesk, the Extension, PHP, and server packages updated.
- Protect backups that may contain Extension data.
- Avoid sharing logs containing secrets or personal data.

No system can be guaranteed fully secure.

## 9. International Transfers

Data may be processed in countries where Plesk, AWS, Cloudflare, hosting providers, DNS providers, Provider, or support tools operate. Administrators are responsible for ensuring that such transfers comply with applicable law.

## 10. Children's Data

The Extension is intended for server administrators and business/technical use. It is not directed to children.

## 11. Your Choices

Administrators can:

- Disable optional integrations such as Cloudflare automation or SNS event tracking.
- Remove stored credentials.
- Delete local Extension records from Plesk or the Extension database where supported.
- Uninstall the Extension.
- Contact Provider support for privacy-related questions.

## 12. Changes to This Policy

Provider may update this Privacy Policy for future versions. The updated version date will be shown at the top of this document.

## 13. Contact

Provider: Opphisse Agency

Product: SES Manager for Plesk

Website: https://opphisse.agency/products/ses-manager

Support: https://opphisse.agency/support

Privacy URL: https://opphisse.agency/products/ses-manager/privacy
