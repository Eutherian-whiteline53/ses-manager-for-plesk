# Soru Cevap ve Hata Giderme

Bu doküman SES Manager'da görülebilecek yaygın hata ve durumları açıklar.

## Genel

### SES Manager ne yapar?

Plesk içinden Amazon SES domain doğrulaması, DNS health, Cloudflare/Plesk DNS kayıtları, smarthost, mail test, SNS bounce/complaint eventleri ve reputation takibi yapar.

### Eklenti AWS hesabımı yönetir mi?

Hayır. Extension yalnızca verdiğiniz IAM izinleri kapsamında API çağrısı yapar. AWS hesabınızın tamamını yönetmez.

### Secret değerler nerede saklanır?

AWS, SMTP ve Cloudflare secret değerleri Plesk encryption altyapısı ile şifreli saklanır. Formlarda geri gösterilmez.

## Kurulum Hataları

### Save and Continue sonrası 500 hatası alıyorum

Muhtemel nedenler:

- Eski extension sürümü.
- Plesk route path bozulmuş link.
- Browser cache veya Plesk opcache.

Çözüm:

1. Extension'ın güncel olduğundan emin olun.
2. Sayfayı Plesk menüsünden yeniden açın.
3. Update sonrası Plesk service restart gerekebilir.
4. Destek çıktısı alın.

### Setup Wizard başlangıç butonu yanlış URL'ye gidiyor

Güncel sürümde başlangıç hedefi setup wizard anchor'ına yönlendirilmelidir. Hâlâ eski URL görünüyorsa extension cache veya eski sürüm olabilir.

## AWS SES Hataları

### SES API connection failed

Muhtemel neden:

- Yanlış region.
- `ses:GetAccount` izni yok.
- Access Key/Secret Key hatalı.
- AWS key inactive.

Çözüm:

1. Region kontrol edin.
2. IAM policy'yi kontrol edin.
3. Access Key ve Secret Key'i yeniden kaydedin.
4. AWS Console'da key aktif mi bakın.

### Account sandbox modunda

Bu hata SES hesabının production access almadığını gösterir.

Çözüm:

1. AWS SES Account dashboard'a gidin.
2. Production access talebi açın.
3. Talepte gönderim amacı ve bounce/complaint yönetimini açıklayın.

### SES identity oluşturulamıyor

Muhtemel neden:

- `ses:CreateEmailIdentity` izni yok.
- Domain formatı geçersiz.
- Region yanlış.
- AWS API erişimi yok.

Çözüm:

1. IAM policy içinde `ses:CreateEmailIdentity` kontrol edin.
2. Domainin Plesk'te doğru göründüğünü kontrol edin.
3. Region'u kontrol edin.

## SMTP ve Mail Test Hataları

### SMTP credentials missing

SES SMTP bilgileri girilmemiştir.

Çözüm:

1. Amazon SES > SMTP settings içinde SMTP credentials oluşturun.
2. SES Manager > Settings ekranına SMTP username/password girin.

### SMTP authentication failed

Muhtemel neden:

- IAM key SMTP credential sanıldı.
- SMTP password yanlış.
- Region uyuşmuyor.

Çözüm:

1. SES SMTP credentials'ı yeniden oluşturun.
2. Doğru regionda oluşturduğunuzdan emin olun.
3. Settings ekranından tekrar kaydedin.

### Test mail gönderilemiyor

Kontroller:

- From domain SES'te verified mı?
- Sandbox modunda doğrulanmamış alıcıya mı gönderiyorsunuz?
- `ses:SendRawEmail` izni var mı?
- Port 587 erişimi var mı?

## DNS Hataları

### DKIM missing

DKIM CNAME kayıtları authoritative DNS'te yoktur veya henüz yayılmamıştır.

Çözüm:

1. Domain detayında DKIM kayıtlarını kontrol edin.
2. Plesk DNS veya Cloudflare üzerinde kayıtların eklendiğini doğrulayın.
3. DNS Health refresh yapın.

### SPF warning veya missing

