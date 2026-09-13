# SES Manager for Plesk

SES Manager for Plesk, Plesk yöneticilerinin AWS SES gönderim altyapısını panel içinden kurmasını, domain doğrulamasını otomatikleştirmesini, DNS sağlık durumunu izlemesini ve Plesk mail çıkışını SES smarthost üzerinden yönetmesini sağlar.

## Aktif Özellikler

- AWS SES API v2 bağlantı testi.
- Adım adım Setup Wizard: AWS API, SES SMTP, SES sandbox çıkışı, DNS otomasyonu, Cloudflare ve SNS ayarları için rehberli kurulum.
- Setup tamamlandı ekranı ve bilgileri değiştirmek için tekrar başlatma aksiyonu.
- Dashboard ve Settings üzerinde SES Connection özeti: API, sandbox/production, sending ve SMTP durumları.
- SES Connection hata mesajlarında IAM/region problemleri, özellikle `ses:GetAccount` eksikliği açıkça belirtilir.
- Çoklu dil desteği: English ve Türkçe locale dosyaları. Diğer Plesk panel dilleri İngilizce fallback kullanır.
- SES domain identity oluşturma.
- SES identity durumunu AWS API üzerinden yenileme.
- Custom MAIL FROM domain yapılandırması (`bounce.domain.com` gibi).
- DKIM CNAME, MAIL FROM MX/TXT, SPF ve DMARC kayıtlarını üretme.
- Plesk DNS zone'a SES için gerekli kayıtları güvenli ekleme.
- SPF kayıtlarını overwrite etmeden merge etme.
- Plesk smarthost ayarlarını SES SMTP endpoint'ine göre yapılandırma.
- Panelden SMTP ve test mail kontrolü.
- SPF, DKIM, DMARC, MX ve SES doğrulama sağlık kontrolü.
- Gelişmiş DNS analizi: SPF lookup limiti, çoklu SPF, riskli policy, SES include, DMARC policy/reporting/alignment ve DKIM CNAME eşleşme detayları.
- DNS Health ekranından tek tık SPF, DKIM, DMARC ve MAIL FROM MX auto-fix.
- DMARC auto-fix mevcut policy/alignment değerlerini koruyarak `rua` ve `pct=100` ekler.
- DNS sağlık kontrolünde mümkünse authoritative nameserver sorgusu kullanılır; recursive cache kaynaklı eski sonuçlar azaltılır.
- Deliverability score.
- SES Reputation Center: sandbox/production durumu, günlük kullanım, gönderim limiti ve account health.
- IP Reputation Center: DNSBL, reverse DNS, forward-confirmed PTR, SMTP banner, HELO/Postfix kimlik kontrolü ve ASN/provider intelligence.
- RBL monitoring: günlük IP reputation scheduler, kritik DNSBL listelenmelerinde Plesk notification, 24 saat cooldown ve recovery event geçmişi.
- Yeni subscription/domain oluşturulduğunda opsiyonel otomatik SES identity + DNS kurulumu.
- Scheduler ile otomatik job runner ve periyodik sağlık kontrolü.
- Plesk domain dashboard ve mail alanlarından ilgili domainin SES yönetimine kısayol.
- Bounce/Complaint Center için AWS SNS imza doğrulamalı public webhook.
- Bounce/Complaint Center içinde AWS SES feedback metrics: sent, delivered, complaint, transient/permanent bounce, open ve click metrikleri.
- SNS Topic ARN allowlist ile webhook kaynak kısıtlama.
- Bounce/Complaint Center üzerinden SNS Raw Message Delivery kontrolü ve tek tık pasifleştirme.
- Allowed SNS Topic ARN girildiğinde SES identity Bounce/Complaint feedback topic bağlantılarını API üzerinden otomatik yapılandırma.
- Yeni domain otomasyonunda SNS aktifse ilgili domain identity için Bounce/Complaint topic bağlantısını otomatik deneme.
- Secret değerlerini `pm_Crypt` ile encrypted saklama.
- Public dağıtımda lisans aktivasyonu, paket ayrımı ve domain limiti yoktur; tüm özellikler varsayılan olarak açıktır.
- Release-ready paketleme: debug scriptleri ve test fixture'ları canlı ZIP dışında bırakacak staging build akışı.

## Gelişmiş Özellikler

- Cloudflare DNS otomasyonu: domain için zone tespiti, opsiyonel zone oluşturma, DKIM/SPF/DMARC/MAIL FROM kayıt preview ve apply.
- Cloudflare sync mode: tüm Plesk DNS kayıtlarını eşitleme veya yalnızca SES için gerekli kayıtları eşitleme.
- Cloudflare Account ID desteği: çoklu account erişiminde zone oluşturma hedefi net seçilebilir.
- Toplu domain doğrulama: seçilen domainler için `pm_LongTask_Manager` üzerinden arka planda SES identity + DNS kaydı planlama.
- Bounce/Complaint Center: SES bounce/complaint/delivery eventlerinin listesi ve CSV export.
- Kritik domainler için Plesk bildirimi.

