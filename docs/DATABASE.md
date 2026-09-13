# Veritabanı Planı

Tablolar `scripts/db/install.php` ile idempotent şekilde oluşturulur. Mevcut kurulumlarda kolon ekleme öncesi `SHOW COLUMNS` kontrolü yapılır.

## ses_settings

Genel ayarlar ve encrypted secret değerleri.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK | Tek satır veya key-value grup ID |
| aws_key_encrypted | text | `pm_Crypt` ile encrypted |
| aws_secret_encrypted | text | `pm_Crypt` ile encrypted |
| smtp_username_encrypted | text | SES SMTP kullanıcı adı |
| smtp_password_encrypted | text | SES SMTP şifresi |
| cloudflare_token_encrypted | text nullable | Pro modül |
| cloudflare_account_id | varchar(128) nullable | Zone oluşturma hedef account |
| default_region | varchar(32) | Örn. `eu-central-1` |
| default_spf_policy | varchar(16) | `~all` varsayılan |
| default_dmarc_policy | varchar(16) | `none`, `quarantine`, `reject` |
| mail_from_subdomain | varchar(63) | Custom MAIL FROM için `bounce` varsayılan |
| cloudflare_sync_mode | varchar(16) | `ses` veya `all` |
| auto_ses_on_create | tinyint | Yeni domain/subscription otomasyonu |
| setup_completed | tinyint | Setup Wizard tamamlandı durumu |
| smarthost_enabled | tinyint | 0/1 |
| sns_topic_arn | varchar(512) nullable | Allowed SNS Topic ARN |
| smarthost_snapshot | text nullable | Rollback için son config |
| cache_ttl_seconds | int | Varsayılan 900 |
| retention_days | int | Varsayılan 90 |
| created_at | datetime |  |
| updated_at | datetime |  |

## ses_domains

Plesk domain ve SES identity durumları.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK |  |
| domain_name | varchar(255) | Unique |
| plesk_domain_id | int | Plesk domain ID |
| ses_identity_arn | varchar(512) nullable | SES identity |
| region | varchar(32) | Domain bazlı region |
| status | varchar(32) | `active`, `inactive`, `disabled`, `error` |
| verification_status | varchar(32) | `not_started`, `pending`, `verified`, `failed` |
| spf_status | varchar(32) | `pass`, `warning`, `missing`, `failed` |
| dkim_status | varchar(32) | `pass`, `pending`, `missing`, `failed` |
| dmarc_status | varchar(32) | `pass`, `warning`, `missing`, `failed` |
| mx_status | varchar(32) | `pass`, `optional`, `missing`, `failed` |
| spf_analysis_json | mediumtext nullable | SPF lookup/policy/issue detayları |
| dkim_analysis_json | mediumtext nullable | DKIM expected/matched/conflict detayları |
| dmarc_analysis_json | mediumtext nullable | DMARC policy/reporting/alignment detayları |
| mail_from_analysis_json | mediumtext nullable | MAIL FROM MX detayları |
| score | int | 0-100 |
| last_checked_at | datetime nullable | Cache zamanı |
| created_at | datetime |  |
| updated_at | datetime |  |

İndeksler:

- Unique: `domain_name`
- Index: `plesk_domain_id`
- Index: `status`
- Index: `verification_status`
- Index: `last_checked_at`

Domain lifecycle notları:

- Plesk'te silinen domainler scheduler senkronunda `inactive` yapılır.
- Inactive kayıt yeniden doğrulanırsa `registerDomain()` aynı satırı yeniden `active` yapar; duplicate domain kaydı üretilmez.
- Bulk verify seçilen tüm geçerli domainleri işler; public build'de domain slot limiti yoktur.

## ses_dns_records

Beklenen, uygulanan ve doğrulanan DNS kayıtları.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK |  |
| domain_id | int FK | `ses_domains.id` |
| type | varchar(16) | TXT, CNAME, MX |
| name | varchar(255) | Record name |
| value | text | Record value |
| provider | varchar(32) | `plesk`, `cloudflare`, `external` |
| purpose | varchar(32) | `spf`, `dkim`, `dmarc`, `mail_from_mx` |
| status | varchar(32) | `planned`, `applied`, `failed`, `conflict` |
| last_error | text nullable | Maskeli hata |
| created_at | datetime |  |
| updated_at | datetime |  |

İndeksler:

- Index: `domain_id`
- Index: `provider`
- Index: `purpose`
- Index: `status`

## ses_mail_tests

Panelden yapılan test mail sonuçları.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK |  |
| domain_id | int FK |  |
| from_email | varchar(255) |  |
| to_email | varchar(255) |  |
| subject | varchar(255) |  |
| status | varchar(32) | `success`, `failed` |
| provider_message_id | varchar(255) nullable | SES/SMTP response |
| response | text | Maskeli response |
| created_at | datetime |  |

İndeksler:

- Index: `domain_id`
- Index: `status`
- Index: `created_at`

## ses_events

SNS bounce, complaint ve delivery eventleri.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK |  |
| domain_id | int nullable | Eşleşirse domain |
| event_type | varchar(32) | `bounce`, `complaint`, `delivery`, `reject` |
| bounce_type | varchar(32) nullable | hard/soft ayrımı |
| recipient | varchar(255) |  |
| message_id | varchar(255) | SES message ID |
| raw_payload | mediumtext | JSON, secret içermez |
| received_at | datetime | SNS event zamanı |
| created_at | datetime | DB kayıt zamanı |

İndeksler:

