# Uygulama Yol Haritası

Bu yol haritası uygulama geliştirme sırasını, kabul kriterlerini ve testleri tanımlar.

## Durum Özeti

MVP ve genişletilmiş deliverability modülleri uygulanmış durumda. Aktif alanlar: Setup Wizard, AWS SES identity, Plesk DNS, Cloudflare DNS, smarthost, mail test, DNS Health auto-fix, SES Connection, Bounce/Complaint Center, SNS automation, SES feedback metrics, IP Reputation Center, RBL monitoring, auto domain verification, bulk verify ve Plesk custom buttons.

## Faz 0: Extension İskeleti - Tamamlandı

Hedef:

- Plesk standardına uygun `ses-manager` extension iskeleti oluşturmak.

İşler:

- `meta.xml`, `DESCRIPTION.md`, `CHANGES.md` oluştur.
- `htdocs/index.php`, controller klasörü, view klasörü ve script klasörü oluştur.
- `post-install.php` içinde migration ve scheduler setup iskeleti hazırla.
- Navigation hook ile SES Manager menüsünü ekle.

Kabul kriterleri:

- `plesk bin extension -r ses-manager` başarılı.
- Plesk panelde SES Manager menüsü görünüyor.
- Dashboard boş state ile açılıyor.

## Faz 1: Settings ve AWS Bağlantısı - Tamamlandı

Hedef:

- AWS credentials, region ve SMTP bilgilerini güvenli saklamak.

İşler:

- `SettingsController` ve `SetupController` formlarını yaz.
- `SettingsRepository` ile encrypted secret kaydı yap.
- `AwsSesService::testConnection()` ekle.
- `SmtpTestService::testCredentials()` ekle.
- Setup Wizard: AWS API, SES SMTP, sandbox, DNS/SNS/Cloudflare adımları.
- SES Connection kartı: API, sandbox/production, sending, SMTP.

Kabul kriterleri:

- Secret değerleri DB'de plaintext değil.
- Yanlış credential kullanıcıya güvenli hata veriyor.
- Doğru credential ile bağlantı testi OK.

## Faz 2: Domain Verification ve Plesk DNS - Tamamlandı

Hedef:

- Plesk domainlerini listelemek, SES identity oluşturmak ve Plesk DNS kayıtlarını eklemek.

İşler:

- `DomainsController` domain listesi.
- `AwsSesService::createEmailIdentity()` ve DKIM kayıt üretimi.
- `PleskDnsService::planChanges()` ve `apply()`.
- SPF merge fonksiyonu.
- DMARC merge ve aggregate reporting auto-fix.
- Custom MAIL FROM MX/TXT kayıtları.
- `ses_domains` ve `ses_dns_records` kayıtları.

Kabul kriterleri:

- Domain için SES identity oluşturuluyor.
- DKIM CNAME kayıtları preview ekranında görünüyor.
- Plesk DNS'e kayıtlar conflict olmadan ekleniyor.
- Mevcut SPF kaydı overwrite edilmiyor, merge ediliyor.

## Faz 3: DNS Health ve Deliverability Score - Tamamlandı

Hedef:

- SPF, DKIM, DMARC, MX ve SES durumunu kontrol etmek.

İşler:

- `DnsHealthController` ve health listesi.
- `HealthCheckService::checkDomain()`.
- `HealthCheckService::calculateScore()`.
- Manuel refresh ve cache TTL.
- Scheduler ile periyodik kontrol.
- Gelişmiş SPF/DMARC/DKIM analiz JSON'ları.
- Authoritative DNS lookup ile recursive cache gecikmesini azaltma.
- Fix SPF/DKIM/DMARC/MX/Fix All aksiyonları.

Kabul kriterleri:

- Her domain için SPF/DKIM/DMARC/MX/SES status görünüyor.
- Skor 0-100 arası hesaplanıyor.
- Cache sayesinde dashboard hızlı açılıyor.
- Manuel refresh güncel sonucu getiriyor.

## Faz 4: Smarthost ve Mail Test - Tamamlandı

Hedef:

- Plesk mail gönderimini SES SMTP üzerinden yapılandırmak ve test mail göndermek.

İşler:

- `SmarthostService::previewConfig()` ve `applyConfig()`.
- Config snapshot ve rollback fonksiyonları.
- `MailTestController` formu.
- `SmtpTestService::sendTestMail()`.
- `ses_mail_tests` geçmişi.

Kabul kriterleri:

