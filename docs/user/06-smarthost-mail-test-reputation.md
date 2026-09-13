# Smarthost, Mail Test ve Reputation

Bu doküman Plesk mail çıkışını SES SMTP üzerinden yönlendirme, test mail gönderimi ve reputation ekranlarını açıklar.

## Smarthost Ne Yapar?

Smarthost, Plesk sunucusundan çıkan mailleri Amazon SES SMTP relay üzerinden göndermeyi sağlar.

Varsayılan SES SMTP endpoint formatı:

```text
email-smtp.{region}.amazonaws.com:587 STARTTLS
```

Örnek:

```text
email-smtp.eu-central-1.amazonaws.com:587
```

## Smarthost Ne Zaman Kullanılmalı?

Kullanım senaryoları:

- Plesk sunucusunun IP reputation riski varsa.
- Mail çıkışını SES üzerinden merkezi yönetmek istiyorsanız.
- SPF/DKIM/DMARC ve bounce yönetimini SES tarafına almak istiyorsanız.
- Direct Postfix çıkışı yerine authenticated relay kullanmak istiyorsanız.

## Smarthost Öncesi Kontrol Listesi

- SES SMTP credentials hazır mı?
- SES account production modda mı?
- Gönderici domain SES identity olarak doğrulandı mı?
- SPF/DKIM/DMARC kayıtları doğru mu?
- AWS SES sending enabled mı?
- Port 587 dışarı açık mı?

## Smarthost Nasıl Uygulanır?

1. SES Manager > Smarthost ekranına gidin.
2. Preview bilgilerini kontrol edin.
3. Host, port, username ve encryption değerlerini inceleyin.
4. `Apply` butonuna tıklayın.
5. Extension mevcut Plesk relay config snapshot'ını alır.
6. SES SMTP relay config uygulanır.
7. Bağlantı testi yapılır.

Test başarısız olursa rollback önerilir veya otomatik rollback davranışı devreye girebilir.

## Rollback Ne İşe Yarar?

Rollback, smarthost uygulanmadan önceki relay ayarına dönmek için kullanılır.

Not:

- Bazı Plesk sürümleri mevcut relay şifresini plaintext döndürmeyebilir.
- Böyle durumlarda önceki relay tam şifreyle geri kurulamayabilir.
- Smarthost değişikliği öncesi mail server ayarlarınızı ayrıca not etmek iyi pratiktir.

## Mail Test Ekranı

Mail Test ekranı SES SMTP üzerinden test mail göndermek için kullanılır.

Alanlar:

- From email.
- To email.
- Subject.
- Body.

Test sonucu:

- Success / failed.
- Provider response.
- Provider message ID, SES döndürürse.
- Tarih.

## Mail Test Başarısızsa Ne Kontrol Edilmeli?

- SMTP username/password doğru mu?
- Region doğru mu?
- SES SMTP credential aynı region için mi?
- From domain doğrulanmış mı?
- Sandbox modunda doğrulanmamış alıcıya mı gönderiyorsunuz?
- `ses:SendRawEmail` izni var mı?
- Sunucu port 587 üzerinden çıkabiliyor mu?

## SES Reputation Ekranı

Reputation ekranı SES account ve gönderim durumunu gösterir:

- Production veya sandbox.
- Sending enabled.
- Günlük kullanım.
- Günlük limit.
- Max send rate.
- Bounce rate.
- Complaint rate.
- Account health.

## IP Reputation Center

IP Reputation Center Plesk sunucusunun doğrudan mail gönderim sağlığına bakar.

Kontroller:

- Reverse DNS.
- Forward-confirmed PTR.
- SMTP banner.
- HELO/EHLO identity.
- ASN/provider bilgisi.
- DNSBL/RBL listelemeleri.

Not: SES smarthost kullanıyorsanız actual mail çıkışı Amazon SES IP'lerinden yapılır. IP Reputation ekranı Plesk sunucu IP'nizin doğrudan çıkış sağlığını gösterir, SES shared IP reputation bilgisini birebir temsil etmez.

## RBL Monitoring

RBL monitoring public build'de kullanılabilir.

Davranış:

- Scheduler günlük kontrol yapar.
- Kritik DNSBL listelenmesi varsa Plesk notification üretir.
- Aynı alert için 24 saat cooldown uygulanır.
- Recovery eventleri kaydedilir.

## Reputation Verisi Neden Boş?

Muhtemel nedenler:

- AWS credentials girilmemiş.
- `ses:GetAccount` izni yok.
- `ses:BatchGetMetricData` izni yok.
- Region yanlış.
- SES account yeni ve yeterli metrik yok.
- Engagement tracking/VDM etkin değilse open/click metrikleri görünmeyebilir.

Çözüm:

1. Settings > AWS bilgilerini kontrol edin.
2. IAM policy izinlerini kontrol edin.
3. Doğru region seçin.
4. Reputation ekranında refresh yapın.
