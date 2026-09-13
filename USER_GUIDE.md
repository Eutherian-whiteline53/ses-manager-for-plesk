# SES Manager for Plesk Kullanıcı Dokümantasyonu

Bu dosya SES Manager for Plesk için siteye taşınabilir kullanıcı dokümantasyonunun indeksidir. Dokümanlar Plesk Extensions Catalog üzerinden kurulmuş bir extension varsayımıyla yazılmıştır.

## Doküman Haritası

1. [Kurulum ve Update](docs/user/01-kurulum-ve-update.md)
2. [Hızlı Başlangıç ve Kurulum Sihirbazı](docs/user/02-hizli-baslangic.md)
3. [AWS SES, IAM ve SES SMTP](docs/user/03-aws-ses-iam-smtp.md)
4. [Amazon SNS, Bounce ve Complaint Takibi](docs/user/04-sns-bounce-complaint.md)
5. [DNS, Cloudflare ve Auto Fix](docs/user/05-dns-cloudflare-auto-fix.md)
6. [Smarthost, Mail Test ve Reputation](docs/user/06-smarthost-mail-test-reputation.md)
7. [Gelişmiş Ayarlar, Veri Saklama ve Destek Çıktısı](docs/user/08-gelismis-ayarlar-destek.md)
8. [Soru Cevap ve Hata Giderme](docs/user/09-soru-cevap-hata-giderme.md)

## Kısa Özet

SES Manager for Plesk, Plesk yöneticilerinin Amazon SES gönderim altyapısını panel içinden kurmasına, domain doğrulamasını yönetmesine, DNS sağlığını izlemesine, Cloudflare veya Plesk DNS üzerinde gerekli kayıtları uygulamasına, bounce/complaint eventlerini Amazon SNS ile takip etmesine ve Plesk mail çıkışını SES SMTP smarthost üzerinden yönlendirmesine yardımcı olur.

Extension aşağıdaki dış sistemlerle çalışabilir:

- Plesk Obsidian
- Amazon SES API v2
- Amazon SES SMTP
- Amazon SNS
- Plesk DNS
- Cloudflare DNS API
- Plesk mail server / smarthost
- DNS resolver ve DNSBL servisleri

## İlk Kurulum Sırası

1. Extension'ı Plesk Extensions sayfasından kurun.
2. SES Manager menüsünden Setup Wizard'ı açın.
3. AWS region seçin.
4. IAM Access Key ve Secret Key girin.
5. SES SMTP kullanıcı adı ve şifresini girin.
6. SES sandbox durumunu kontrol edin.
7. MAIL FROM, SPF ve DMARC varsayılanlarını seçin.
8. Cloudflare kullanıyorsanız API token ekleyin.
9. Bounce/Complaint takibi istiyorsanız SNS Topic ARN ve webhook aboneliğini yapılandırın.
10. Domains ekranından domain identity oluşturun ve DNS kayıtlarını uygulayın.

## Destek Talebi Açmadan Önce

Destek talebinde şu bilgileri ekleyin:

- Hangi ekranda hata alındı?
- Hangi domain veya region ile işlem yapıldı?
- Hata mesajının tam metni nedir?
- Gelişmiş Ayarlar > Destek Çıktısı bölümünden indirilen JSON dosyası.
- AWS veya Cloudflare tarafında ilgili izinler verildi mi?

Destek çıktısı secret değerleri maskeleyerek üretir. Yine de göndermeden önce dosyayı gözden geçirin.