- Admin smarthost config preview görebiliyor.
- Apply sonrası SMTP test çalışıyor.
- Test mail sonucu message ID veya hata ile kaydediliyor.
- Başarısız apply sonrası rollback mümkün.

## Faz 5: Public MVP Release - Tamamlandı

Hedef:

- Lisanssız public release paketini tamamlamak.

İşler:

- Runtime lisans ve domain limit kontrolleri kaldırıldı.
- Tüm feature gate'ler public build'de açık hale getirildi.
- `DESCRIPTION.md` ve `CHANGES.md` final.
- ZIP paket ve kurulum testi.

Kabul kriterleri:

- Tüm MVP ve Pro özellikler lisanssız çalışıyor.
- ZIP kurulup kaldırılabiliyor.
- Catalog checklist tamam.

## Pro Fazı - Büyük Ölçüde Tamamlandı

Hedef:

- Cloudflare, bounce/complaint ve operasyonel özellikleri eklemek.

İşler:

- `CloudflareController`, `CloudflareClient`, `CloudflareDnsService`.
- Zone detection ve DNS change preview/apply.
- `htdocs/public/sns-webhook.php` ve `WebhookService`.
- `BounceController` ve CSV export.
- Bulk verify long task.
- Plesk notifications.
- Yeni hosting oluşturulduğunda otomatik doğrulama hook'u.
- SNS confirmation görünürlüğü ve webhook attempts tanılama.
- SNS Raw Message Check ve identity notification topic auto-config.
- SES Feedback Metrics (`BatchGetMetricData`).
- IP Reputation Center: DNSBL, PTR, SMTP banner, HELO, ASN/provider.
- RBL monitoring ve recovery eventleri.

Kabul kriterleri:

- Cloudflare kayıtları overwrite yapmadan uygulanıyor.
- SNS imzasız payload reddediliyor.
- Bounce/complaint eventleri listeleniyor ve export ediliyor.
- Bulk verify paneli uzun iş olarak çalışıyor.

## Kalan / Sonraki İyileştirmeler

- Catalog öncesi `meta.xml` vendor, URL, support, help ve privacy alanları gerçek değerlerle değiştirilmeli.
- Open/click tracking aktivasyon akışı Pro özellik olarak tasarlanmalı.
- SNS/Webhook attempts retention temizliği scheduler'a bağlanmalı.
- Unit test seti repository dışında henüz otomatik CI olarak koşmuyor; SPF/DMARC merge, SNS normalize, DNS provider detection için testler eklenmeli.
- IAM policy ekranındaki izinler least-privilege Resource scope ile daraltılabilir.
- Multi-admin/reseller/domain owner yetki matrisi ayrı Plesk rollerinde manuel test edilmeli.

## Test Matrisi

Unit test:

- SPF merge.
- DMARC parse.
- DMARC merge ve `rua` auto-fix.
- Deliverability score.
- SNS payload normalize.
- `AmazonSnsSubscriptionSucceeded` ignore/ack davranışı.
- Feedback metrics disabled open/click durumu.
- Secret masking.

Integration test:

- AWS SES client mock.
- Cloudflare API mock.
- Plesk DNS adapter mock.
- Repository migration.

Manual Plesk test:

- Extension install/uninstall.
- Setup Wizard tamamlanması.
- Domain verification.
- DKIM/SPF/DMARC kayıt ekleme.
- Mail test.
- Cron health check.
- SNS webhook.
- SNS raw delivery kapatma.
- SES simulator bounce/complaint.
- New subscription/domain auto SES job.
- Cloudflare nameserver tespit + SES-only sync.
- IP reputation scheduler ve RBL alert recovery.

## Release Checklist

- `meta.xml` gerçek vendor, URL, support, help ve privacy bilgileriyle güncel.
- `DESCRIPTION.md` veri işleme ve public endpoint açıklamasını içeriyor.
- `CHANGES.md` sürüm notları güncel.
- Debug mode kapalı.
- Loglarda secret yok.
- IAM policy `ses:GetAccount`, `ses:BatchGetMetricData`, SES identity/send, SES notification topic ve SNS subscription izinlerini içeriyor.
- Bounce/Complaint webhook HTTPS public erişilebilir, Raw Message Delivery kapalı, Topic ARN allowlist doğru.
- `plesk bin extension -p ses-manager` başarılı.
- Temiz Plesk Obsidian 18.x sunucuda kurulum testi geçti.
