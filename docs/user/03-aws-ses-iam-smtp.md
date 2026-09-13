# AWS SES, IAM ve SES SMTP

SES Manager'ın Amazon SES ile çalışması için iki ayrı credential gerekir:

1. AWS API credential: Access Key ID ve Secret Access Key.
2. SES SMTP credential: SMTP username ve SMTP password.

Bu iki credential aynı şey değildir.

## AWS API Credential Ne İçin Kullanılır?

AWS API credential şu işlemler için kullanılır:

- SES account durumunu okuma.
- Sandbox/production durumunu kontrol etme.
- Sending enabled durumunu kontrol etme.
- SES domain identity oluşturma.
- SES identity durumunu okuma.
- Custom MAIL FROM ayarlama.
- SES reputation ve feedback metrics okuma.
- SES identity için SNS feedback topic bağlama.
- SNS topic ve subscription otomasyonu.

## SES SMTP Credential Ne İçin Kullanılır?

SES SMTP credential şu işlemler için kullanılır:

- SMTP bağlantı testi.
- Test mail gönderimi.
- Plesk smarthost yapılandırmasında SES relay kimlik doğrulaması.

IAM Access Key'i SMTP username olarak kullanmayın. Amazon SES SMTP credentials ayrı üretilir.

## Minimum IAM Policy

Aşağıdaki policy SES Manager'ın kullandığı temel işlemler için hazırlanmıştır.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "SesManagerIdentityManagement",
      "Effect": "Allow",
      "Action": [
        "ses:ListEmailIdentities",
        "ses:GetAccount",
        "ses:GetEmailIdentity",
        "ses:CreateEmailIdentity",
        "ses:PutEmailIdentityMailFromAttributes",
        "ses:GetIdentityNotificationAttributes",
        "ses:SetIdentityNotificationTopic",
        "ses:BatchGetMetricData"
      ],
      "Resource": "*"
    },
    {
      "Sid": "SesManagerSendMail",
      "Effect": "Allow",
      "Action": [
        "ses:SendEmail",
        "ses:SendRawEmail"
      ],
      "Resource": "*"
    },
    {
      "Sid": "SesManagerSnsAutomation",
      "Effect": "Allow",
      "Action": [
        "sns:CreateTopic",
        "sns:Subscribe",
        "sns:ListSubscriptionsByTopic",
        "sns:GetSubscriptionAttributes",
        "sns:SetSubscriptionAttributes"
      ],
      "Resource": "*"
    }
  ]
}
```

## IAM Kullanıcısı Nasıl Hazırlanır?

1. AWS Console > IAM bölümüne gidin.
2. SES Manager için ayrı bir IAM user veya access key oluşturun.
3. Yukarıdaki policy'yi oluşturup bu kullanıcıya bağlayın.
4. Access Key ID ve Secret Access Key değerlerini alın.
5. SES Manager > Setup veya Settings ekranına girin.
6. AWS Access Key ve AWS Secret Key alanlarını doldurun.
7. Doğru SES region seçin.
8. `Save & Test Connection` veya wizard içindeki test butonunu kullanın.

## SES SMTP Credential Nasıl Oluşturulur?

1. AWS Console > Amazon SES bölümüne gidin.
2. Doğru region seçili olduğundan emin olun.
3. `SMTP settings` bölümünü açın.
4. `Create SMTP credentials` seçeneğini kullanın.
5. Oluşan SMTP username ve password değerlerini kaydedin.
6. SES Manager > Setup veya Settings ekranına girin.
7. SMTP username/password alanlarını doldurun.
8. SMTP testini çalıştırın.

## SES Sandbox Nedir?

Yeni SES accountları çoğu durumda sandbox modunda başlar. Sandbox modunda:

- Sadece doğrulanmış alıcılara mail gönderebilirsiniz.
- Günlük gönderim limiti düşüktür.
- Test için uygundur, production gönderim için uygun değildir.

Production kullanım için AWS SES production access talebi açmanız gerekir.

## Production Access Talebinde Ne Yazılmalı?

AWS talep formunda genelde şu bilgiler beklenir:

- Gönderim amacı.
- Beklenen günlük/aylık hacim.
- Alıcıların nasıl izin verdiği.
- Bounce ve complaint takibinin nasıl yapılacağı.
- Liste temizliği ve unsubscribe süreçleri.
- SES Manager ile SNS bounce/complaint tracking kullanılacağı.

## Sık AWS Hataları

### SES API connection failed / 403

Muhtemel nedenler:

- `ses:GetAccount` izni yok.
- Yanlış AWS region seçildi.
- Access Key veya Secret Key hatalı.
- IAM policy kullanıcıya bağlı değil.
- AWS account veya IAM key devre dışı.

Çözüm:

1. Region kontrol edin.
2. IAM policy içinde `ses:GetAccount` olduğundan emin olun.
3. Access Key/Secret Key'i tekrar girin.
4. AWS CloudTrail veya IAM policy simulator ile kontrol edin.

### SMTP authentication failed

Muhtemel nedenler:

- IAM Access Key SMTP username olarak kullanıldı.
- SES SMTP password yanlış.
- SMTP credential farklı region için oluşturuldu.
- Port 587 dışarı kapalı.

Çözüm:

1. SES SMTP credentials'ı yeniden oluşturun.
2. SMTP endpoint region ile uyuşuyor mu kontrol edin.
3. Sunucudan `email-smtp.{region}.amazonaws.com:587` erişimini kontrol edin.

### Message rejected / identity not verified

Muhtemel nedenler:

- From domain SES'te doğrulanmadı.
- Sandbox modunda doğrulanmamış alıcıya gönderim deneniyor.
- DKIM/DNS kayıtları henüz yayılmadı.

Çözüm:

1. Domain identity durumunu kontrol edin.
2. DNS Health ekranında DKIM/SPF/DMARC durumunu yenileyin.
3. Sandbox ise alıcı adresini doğrulayın veya production access isteyin.

## Güvenlik Önerileri

- AWS root account kullanmayın.
- SES Manager için ayrı IAM user/access key kullanın.
- IAM policy'yi mümkün olduğunca sınırlı tutun.
- Access Key'i düzenli aralıklarla rotate edin.
- Secret değerleri destek talebine yazmayın.