Muhtemel neden:

- SPF kaydı yok.
- `include:amazonses.com` yok.
- Birden fazla SPF kaydı var.
- SPF lookup limiti aşılıyor.

Çözüm:

1. DNS Health > SPF analizini açın.
2. Auto Fix varsa kullanın.
3. Birden fazla SPF varsa manuel sadeleştirin.

### DMARC missing

DMARC kaydı yoktur.

Çözüm:

1. Auto Fix kullanın veya `_dmarc.domain.com` TXT kaydı ekleyin.
2. İlk aşamada `p=none` ile başlayabilirsiniz.

### DNS Fix Unknown Provider

Nameserver Plesk veya Cloudflare olarak tanınmadı.

Çözüm:

1. Domain nameserverlarını kontrol edin.
2. DNS hangi sağlayıcıdaysa kayıtları orada uygulayın.
3. Cloudflare kullanıyorsanız token ve zone erişimini kontrol edin.

## Cloudflare Hataları

### Cloudflare token missing

Cloudflare API token girilmemiştir.

Çözüm:

1. Cloudflare token oluşturun.
2. Settings > Cloudflare alanına girin.

### Cloudflare zone not found

Muhtemel neden:

- Domain Cloudflare hesabınızda yok.
- Token o zone'a erişemiyor.
- Yanlış account ID girildi.

Çözüm:

1. Cloudflare dashboard'da zone var mı kontrol edin.
2. Token zone scope'unu kontrol edin.
3. Account ID doğru mu kontrol edin.

### Cloudflare API request failed

Muhtemel neden:

- Token iptal edildi.
- DNS Edit izni yok.
- Rate limit.
- Cloudflare API geçici hata.

Çözüm:

1. Token izinlerini kontrol edin.
2. Yeni token oluşturup deneyin.
3. Bir süre sonra tekrar deneyin.

## SNS Hataları

### SNS Raw message delivery is enabled

SNS subscription Raw Message Delivery açık.

Çözüm:

1. Bounce/Complaint ekranında Raw Message Kontrol butonunu kullanın.
2. AWS SNS subscription içinde `RawMessageDelivery=false` yapın.

### TopicArn does not match Allowed SNS Topic ARN

Webhook'a gelen SNS topic, Settings'te izin verilen Topic ARN ile uyuşmuyor.

Çözüm:

1. Settings > Allowed SNS Topic ARN değerini kontrol edin.
2. SNS topic region/account doğru mu kontrol edin.

### SNS subscription pending confirmation

AWS confirmation isteği henüz tamamlanmamış.

Çözüm:

1. Webhook URL'nin public HTTPS erişilebilir olduğundan emin olun.
2. Bounce/Complaint ekranında confirmation kayıtlarını kontrol edin.
3. SNS subscription'ı tekrar oluşturun.

### Bounce eventleri gelmiyor

Kontroller:

- SES identity feedback topic ayarlı mı?
- SNS subscription confirmed mı?
- Raw Message Delivery kapalı mı?
- Allowed Topic ARN doğru mu?
## Bulk Verify Hataları

### Seçilen domainlerin bir kısmı atlandı

Seçilen domainlerin bir kısmı Plesk domain listesinde bulunamadığı veya geçersiz payload ürettiği için işleme alınmadı.

Çözüm:

1. Domain seçimini yenileyin.
2. Plesk domain listesini kontrol edin.
3. İşlem başarısız domainleri tek tek deneyin.

### Bulk job partial

Toplu işin bir kısmı başarılı, bir kısmı başarısızdır.

Çözüm:

1. Job geçmişindeki hata mesajını kontrol edin.
2. Başarısız domainleri tek tek açıp DNS/AWS hatasını inceleyin.

## Destek Talebi Açarken

Destek talebine şunları ekleyin:

- Ekran adı.
- Domain adı.
- AWS region.
- Hata mesajı.
- İşlem zamanı.
- Advanced > Support Output JSON dosyası.

Secret değerleri destek talebine yazmayın.
