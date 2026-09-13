# Hızlı Başlangıç ve Kurulum Sihirbazı

Bu doküman SES Manager'ı ilk kez yapılandıran Plesk yöneticileri için adım adım akışı açıklar.

## En Kısa Kurulum Akışı

1. SES Manager > Setup sayfasını açın.
2. `Open Setup Wizard` butonuna tıklayın.
3. AWS SES region seçin.
4. AWS Access Key ve Secret Key girin.
5. SES API bağlantısını test edin.
6. SES sandbox durumunu kontrol edin.
7. SES SMTP username/password girin.
8. SMTP bağlantısını test edin.
9. MAIL FROM subdomain seçin. Varsayılan: `bounce`.
10. DMARC policy seçin. İlk kurulum için `none` güvenli başlangıçtır.
11. Yeni domain otomasyonunu isteğe bağlı açın.
12. Cloudflare kullanıyorsanız token ve sync mode girin.
13. SNS kullanıyorsanız Topic ARN girin veya otomatik kurulum kullanın.
14. Summary adımında kurulumu tamamlayın.

## Setup Wizard Hangi Parçaları Kurar?

Setup Wizard aşağıdaki bileşenleri sırayla yapılandırır:

- AWS SES API erişimi.
- SES SMTP bilgileri.
- SES sandbox/production durumu.
- Custom MAIL FROM varsayılanı.
- SPF ve DMARC varsayılanları.
- Yeni domain otomasyonu.
- Cloudflare DNS otomasyonu.
- SNS feedback eventleri.

Her adım yalnızca kendi ayarını kaydeder. Bir adımda hata alırsanız önceki adımlar korunur.

## Hangi AWS Region Seçilmeli?

SES identity, SMTP endpoint, SNS topic ve feedback metrics aynı AWS region içinde olmalıdır.

Örnek:

- SES identity `eu-central-1` içinde oluşturulacaksa SMTP host `email-smtp.eu-central-1.amazonaws.com` olur.
- SNS topic de `eu-central-1` içinde olmalıdır.
- IAM permission aynı AWS account için geçerli olmalıdır.

Region yanlış seçilirse sık görülen sonuçlar:

- SES identity bulunamaz.
- SMTP kullanıcı adı doğru olsa bile bağlantı başarısız olur.
- SNS topic ARN region uyuşmazlığı verir.
- SES account sandbox/production durumu beklediğinizden farklı görünür.

## Kurulumdan Sonra İlk Domain Nasıl Doğrulanır?

1. `Domains` ekranına gidin.
2. Listeden domain seçin.
3. `Create SES Identity` butonuna tıklayın.
4. SES Manager AWS SES tarafında domain identity oluşturur.
5. DKIM, MAIL FROM, SPF ve DMARC kayıtlarını üretir.
6. DNS kayıtlarını inceleyin.
7. Plesk DNS kullanıyorsanız `Apply DNS Changes` ile uygulayın.
8. Cloudflare kullanıyorsanız Cloudflare ekranından preview/apply yapın.
9. DNS yayılımından sonra `DNS Health` ekranından refresh yapın.

## Yeni Domain Otomasyonu Ne Yapar?

Bu özellik açılırsa Plesk'te yeni domain veya subscription oluştuğunda SES Manager arka planda job kuyruğuna iş ekler.

Otomasyon şunları dener:

- SES identity oluşturma veya yenileme.
- Custom MAIL FROM ayarı.
- DKIM CNAME kayıtlarını planlama.
- SPF merge.
- DMARC record planlama.
- MAIL FROM MX/TXT kayıtları.
- Plesk DNS veya Cloudflare DNS uygulaması.
- SNS topic bağlantısı, SNS ayarı varsa.

Otomasyon domain oluşturma işlemini bloklamaz. Hata olursa job geçmişinde veya ilgili domain ekranında görülür.

## Public Build ile Neler Yapılabilir?

Public build lisans anahtarı, paket ayrımı veya domain limiti uygulamaz. Gerekli AWS/Cloudflare/SNS yetkileri sağlandığında aşağıdaki iş akışları kullanılabilir:

- AWS SES bağlantısı test edilebilir.
- SES SMTP bağlantısı test edilebilir.
- SES account durumu görüntülenebilir.
- Plesk domainleri için SES identity oluşturulabilir.
- Plesk DNS veya Cloudflare DNS üzerinden gerekli kayıtlar uygulanabilir.
- DNS Health ve deliverability score kullanılabilir.
- SES reputation görüntülenebilir.
- Cloudflare DNS otomasyonu kullanılabilir.
- DNS Auto Fix kullanılabilir.
- SNS automation ve Bounce/Complaint center kullanılabilir.
- Bulk verify, CSV export ve RBL monitoring kullanılabilir.

## Kurulumu Yeniden Başlatabilir miyim?

Evet. Settings veya Setup ekranında `Start Setup Again` seçeneği kullanılabilir. Bu işlem mevcut secret değerleri otomatik silmez. Secret alanlarını boş bırakırsanız mevcut değerler korunur.

## Secret Alanlar Neden Geri Gösterilmiyor?

AWS Secret Key, SES SMTP password ve Cloudflare token gibi değerler güvenlik nedeniyle formda geri gösterilmez. Panelde yalnızca `Configured` veya `Missing` durumu gösterilir.
