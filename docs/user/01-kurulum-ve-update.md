# Kurulum ve Update

Bu doküman SES Manager for Plesk extension'ının Plesk üzerinden kurulması, güncellenmesi, kaldırılması ve ilk açılış gereksinimlerini açıklar.

## Gereksinimler

- Plesk Obsidian 18.x.
- Linux tabanlı Plesk sunucusu.
- Plesk administrator yetkisi.
- Dış bağlantı erişimi:
  - AWS SES API endpointleri.
  - AWS SNS endpointleri.
  - Cloudflare API, Cloudflare entegrasyonu kullanılacaksa.
  - Public DNS resolver erişimi.
- DNS yönetimi Plesk veya Cloudflare üzerinden yapılacaksa ilgili yetkiler.

## Extension Plesk Catalog Üzerinden Nasıl Kurulur?

1. Plesk paneline administrator olarak giriş yapın.
2. Sol menüden `Extensions` bölümünü açın.
3. Arama kutusunda `SES Manager` arayın.
4. Extension detay sayfasını açın.
5. `Install` butonuna tıklayın.
6. Kurulum tamamlandıktan sonra sol menüde `SES Manager` görünür.
7. İlk açılışta EULA gösterilirse kabul edin.
8. Setup Wizard ile yapılandırmaya başlayın.

## ZIP ile Manuel Kurulum Yapılabilir mi?

Catalog dışı kurulum sadece test veya özel dağıtım için önerilir. Production kullanıcılar için önerilen yöntem Plesk Extensions Catalog kurulumudur.

Manuel ZIP kurulumu yapılacaksa:

1. Plesk panelinde `Extensions` bölümünü açın.
2. `Upload Extension` veya `Upload Extension Package` bölümünden ZIP dosyasını yükleyin.
3. Kurulum sonrası SES Manager menüsünü açın.
4. Setup Wizard'ı çalıştırın.

## Update Nasıl Yapılır?

Plesk Catalog üzerinden kurulu extension için:

1. Plesk `Extensions` bölümünü açın.
2. `Updates` veya extension detay sayfasını kontrol edin.
3. SES Manager için update görünüyorsa `Update` butonuna tıklayın.
4. Update sonrası SES Manager > Settings ve Advanced ekranlarını kontrol edin.

Update sonrasında:

- Mevcut AWS/SMTP/Cloudflare secret değerleri korunur.
- Veritabanı migration'ları idempotent çalışır.
- Yeni ayarlar varsayılan değerlerle eklenir.
- Debug/test dosyaları release paketine dahil edilmez.

## Update Öncesi Önerilen Kontroller

- Plesk ve extension yedeğinizin güncel olduğundan emin olun.
- Kritik mail altyapısı değişiklikleri yapıyorsanız update'i düşük trafik saatinde planlayın.
- Smarthost kullanıyorsanız mevcut mail çıkışı davranışını not alın.
- Çok sayıda domain yönetiliyorsa update sonrası DNS Health ekranını kontrol edin.

## Extension Kaldırılırsa Veriler Silinir mi?

Uninstall davranışı Plesk ve extension paket ayarlarına bağlıdır. SES Manager kullanıcı verisini gereksiz yere silmemek üzere tasarlanmıştır. Kaldırma işleminden önce aşağıdaki verileri yedeklemeniz önerilir:

- AWS/SMTP/Cloudflare yapılandırma durumu.
- Domain doğrulama listesi.
- DNS record planları.
- Bounce/complaint event geçmişi.
- Mail test geçmişi.
- Destek çıktısı.

## İlk Açılışta EULA Neden Gösterilir?

Plesk extension gereksinimleri gereği son kullanıcı lisans sözleşmesi extension kullanılmadan önce gösterilir ve kabul kaydı her Plesk sunucusunda saklanır. EULA versiyonu değişirse tekrar kabul istenebilir.

## Kurulumdan Sonra Hangi Menüleri Göreceğim?

- `Dashboard`: Genel durum ve kritik özet.
- `Domains`: Plesk domainleri ve SES identity işlemleri.
- `Reputation`: SES ve IP reputation kontrolleri.
- `DNS Health`: SPF, DKIM, DMARC, MX, SES sağlık kontrolleri.
- `Smarthost`: Plesk mail çıkışını SES SMTP relay'e yönlendirme.
- `Mail Test`: Test mail gönderimi.
- `Bounce/Complaint`: SNS eventleri ve feedback metrikleri.
- `Bulk Verify`: Toplu domain doğrulama.
- `Cloudflare`: Cloudflare DNS preview/apply.
- `Setup`: Kurulum sihirbazı.
- `Settings`: AWS, SMTP, DNS ve Cloudflare ayarları.
- `Advanced`: Veri saklama ve destek çıktısı.
