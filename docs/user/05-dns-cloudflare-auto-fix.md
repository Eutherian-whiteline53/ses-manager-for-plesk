# DNS, Cloudflare ve Auto Fix

SES Manager, Amazon SES için gerekli DNS kayıtlarını üretir, Plesk DNS veya Cloudflare üzerinde güvenli şekilde uygulamaya yardımcı olur.

## SES İçin Hangi DNS Kayıtları Kullanılır?

Domain doğrulama ve teslim edilebilirlik için tipik kayıtlar:

- DKIM CNAME kayıtları.
- Custom MAIL FROM MX kaydı.
- Custom MAIL FROM TXT/SPF kaydı.
- Root domain SPF kaydı veya SPF merge.
- DMARC TXT kaydı.

Örnek custom MAIL FROM:

```text
bounce.example.com
```

Örnek SES feedback MX:

```text
10 feedback-smtp.eu-central-1.amazonses.com
```

## SPF Merge Nasıl Çalışır?

SES Manager mevcut SPF kaydını ezmez. Var olan SPF içine `include:amazonses.com` eklemeye çalışır.

Örnek:

```text
Mevcut: v=spf1 include:_spf.google.com ~all
Sonuç:  v=spf1 include:_spf.google.com include:amazonses.com ~all
```

Birden fazla SPF kaydı varsa otomatik düzeltme riskli kabul edilir ve conflict/warning oluşabilir.

## DMARC Auto Fix Nasıl Çalışır?

DMARC kaydı yoksa varsayılan policy ile kayıt üretir.

Varsayılan örnek:

```text
v=DMARC1; p=none; rua=mailto:dmarc@example.com; pct=100
```

Mevcut DMARC kaydı varsa:

- Mevcut policy korunur.
- Mevcut alignment ayarları korunur.
- Eksikse `rua` eklenir.
- Eksikse `pct=100` eklenir.

Birden fazla DMARC kaydı varsa otomatik overwrite yapılmaz.

## Plesk DNS ile Kullanım

Plesk DNS kullanıyorsanız:

1. Domain ekranından SES identity oluşturun.
2. DNS kayıtlarını inceleyin.
3. `Apply DNS Changes` butonunu kullanın.
4. DNS Health ekranında refresh yapın.

SES Manager Plesk DNS üzerinde:

- Yeni kayıt ekleyebilir.
- SPF merge için mevcut kaydı kaldırıp güncel kaydı ekleyebilir.
- Conflict durumunda farklı mevcut kaydı ezmez.

## Cloudflare ile Kullanım

Cloudflare DNS kullanıyorsanız:

1. Cloudflare API token oluşturun.
2. SES Manager > Settings veya Cloudflare ekranına token girin.
3. Gerekirse Cloudflare Account ID girin.
4. Sync mode seçin:
   - Sadece SES kayıtları.
   - Tüm Plesk DNS kayıtları.
5. Cloudflare ekranında domain için preview alın.
6. Değişiklikleri inceleyin.
7. `Apply Changes` ile uygulayın.

## Cloudflare Token İzinleri

Minimum önerilen izinler:

- Zone Read
- DNS Edit

Zone oluşturma veya çoklu account senaryosu gerekiyorsa ek izinler:

- Account Read
- Zone Edit

Token mümkünse yalnızca gerekli account/zone ile sınırlandırılmalıdır.

## Cloudflare Account ID Ne Zaman Gerekir?

Bir Cloudflare hesabında birden fazla account varsa veya SES Manager'ın eksik zone oluşturması bekleniyorsa Account ID kullanılır.

Account ID 32 karakter hex formatında olmalıdır.

## DNS Health Ekranı Ne Kontrol Eder?

DNS Health aşağıdaki başlıkları kontrol eder:

- SES verification status.
- SPF.
- DKIM.
- DMARC.
- MAIL FROM MX.
- Deliverability score.
- Son kontrol zamanı.

Gelişmiş DNS analizi şu detayları da gösterebilir:

- SPF DNS lookup sayısı.
- Çoklu SPF problemi.
- SES include var mı?
- DMARC policy.
- DMARC reporting.
- DKIM CNAME eşleşmeleri.
- MAIL FROM alignment.

## Auto Fix Hangi Dağıtımda Var?

Public build lisans veya paket kontrolü yapmaz. SPF, DKIM, DMARC, MAIL FROM Auto Fix, yeni domain otomasyonu ve Cloudflare DNS otomasyonu gerekli AWS/Cloudflare yetkileri sağlandığında açıktır.

## DNS Değişiklikleri Neden Hemen Pass Olmuyor?

Muhtemel nedenler:

- DNS propagation devam ediyor.
- Authoritative nameserver farklı.
- Domain Cloudflare kullanıyor ama Plesk DNS'e kayıt eklendi.
- Cloudflare proxy veya yanlış zone seçildi.
- Eski recursive DNS cache sonucu dönüyor.
- DKIM tokenları SES tarafında henüz aktif görünmüyor.

Çözüm:

1. Domainin nameserverlarını kontrol edin.
2. Kayıtların gerçekten authoritative DNS'te olduğunu doğrulayın.
3. DNS Health refresh yapın.
4. Bir süre bekleyip tekrar kontrol edin.

## Conflict Ne Anlama Gelir?

Conflict, SES Manager'ın mevcut bir kaydı güvenli şekilde değiştiremeyeceği anlamına gelir.

Örnekler:

- DKIM CNAME aynı isimde var ama farklı değere gidiyor.
- Aynı domain için birden fazla SPF kaydı var.
- DMARC kaydı birden fazla.
- Cloudflare'de aynı kayıt farklı içerikle mevcut.

Bu durumda kayıtları manuel inceleyin. SES Manager güvenlik nedeniyle overwrite yapmaz.