## Pasif / Opsiyonel Özellikler

- Otomatik yeni domain SES kurulumu varsayılan olarak kapalıdır; Settings üzerinden açılır.
- Cloudflare entegrasyonu token girilmeden çalışmaz; token yoksa Cloudflare adımı sessizce atlanır.
- SNS bounce/complaint kayıtları için AWS SNS topic ve HTTPS subscription gerekir; topic ARN girildikten sonra raw delivery ve identity feedback bağlantıları panelden otomatik yapılandırılabilir.
- Open/Click metrikleri AWS engagement tracking/VDM verisi yoksa pasif görünür; aktivasyon akışı Pro özellik olarak planlanmıştır.
- Smarthost uygulaması manuel butonla yapılır; Settings'teki durum bilgisi ve Smarthost sayfasındaki aksiyon birlikte kullanılır.
- Public dağıtımda lisans anahtarı gerekmez.

## Gereksinimler

- Plesk Obsidian 18.x.
- Linux sunucu.
- AWS SES production-ready account veya test amaçlı sandbox account.
- AWS Access Key ve Secret Key.
- SES SMTP credentials.
- Cloudflare otomasyonu için Cloudflare API token.
- Cloudflare zone oluşturma gerekiyorsa token için Account Read, Zone Read, Zone Edit ve DNS Edit izinleri.
- Bounce/Complaint Center için AWS SNS topic ve SES feedback notification ayarı.
- SES Feedback Metrics için IAM kullanıcısında `ses:BatchGetMetricData` izni.
- SNS otomasyonu için IAM kullanıcısında `ses:SetIdentityNotificationTopic`, `ses:GetIdentityNotificationAttributes`, `sns:ListSubscriptionsByTopic`, `sns:GetSubscriptionAttributes` ve `sns:SetSubscriptionAttributes` izinleri.

## Lisans

Bu public build lisans aktivasyonu, paket ayrımı ve domain limiti uygulamaz. Eklenti kaynak kodu public olarak paylaşılmak üzere lisans kısıtlarından arındırılmıştır.

## Ucuncu Taraf Ucretleri

SES Manager lisansi AWS, Cloudflare, DNS, hosting veya diger ucuncu taraf servis ucretlerini kapsamaz. Eklentinin kesintisiz calismasi icin Amazon SES/SNS ve kullanilan diger servislerin aktif ve odemelerinin sorunsuz olmasi gerekir.

Bilgilendirme amacli AWS SES baz fiyat ornekleri:

- Outbound email: 1.000 e-posta icin 0,10 USD.
- Attachment data: GB basina 0,12 USD.
- Dedicated IP Standard: IP basina aylik 24,95 USD.
- Virtual Deliverability Manager: 1.000 e-posta icin 0,07 USD ek ucret.
- AWS SNS: topic kullaniminda API request, notification delivery, payload ve data transfer kalemlerine gore kullandikca ode modeli.

Basit outbound hesap ornegi: yalnizca SES outbound baz fiyatiyla yilda 120.000 e-posta yaklasik 12 USD, yilda 1.200.000 e-posta yaklasik 120 USD eder. Attachment data, inbound mail, SNS eventleri, VDM, dedicated IP, EC2/data transfer, vergiler, free tier ve AWS fiyat degisiklikleri bu tahmine dahil degildir. Guncel fiyatlar icin AWS SES Pricing ve AWS SNS Pricing sayfalarini kontrol edin:

- https://aws.amazon.com/ses/pricing/
- https://aws.amazon.com/sns/pricing/

## Veri İşleme

Extension AWS credentials, SES SMTP credentials ve Cloudflare token değerlerini encrypted olarak saklar. Mail test geçmişi, DNS sağlık sonuçları, SES identity durumları ve bounce/complaint eventleri extension veritabanında tutulur.

Public endpoint yalnızca AWS SNS webhook için kullanılır. Payload, AWS SNS imza doğrulaması başarılı olmadan işlenmez. Settings'te SNS Topic ARN girilirse yalnızca o topic'ten gelen imzalı mesajlar kabul edilir.

## Güvenlik

- Secret değerleri plaintext saklanmaz.
- Loglarda token, password ve Authorization header maskelenir.
- DNS değişiklikleri preview sonrası uygulanır.
- SPF kayıtları overwrite edilmez, güvenli merge yapılır.
- Cloudflare/Plesk DNS çakışmalarında mevcut farklı kayıt overwrite edilmez; işlem conflict/partial olarak işaretlenir.
- Riskli işlemler sadece yetkili Plesk kullanıcıları tarafından yapılır.

## Dokümantasyon

Teknik plan ve geliştirme yol haritası için `docs/` klasörüne bakın.
