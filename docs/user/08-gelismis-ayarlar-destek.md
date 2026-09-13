# Gelişmiş Ayarlar, Veri Saklama ve Destek Çıktısı

Advanced / Gelişmiş sayfası retention ayarlarını ve destek çıktısını yönetir.

## Gelişmiş Ayarlar Nerede?

Plesk panelinde:

```text
SES Manager > Advanced
```

Bu sayfada iki ana bölüm vardır:

- Retention and Cleanup.
- Support Output.

## Hangi Veriler İçin Saklama Süresi Ayarlanır?

| Ayar | Varsayılan | Açıklama |
| --- | ---: | --- |
| Mail test retention | 90 gün | Test mail geçmişi |
| Bounce/complaint event retention | 180 gün | SES event kayıtları |
| SNS webhook attempt retention | 30 gün | Webhook deneme ve confirmation kayıtları |
| Job history retention | 30 gün | Background job geçmişi |
| SES reputation snapshot retention | 90 gün | SES metrik snapshotları |
| IP reputation data retention | 90 gün | DNSBL, PTR, SMTP banner ve IP reputation kayıtları |
| Local log file retention | 30 gün | Extension local log dosyaları |

## Retention Cleanup Neleri Silmez?

Retention cleanup şunları silmez:

- AWS credentials.
- SES SMTP credentials.
- Cloudflare token.
- Domain listesi.
- DNS record planlarının aktif yapılandırması.
- EULA kabul kaydı.
- Extension ana ayarları.

Cleanup yalnızca operasyonel geçmiş ve tanılama kayıtlarını hedefler.

## Temizliği Manuel Çalıştırma

1. SES Manager > Advanced ekranına gidin.
2. Saklama günlerini kontrol edin.
3. `Run Cleanup Now` / `Temizliği Şimdi Çalıştır` butonuna tıklayın.
4. Silinen kayıt/dosya sayısı panel mesajında gösterilir.

## Destek Çıktısı Nedir?

Destek çıktısı, hata giderme için üretilen maskelenmiş JSON dosyasıdır.

İçerik:

- Üretim zamanı.
- Extension ID, version, release.
- Sunucu hostname.
- OS ve PHP bilgisi.
- Plesk version.
- Maskelenmiş SES Manager ayarları.
- Veritabanı tablo özetleri.
- Kurulu Plesk extension listesi.
- Local log dosyası listesi.

## Destek Çıktısı Secret İçerir mi?

Secret değerler açık yazılmaz. Aşağıdaki değerler `configured` veya `missing` olarak maskelenir:

- AWS Access Key.
- AWS Secret Key.
- SES SMTP username.
- SES SMTP password.
- Cloudflare API token.

Yine de destek ekibine göndermeden önce JSON çıktısını gözden geçirin.

## Destek Çıktısı Nasıl Alınır?

1. SES Manager > Advanced ekranına gidin.
2. Support Output bölümünü açın.
3. Önizlemeyi kontrol edin.
4. `Download Support Output` butonuna tıklayın.
5. İndirilen JSON dosyasını destek talebine ekleyin.

## Destek Talebine Ne Yazılmalı?

Şu format önerilir:

```text
Konu: SES Manager - [ekran/özellik] hatası

Plesk sunucusu:
Domain:
AWS region:
İşlem:
Hata mesajı:
Ne zaman başladı:
Son değişiklikler:
Ek dosya: support-output.json
```

## Loglar Nerede Tutulur?

Extension teknik logları Plesk log sistemi ve local extension log dizininde tutulabilir. Advanced ekranındaki destek çıktısı local log dosyalarının ad, boyut ve son değişiklik bilgilerini listeler.

Stack trace veya secret değerler kullanıcı ekranlarında gösterilmez.

## Veri Saklama Politikası Nasıl Seçilmeli?

Öneriler:

- Düşük trafikli sistem: varsayılan değerler yeterlidir.
- Yoğun bounce/complaint alan sistem: event retention 90 güne düşürülebilir.
- Uzun dönem raporlama isteyen ajans: event ve reputation retention 180-365 gün yapılabilir.
- Disk alanı kısıtlı sunucu: webhook/job/log retention 7-14 gün yapılabilir.

## Privacy Policy ile İlişki

Privacy Policy içinde extension'ın hangi verileri işlediği açıklanır. Advanced retention ayarları, local Plesk sunucusunda bu verilerin ne kadar tutulacağını yönetir.
