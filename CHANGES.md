# Changelog

## 1.0.1 - Public Open Source Release

- Published SES Manager as a public build with no license activation, package tiers, or domain limits.
- Enabled all extension features by default.
- Added English and Turkish locale coverage. Other Plesk panel languages fall back to English.
- Hardened mutating panel actions with CSRF validation.
- Hardened the public AWS SNS webhook response behavior and certificate URL validation.
- Updated the Plesk custom button icon to use the standard extension icon asset.
- Removed private development notes, debug files, and test fixtures from release packaging.

## 1.0.0 - Initial Feature Complete Build

- Added AWS SES setup, SES SMTP credential testing, domain identity creation, DKIM/SPF/DMARC/MAIL FROM planning, Plesk DNS apply, smarthost configuration, mail testing, DNS health checks, deliverability scoring, Cloudflare DNS sync, SNS webhook processing, bounce/complaint/delivery tracking, bulk verification, reputation checks, support bundle generation, retention cleanup, EULA acceptance, and Privacy Policy acceptance.
