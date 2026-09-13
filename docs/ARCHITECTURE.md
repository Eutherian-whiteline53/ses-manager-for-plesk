# Mimari Plan

Extension Plesk MVC yapısını kullanır. Controller'lar sadece request, form ve response yönetir; AWS, DNS, SMTP ve sağlık kontrolü service katmanında kalır.

## Controller Katmanı

Tüm controller'lar `pm_Controller_Action` tabanlı olur.

- `IndexController`: Dashboard, skor özeti, son hatalar, kritik DNS/SES uyarıları.
- `SetupController`: adım adım kurulum sihirbazı; AWS API, SES SMTP, sandbox, DNS otomasyon, Cloudflare ve SNS ayarları.
- `DomainsController`: Plesk domain listesi, SES identity oluşturma, DKIM kayıtlarını gösterme.
- `DnsHealthController`: SPF, DKIM, DMARC, MX kontrolleri ve manuel refresh.
- `MailTestController`: test mail formu, SMTP bağlantı sonucu, provider message ID.
- `BounceController`: SNS rehberi, feedback metrics, webhook diagnostics, confirmation listesi, bounce/complaint/delivery eventleri ve CSV export.
- `ReputationController`: SES reputation snapshot, sandbox/production, quota, IP reputation, DNSBL, ASN ve RBL alert geçmişi.
- `CloudflareController`: token kaydı, zone tespiti, DNS kayıt preview ve apply.
- `SettingsController`: default SPF/DMARC policy, retention, cache TTL, cron ayarları.

Controller kuralları:

- Her mutating action CSRF kontrolü yapar.
- Her domain action `pm_Client` / `pm_Session` ile erişim kontrolü yapar.
- Uzun süren işlemler `pm_LongTask_Manager` veya scheduler job'a aktarılır.
- Hata mesajları kullanıcıya kısa, teknik detaylar `pm_Log` ve ilgili diagnostic tablolarına maskeli yazılır.

## Service Katmanı

Dosya konumu: `plib/library/Service/`.

- `AwsSesService`
  - `testConnection(string $region): ConnectionResult`
  - `createEmailIdentity(string $domain, string $region): SesIdentityResult`
  - `getIdentityStatus(string $domain, string $region): IdentityStatus`
  - `getDkimRecords(SesIdentityResult $identity): DnsRecord[]`
  - `getAccount(string $region): array`
  - `configureMailFromDomain(string $domain, string $mailFromDomain): void`
- `ReputationService`
  - SES account/quota/sandbox snapshot üretir.
  - Bounce/complaint oranlarını eventlerden hesaplayıp `ses_reputation_snapshots` içine yazar.
- `FeedbackMetricsService`
  - SES v2 `BatchGetMetricData` ile sent, delivered, bounce, complaint, open ve click metriklerini okur.
  - Open/click tracking verisi yoksa ilgili metrikleri disabled gösterir.
- `SesConnectionService`
  - Dashboard ve Settings için API/sandbox/sending/SMTP bağlantı özetini cache'li üretir.
- `SmtpTestService`
  - `testCredentials(string $host, int $port, string $username, string $password): SmtpTestResult`
  - `sendTestMail(MailTestRequest $request): MailTestResult`
- `PleskDnsService`
  - `planChanges(string $domain, DnsRecord[] $records): DnsChangeResult[]`
  - `apply(string $domain, DnsChangeResult[] $plans): DnsChangeResult[]`
  - `mergeSpf(string $currentValue, string $requiredInclude): string`
  - `mergeDmarc(string $currentValue, string $domain, string $fallbackValue): string`
- `CloudflareDnsService`
  - `detectZone(string $domain): ?CloudflareZone`
  - `previewChanges(string $domain, DnsRecord[] $records): DnsChange[]`
  - `applyChanges(DnsChange[] $changes): DnsChangeResult[]`
- `SmarthostService`
  - `previewConfig(string $region): SmarthostConfig`
  - `applyConfig(SmarthostConfig $config): SmarthostResult`
  - `rollbackLastConfig(): SmarthostResult`
- `HealthCheckService`
  - `checkDomain(string $domain): HealthReport`
  - `calculateScore(HealthReport $report): int`
  - `parseDmarc(string $txt): DmarcPolicy`
- `DnsAutoFixService`
  - DNS Health ekranındaki Fix SPF/DKIM/DMARC/MX ve Fix All aksiyonlarını uygular.
  - SPF ve DMARC kayıtlarını overwrite etmeden güvenli merge eder.
- `DomainVerificationService`
  - Manuel identity planlama ve otomatik identity + DNS apply akışlarını yönetir.
  - SNS topic aktifse identity Bounce/Complaint notification topic bağlantısını dener.
