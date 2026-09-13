# Güvenlik ve Performans Planı

Bu extension mail altyapısı ve DNS değiştirdiği için güvenlik varsayılanları katı tutulur.

## Secret Yönetimi

- AWS Access Key, AWS Secret Key, SES SMTP password ve Cloudflare token plaintext saklanmaz.
- Secret değerleri `pm_Crypt` ile encrypt edilir.
- Formlarda secret alanları read-back yapmaz; sadece "configured" durumu gösterilir.
- Log maskesi şu pattern'leri kapatır: access key, secret, SMTP password, bearer token, authorization header.
- Public build lisans aktivasyonu, paket ayrımı ve domain limiti uygulamaz.
- Public build external license, entitlement veya subscription secret değerleri saklamaz.

## Yetki ve Session

- Controller'lar `pm_Controller_Action` tabanlı olur.
- Admin tüm sunucuyu yönetebilir.
- Reseller sadece erişebildiği domainleri görebilir.
- Domain owner sadece kendi domaininde health ve mail test görebilir; smarthost ve global AWS ayarı yapamaz.
- Domain bazlı işlemler `pm_Client` ve `pm_Session` erişim kontrolünden geçer.
- Mutating POST işlemlerinde CSRF token zorunludur.

## Public Webhook Güvenliği

- Auth bypass eden tek dosya `htdocs/public/sns-webhook.php` olur.
- Webhook sadece AWS SNS imza doğrulaması başarılıysa işlenir.
- SNS certificate URL'i AWS domain allowlist ile kontrol edilir.
- Settings'te Allowed SNS Topic ARN girildiyse sadece bu topic kabul edilir.
- SNS Raw Message Delivery açık payload'lar reddedilir; çünkü signed SNS envelope içermez.
- Raw Message Check aksiyonu SNS subscription attribute okuyup raw delivery'yi kapatabilir.
- Subscription confirmation otomatik yapılacaksa imza doğrulandıktan sonra yapılır.
- `AmazonSnsSubscriptionSucceeded` gibi signed informational SES bildirimleri event yazmadan başarılı acknowledge edilir.
- Payload boyutu limitlenir; büyük payload reddedilir.
- Event idempotency için `message_id` ve event type birlikte kontrol edilir.
- Webhook attempts kısa tanılama için saklanır; raw payload kişisel veri içerebileceği için retention planına dahil edilmelidir.

## DNS Güvenliği

- DNS değişiklikleri önce preview ekranında gösterilir.
- Existing TXT kayıtları overwrite edilmez.
- SPF için birden fazla `v=spf1` varsa kullanıcıya warning gösterilir.
- SPF merge örneği:

```text
Mevcut: v=spf1 include:_spf.google.com ~all
Gerekli: include:amazonses.com
Sonuç:  v=spf1 include:_spf.google.com include:amazonses.com ~all
```

- DKIM CNAME kayıtlarında mevcut farklı değer varsa otomatik overwrite yapılmaz; conflict olarak işaretlenir.
- DMARC kayıtları güvenli merge edilir; mevcut policy/alignment korunur, eksik `rua` ve `pct=100` eklenir.
- Birden fazla DMARC kaydı varsa otomatik overwrite yapılmaz; conflict olarak işaretlenir.
- Health check mümkünse authoritative nameserver'dan okur; recursive resolver cache'i eskiyse false warning azalır.

## AWS ve Cloudflare API Kuralları

- AWS SES API v2 kullanılır.
- SES domain doğrulama için `CreateEmailIdentity` çağrılır.
- SES Feedback Metrics için SES v2 `BatchGetMetricData` kullanılır ve `ses:BatchGetMetricData` izni gerekir.
- SES identity Bounce/Complaint topic bağlantısı için SES classic `SetIdentityNotificationTopic` kullanılır.
- SNS subscription raw delivery kontrolü için `ListSubscriptionsByTopic`, `GetSubscriptionAttributes`, `SetSubscriptionAttributes` gerekir.
- SMTP default endpoint `email-smtp.{region}.amazonaws.com:587 STARTTLS`.
- Cloudflare DNS API token minimum zone DNS edit yetkisine sahip olmalıdır.
- Client timeout default 10 saniyedir.
- Retry max 3 deneme ve exponential backoff ile yapılır.
- Rate limit veya throttling alınırsa işlem job olarak tekrar kuyruğa alınır.

## Smarthost Güvenliği

- Smarthost değişikliği öncesi mevcut config snapshot alınır.
- Apply işleminden sonra SMTP bağlantı testi yapılır.
- Test başarısızsa kullanıcıya rollback önerilir.
- Global mail config sadece admin tarafından değiştirilebilir.

## Logging ve Audit

- Teknik detaylar `pm_Log` içine maskeli yazılır.
- SNS, job, reputation ve mail test gibi tanılama kayıtları ilgili repository tablolarında tutulur.
- Diagnostic context JSON olarak tutulduğunda secret maskesi uygulanır.
- User-facing hata mesajlarında stack trace gösterilmez.

## Performans Stratejisi

- Dashboard her yüklemede AWS/Cloudflare çağırmaz; cache okur.
- Public build lisans API'sine veya Plesk additional license okuyucusuna çağrı yapmaz.
- DNS ve SES status cache TTL varsayılan 900 saniyedir.
- Bounce/Complaint Center feedback summary cached reputation snapshot kullanır; detaylı metrics SES API'den okunur ve yetki yoksa kullanıcıya kısa uyarı verilir.
- Manuel refresh cache'i bypass eder ve sonucu tekrar yazar.
- Domain listeleri sayfalı gösterilir.
- Bulk verify ve bulk health check `pm_LongTask_Manager` ile çalışır.
- Periyodik kontroller `pm_Scheduler` üzerinden parça parça yapılır.
- IP reputation/RBL monitoring günlük scheduler ile çalışır, aynı alert için 24 saat cooldown uygular.

## Background Job Kuralları

- Her job `ses_jobs` içinde status tutar.
- Aynı domain için aynı job türü çalışıyorsa yeni job kuyruğa alınmaz.
- Başarısız job max 3 kez denenir.
- Job payload secret içermez; sadece setting reference veya domain ID taşır.

## Veri Saklama

- Mail testleri 90 gün sonra temizlenir.
- Teknik loglar 30 gün sonra temizlenir.
- Bounce/complaint eventleri 180 gün saklanır.
- SNS webhook attempts ve confirmation kayıtları tanılama amaçlı sınırlı süre saklanmalıdır.
- Reputation snapshots ve blacklist checks raporlama için dönemsel temizlenmelidir.
- Retention ayarları Settings ekranından değiştirilebilir.

## Catalog Review Notları

- Public endpoint amacı ve imza doğrulaması `DESCRIPTION.md` içinde açıklanır.
- Hangi verilerin saklandığı ve neden saklandığı açıkça yazılır.
- Uninstall sırasında kullanıcı verisinin silinmediği belirtilir.
- Debug mod default kapalıdır.
- Debug/helper scriptleri release paketine dahil edilmez.
- Release paketi `scripts/build-release.sh` ile staging dizininde üretilir; test dosyaları, local notlar ve geçici cache/log dizinleri hariç tutulur.
- External API erişilemezse yalnızca ilgili entegrasyon degrade olur; kullanıcıya kısa ve maskeli hata mesajı gösterilir.
