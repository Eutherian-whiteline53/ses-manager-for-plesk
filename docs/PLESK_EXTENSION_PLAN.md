# Plesk Extension Paket Planı

Bu doküman `ses-manager` extension paketinin klasör, dosya ve Catalog uyumluluk planını tanımlar.

## Paket Kökü

```text
ses-manager/
├── meta.xml
├── DESCRIPTION.md
├── CHANGES.md
├── EULA.md
├── PRIVACY_POLICY.md
├── htdocs/
│   ├── index.php
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   └── app.js
│   ├── legal/
│   │   ├── EULA.md
│   │   └── PRIVACY_POLICY.md
│   └── public/
│       └── sns-webhook.php
├── plib/
│   ├── controllers/
│   ├── library/
│   ├── scripts/
│   └── views/
├── sbin/
│   ├── health-check.php
│   ├── job-runner.php
│   └── ip-reputation-check.php
└── var/
    ├── cache/
    ├── logs/
    └── tmp/
```

## meta.xml

Varsayılan metadata:

```xml
<?xml version="1.0"?>
<module>
  <id>ses-manager</id>
  <name>SES Manager for Plesk</name>
  <description>Manage Amazon SES verification, DNS health, smarthost setup, and deliverability from Plesk.</description>
  <category>mail</category>
  <version>1.2.47</version>
  <release>1</release>
  <vendor>Opphisse Agency</vendor>
  <url>https://opphisse.agency/products/ses-manager</url>
  <support_url>https://opphisse.agency/support</support_url>
  <help_url>https://opphisse.agency/products/ses-manager/docs</help_url>
  <privacy_policy_url>https://opphisse.agency/products/ses-manager/privacy</privacy_policy_url>
  <plesk_min_version>18.0.0</plesk_min_version>
</module>
```

Dikkat edilecekler:

- `id` sadece küçük harf ve tire içermeli.
- `name` kısa ve İngilizce olmalı.
- Catalog öncesi URL, support, help ve privacy sayfaları public erişilebilir olmalı.
- `release` her paket build'inde artırılmalı.

## Entry Point Dosyaları

- `htdocs/index.php`: Plesk MVC bootstrap. Tüm panel ekranları buradan controller'a yönlenir.
- `htdocs/public/sns-webhook.php`: Auth gerektirmeyen tek public endpoint. Sadece AWS SNS imzası doğrulanan payload işlenir.
- `library/EventListener.php`: Plesk `domain_create` ve `site_create` eventlerini dinler; auto SES açıksa job kuyruğuna alır.
- `sbin/health-check.php`: Scheduler ile çalışan DNS/SES sağlık kontrolü ve kritik domain notification.
- `sbin/job-runner.php`: `auto_domain_verify` job kuyruğunu işler.
- `sbin/ip-reputation-check.php`: Günlük IP reputation, DNSBL ve RBL alert kontrolü.

## Plesk Kurulum Scriptleri

- `plib/scripts/pre-install.php`: Plesk minimum sürüm ve PHP extension kontrolleri.
- `plib/scripts/post-install.php`: DB migration, varsayılan ayarlar, scheduler kayıtları.
- `plib/scripts/pre-uninstall.php`: Scheduler temizliği, geçici dosya temizliği. Kullanıcı verisi varsayılan olarak silinmez.
- `plib/scripts/db/install.php`: İlk tablo kurulumları.
- `plib/scripts/db/install.php`: Mevcut kurulumlarda kolon eklemeleri de idempotent yürütür.

## Catalog Kontrol Listesi

- `DESCRIPTION.md`: Özellikler, gereksinimler, veri işleme açıklaması.
- `CHANGES.md`: SemVer changelog.
- `meta.xml`: URL, support, help ve privacy alanları public ve doğru.
- `EULA.md`: Catalog ve site kullanımı için güncel son kullanıcı lisans sözleşmesi.
- `PRIVACY_POLICY.md`: Catalog ve site kullanımı için güncel gizlilik politikası.
- `htdocs/legal/EULA.md` ve `htdocs/legal/PRIVACY_POLICY.md`: Kurulu Plesk sunucusunda saklanan local kopyalar.
- EULA ilk kullanım öncesi gösterilir ve kabul kaydı her Plesk sunucusunda local saklanır.
- ZIP kurulumu Plesk UI ve CLI ile test edildi.
- Public webhook imza doğrulaması test edildi.
- Credential'lar plaintext loglanmıyor.
- SNS webhook Raw Message Delivery kapalıyken imzalı envelope kabul ediyor; raw payload reddediliyor.
- IAM policy dokümanı `docs/iam-policy.json` güncel.
- Upload edilen pakette geliştirme cache, test dump, debug script ve local secret yok.

## Paketleme Akışı

Release staging üzerinden paketleme:

```bash
./scripts/build-release.sh
```

Geliştirme ortamında doğrudan Plesk CLI:

```bash
plesk bin extension -c ses-manager
plesk bin extension -r ses-manager
plesk bin extension -p ses-manager
plesk bin extension -i ses-manager.zip
```

Release build script'i şu dosyaları pakete dahil etmez:

- `tests/`
- `scripts/debug-*.php`
- `sbin/debug-*.php`
- `var/cache/*`, `var/logs/*`, `var/tmp/*`

Geliştirme sırasında `-r` komutu birden fazla çalışabilir; install scriptleri idempotent yazılmalıdır.
