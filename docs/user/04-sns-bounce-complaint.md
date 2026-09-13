# Amazon SNS, Bounce ve Complaint Takibi

SES Manager, Amazon SES bounce, complaint ve delivery eventlerini Amazon SNS üzerinden alabilir. Bu özellik sayesinde hatalı alıcılar, complaint oranları ve delivery eventleri Plesk panelinden izlenebilir.

## SNS Neden Gerekli?

Amazon SES gönderim sonrası olayları SNS topiclerine gönderebilir:

- Bounce
- Complaint
- Delivery

SES Manager bu eventleri public webhook üzerinden alır, AWS SNS imzasını doğrular ve veritabanına kaydeder.

## Webhook URL Nerede?

SES Manager > Bounce/Complaint ekranında webhook URL gösterilir.

Genel format:

```text
https://panel.example.com/modules/ses-manager/public/sns-webhook.php
```

Plesk path yapısına göre URL farklı görünebilir. En doğru URL panelde gösterilen URL'dir.

## SNS Kurulum Adımları

1. AWS Console > SNS bölümüne gidin.
2. SES ile aynı regionda Standard topic oluşturun.
3. Topic ARN değerini kopyalayın.
4. SES Manager > Settings veya Setup Wizard içinde `Allowed SNS Topic ARN` alanına girin.
5. SNS topic içinde HTTPS subscription oluşturun.
6. Endpoint olarak SES Manager webhook URL kullanın.
7. Raw Message Delivery kapalı kalmalıdır.
8. AWS, webhook'a SubscriptionConfirmation gönderir.
9. SES Manager imzayı doğrulayıp confirmation isteğini otomatik onaylamaya çalışır.
10. Bounce/Complaint ekranında confirmation kayıtlarını kontrol edin.

## Otomatik SNS Yapılandırma

SES Manager SNS işlemlerini otomatikleştirebilir:

- Topic ARN boşsa topic oluşturmayı deneyebilir.
- Webhook subscription request gönderebilir.
- Raw Message Delivery ayarını kontrol edebilir.
- Raw açıksa kapatmayı deneyebilir.
- SES identity için bounce/complaint feedback topic bağlantısını yapabilir.

Bu işlemler için IAM policy içinde SNS ve SES feedback izinleri olmalıdır.

## Raw Message Delivery Neden Kapalı Olmalı?

AWS SNS normal HTTPS subscription'da imzalı SNS envelope gönderir. SES Manager bu envelope içindeki imzayı doğrular.

Raw Message Delivery açık olursa SNS imzalı envelope yerine ham mesaj gönderir. Bu durumda SES Manager imzayı doğrulayamaz ve payload'u reddeder.

Hata örneği:

```text
SNS Raw message delivery is enabled.
```

Çözüm:

1. Bounce/Complaint ekranında `Raw Message Kontrol` butonunu kullanın.
2. AWS SNS subscription attribute içinde `RawMessageDelivery=false` olduğundan emin olun.

## Allowed SNS Topic ARN Ne İşe Yarar?

Bu alan girilirse webhook yalnızca belirtilen topic ARN'den gelen imzalı SNS mesajlarını kabul eder.

Avantajı:

- Başka SNS topiclerinden gelen istekler reddedilir.
- Webhook public olsa bile kaynak kısıtlaması sağlanır.
- Yanlış AWS account/region mesajları fark edilir.

## SES Identity Feedback Topic Nasıl Bağlanır?

Amazon SES içinde her domain identity için feedback notification topic seçilebilir.

SES Manager otomasyon kullanmıyorsanız manuel akış:

1. AWS Console > Amazon SES > Verified identities.
2. Domain identity seçin.
3. Notifications veya Feedback notifications bölümünü açın.
4. Bounce feedback için SNS topic seçin.
5. Complaint feedback için aynı SNS topic'i seçin.
6. Delivery eventlerini izlemek istiyorsanız delivery feedback'i de bağlayın.

## Bounce/Complaint Center Ne Gösterir?

Bu ekran şunları gösterir:

- Event tipi.
- Bounce tipi.
- Recipient.
- Message ID.
- Alınma zamanı.
- İlgili domain eşleşmesi.
- SNS confirmation kayıtları.
- Son webhook denemeleri.
- Feedback metrics, AWS yetkileri ve veri kaynağı uygunsa.

## Eventler Neden Görünmüyor?

Muhtemel nedenler:

- SNS subscription confirmed değil.
- Raw Message Delivery açık.
- Allowed Topic ARN yanlış.
- SES identity bounce/complaint topic'e bağlı değil.
- AWS region uyuşmuyor.
- Webhook public HTTPS üzerinden erişilemiyor.

Kontrol sırası:

1. Bounce/Complaint ekranında webhook URL'yi kontrol edin.
2. SNS subscription status `Confirmed` mi kontrol edin.
3. Raw Message Delivery kapalı mı kontrol edin.
4. Allowed Topic ARN doğru mu kontrol edin.
5. SES identity feedback notification topic doğru mu kontrol edin.
6. Webhook Attempts tablosunda hata var mı bakın.

## Güvenlik

- Webhook auth bypass eder, ancak sadece imzalı SNS payload kabul eder.
- SNS certificate URL AWS allowlist ile kontrol edilir.
- Payload boyutu limitlenir.
- Eventler idempotency kontrolüyle kaydedilir.
- Topic ARN allowlist girildiyse başka topic reddedilir.