- Index: `domain_id`
- Index: `event_type`
- Index: `recipient`
- Index: `message_id`
- Index: `created_at`

## ses_jobs

Scheduler ve long task durumları.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK |  |
| job_type | varchar(64) | `auto_domain_verify`, `bulk_verify` |
| status | varchar(32) | `queued`, `running`, `success`, `partial`, `failed` |
| payload | mediumtext | JSON |
| attempts | int | Varsayılan 0 |
| last_error | text nullable | Maskeli |
| scheduled_at | datetime |  |
| started_at | datetime nullable |  |
| finished_at | datetime nullable |  |
| created_at | datetime |  |

İndeksler:

- Index: `job_type`
- Index: `status`
- Index: `scheduled_at`

## ses_cloudflare_zones

Cloudflare zone cache.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK |  |
| domain_name | varchar(255) | Domain |
| zone_id | varchar(128) | Cloudflare zone ID |
| zone_name | varchar(255) | Root zone |
| detected_at | datetime | Cache zamanı |

Unique:

- `domain_name`

## ses_sns_confirmations

SNS subscription confirmation istekleri.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK |  |
| message_id | varchar(255) | SNS MessageId |
| topic_arn | varchar(512) | Topic ARN |
| subscribe_url | text | AWS SubscribeURL |
| token | text nullable | Confirmation token |
| status | varchar(32) | `pending`, `confirmed`, `failed` |
| last_error | text nullable | Son hata |
| received_at | datetime | SNS zamanı |
| confirmed_at | datetime nullable | Onay zamanı |
| created_at | datetime |  |
| updated_at | datetime |  |

## ses_sns_webhook_attempts

Webhook tanılama kayıtları.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK |  |
| message_id | varchar(255) | SNS MessageId |
| message_type | varchar(64) | `Notification`, `SubscriptionConfirmation` |
| topic_arn | varchar(512) | İmzalı topic |
| status | varchar(32) | `success`, `failed` |
| reason | text nullable | İşlem sonucu veya hata |
| http_status | int | Webhook response |
| raw_payload | mediumtext nullable | Tanılama payload'ı |
| created_at | datetime |  |

## ses_reputation_snapshots

SES account/quota/sandbox snapshot.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK |  |
| region | varchar(32) | AWS region |
| production_access_enabled | tinyint | Sandbox/production |
| sending_enabled | tinyint | Sending state |
| enforcement_status | varchar(64) nullable | AWS enforcement |
| max_24_hour_send | int | Günlük kota |
| sent_last_24_hours | int | Kullanım |
| max_send_rate | decimal | Saniyelik rate |
| bounce_rate | decimal | Event bazlı bounce oranı |
| complaint_rate | decimal | Event bazlı complaint oranı |
| health | varchar(32) | `excellent`, `warning`, `critical` |
| source | varchar(32) | `ses_events` |
| raw_payload | mediumtext nullable | AWS account payload |
| checked_at | datetime | Snapshot zamanı |

## ses_blacklist_checks

DNSBL sonuç geçmişi.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK |  |
| ip_address | varchar(64) | Sunucu public IP |
| provider | varchar(64) | Spamhaus, Barracuda, SORBS vb. |
| zone | varchar(255) | DNSBL zone |
| severity | varchar(32) | `critical`, `warning` |
| listed | tinyint | 0/1 |
| response | text nullable | DNSBL response |
| checked_at | datetime |  |

## ses_ip_reputation_checks

PTR/banner/HELO/ASN snapshot.

| Kolon | Tip | Not |
| --- | --- | --- |
| id | int PK |  |
| ip_address | varchar(64) | Public IP |
| ptr_hostname | varchar(255) nullable | Reverse DNS |
| ptr_status | varchar(32) | PTR durumu |
| forward_confirmed | tinyint | Forward-confirmed PTR |
| smtp_banner | text nullable | SMTP banner |
| smtp_banner_status | varchar(32) | Banner durumu |
| helo_hostname | varchar(255) nullable | Postfix `myhostname` |
| helo_status | varchar(32) | HELO durumu |
| asn | varchar(32) nullable | ASN |
| asn_name | varchar(255) nullable | ASN adı |
| asn_provider | varchar(128) nullable | Provider |
| asn_country | varchar(8) nullable | Ülke |
| asn_known_datacenter | tinyint | Known datacenter/cloud |
| score | int | IP reputation score |
| raw_payload | mediumtext nullable | Tam rapor |
| checked_at | datetime |  |

## ses_reputation_alerts / ses_ip_reputation_events

RBL monitoring state ve event geçmişi.

- `ses_reputation_alerts`: aktif/recovered alert state, cooldown, first/last seen.
- `ses_ip_reputation_events`: listed, reminder ve recovered olayları.

## Saklama Politikası

- `ses_mail_tests`: varsayılan 90 gün.
- `ses_events`: varsayılan retention ayarına göre temizlenir.
- `ses_sns_webhook_attempts`: kısa tanılama geçmişi olarak tutulur; raw payload kişisel veri içerebilir.
- `ses_reputation_snapshots`, `ses_blacklist_checks`, `ses_ip_reputation_checks`: raporlama için dönemsel snapshot tutar.
- Health cache domain üzerinde tutulur; manuel refresh cache'i bypass eder.

## Migration Kuralları

- Migration dosyaları idempotent yazılır.
- Kolon ekleme öncesi varlık kontrolü yapılır.
- Veri kaybettiren migration yoktur; kaldırılacak kolonlar önce deprecated bırakılır.
- Uninstall sırasında kullanıcı verisi varsayılan olarak silinmez.