- `AutoDomainJobRunner`
  - Event listener tarafından kuyruğa alınan `auto_domain_verify` işlerini parçalı işler.
- `WebhookService`
  - `verifySnsSignature(array $headers, string $payload): bool`
  - `handleSnsPayload(string $payload): WebhookResult`
  - Subscription confirmation, raw message uyarısı, topic allowlist ve unsupported signed notification handling.
- `SnsSetupService`
  - SNS Raw message delivery kontrolü/pasifleştirme.
  - SES identity Bounce/Complaint feedback topic bağlantılarını API ile kurma.
- `IpReputationService`
  - DNSBL, PTR, forward-confirmed PTR, SMTP banner, HELO/Postfix ve ASN/provider kontrolü.
- `IpReputationAlertService`
  - Günlük RBL monitoring, cooldown ve recovery eventlerini yönetir.
- `LicenseService`
  - Public build için geriye uyumlu no-op feature kapısıdır; tüm feature'lar açık, domain limiti yoktur.

## Repository Katmanı

Dosya konumu: `plib/library/Repository/`.

- `SettingsRepository`: encrypted settings ve feature flags.
- `DomainRepository`: domain status, SES identity, son health sonucu.
- `DnsRecordRepository`: beklenen ve uygulanan DNS kayıtları.
- `MailTestRepository`: test mail geçmişi.
- `EventRepository`: bounce/complaint eventleri.
- `JobRepository`: scheduler ve long task durumları.
- `ReputationSnapshotRepository`: SES reputation/account snapshot.
- `SnsConfirmationRepository`: SNS SubscribeURL ve confirmation sonucu.
- `SnsWebhookAttemptRepository`: webhook attempt, HTTP status ve hata nedeni.
- `BlacklistCheckRepository`: DNSBL sorgu sonuçları.
- `IpReputationCheckRepository`: PTR/banner/HELO/ASN snapshot.
- `IpReputationAlertRepository`: RBL alert state ve event geçmişi.

Repository kuralları:

- Raw SQL merkezi repository içinde kalır.
- Kullanıcı girdileri parametreli query ile yazılır.
- Büyük payload alanları JSON string olarak saklanır, listelerde özet gösterilir.

## Client Katmanı

Dosya konumu: `plib/library/Client/`.

- `AwsSesClient`: AWS SES API v2 HTTP/SDK wrapper.
- `AwsSesClassicClient`: SES classic Query API; identity notification topic bağlantıları.
- `AwsSnsClient`: SNS Query API; subscription attribute ve raw delivery kontrolü.
- `AwsQueryClient`: AWS SigV4 Query API ortak client.
- `CloudflareClient`: Cloudflare DNS API wrapper.
- `PleskCliClient`: Plesk CLI çağrıları için güvenli wrapper.
- `DnsResolverClient`: TXT, CNAME, MX ve NS lookup; mümkünse authoritative nameserver üzerinden okur, yoksa PHP DNS fallback kullanır.

Client kuralları:

- Timeout default 10 saniye.
- Retry sadece idempotent read veya safe upsert öncesi preview adımlarında yapılır.
- Exponential backoff kullanılır.
- Secret ve Authorization header loglanmaz.

## Event, Custom Button ve Job Katmanı

Dosya konumu: `library/EventListener.php`, custom button hook sınıfları ve `sbin/`.

- `library/EventListener.php`: `domain_create` ve `site_create` olaylarını dinler, auto SES açıksa job kuyruğuna alır.
- `pm_Hook_CustomButtons`: domain dashboard ve mail alanına admin-only SES kısayolları ekler.
- `sbin/job-runner.php`: otomatik domain doğrulama kuyruğunu işler.
- `sbin/health-check.php`: cache TTL dolmuş domainlerde SES/DNS health yeniler ve kritik domain notification üretir.
- `sbin/ip-reputation-check.php`: günlük IP reputation/RBL monitoring çalıştırır.
- `BulkVerifyTask`: seçilen domainleri `pm_LongTask_Manager` ile arka planda doğrular.

## View Katmanı

Dosya konumu: `plib/views/scripts/`.

- Formlar `pm_Form_Simple` ile hazırlanır.
- Tablolar `pm_View_List_Simple` ile sayfalı gösterilir.
- DNS değişiklikleri apply edilmeden önce preview ekranında gösterilir.
- Cloudflare ve smarthost gibi riskli aksiyonlarda confirmation formu kullanılır.
- Setup Wizard tamamlandı durumunu gösterir ve tekrar başlatma aksiyonu sunar.
- Bounce/Complaint rehberi FAQ olarak kapalı gelir; webhook diagnostics ve feedback metrics aynı ekranda gösterilir.
