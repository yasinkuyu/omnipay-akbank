# Omnipay: Akbank

**Akbank sanal POS için [Omnipay](https://github.com/thephpleague/omnipay) sürücüsü**

[![Latest Stable Version](https://poser.pugx.org/yasinkuyu/omnipay-akbank/v/stable)](https://packagist.org/packages/yasinkuyu/omnipay-akbank)
[![Total Downloads](https://poser.pugx.org/yasinkuyu/omnipay-akbank/downloads)](https://packagist.org/packages/yasinkuyu/omnipay-akbank)
[![License](https://poser.pugx.org/yasinkuyu/omnipay-akbank/license)](https://packagist.org/packages/yasinkuyu/omnipay-akbank)

**Türkçe** | [English](README_EN.md)

## Bu paket ne yapar?

Mağazanızda Akbank sanal POS'unuzla kartla ödeme almanızı sağlar. Müşteri **Akbank'ın ortak ödeme sayfasına**
gider, kartını orada girer, 3D Secure doğrulamasını yapar ve sitenize geri döner. Kart bilgisi sizin sunucunuzdan
hiç geçmez.

```
Siteniz ──(imzalı form)──▶ Akbank ortak ödeme sayfası ──(3D Secure)──▶ Siteniz (dönüş adresi)
```

Bu paket Akbank'ın yeni sanal POS altyapısı içindir (32 karakterlik "Merchant Safe ID" ve "Terminal Safe ID").
Eski NestPay / EST altyapısındaki Akbank üye iş yerleri için [omnipay-nestpay](https://github.com/yasinkuyu/omnipay-nestpay) kullanılır.

**Özellikler**

| | |
|---|---|
| Ödeme | Ortak ödeme sayfası (3D Pay Hosting) |
| Taksit | 2-12 (bankanızla anlaştığınız kadar) |
| İade / iptal | `refund()` (tam ya da kısmi), `void()` (gün sonundan önce) |
| Sorgu | `fetchTransaction()` |
| Güvenlik | HMAC-SHA512 imza; dönüşte imza, üye iş yeri / terminal ve sipariş no denetimi |

## Kurulum

```bash
composer require yasinkuyu/omnipay-akbank php-http/guzzle7-adapter
```

PHP 8.1 ya da üstü gerekir.

## Bankadan almanız gereken bilgiler

| Ayar | Nedir? | Nereden bulunur? |
|---|---|---|
| `merchantSafeId` | Üye iş yeri güvenli numarası (32 karakter) | Akbank sanal POS yönetim ekranı / bankanın gönderdiği bilgiler |
| `terminalSafeId` | Terminal güvenli numarası (32 karakter) | Aynı yer |
| `secretKey` | Gizli anahtar | Sanal POS yönetim ekranında oluşturulur |
| `subMerchantId` | Alt üye iş yeri no (isteğe bağlı) | Yalnız pazaryeri / ödeme kuruluşu hesaplarında |

## Hızlı başlangıç

### 1. Gateway'i hazırlayın

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('Akbank');
$gateway->initialize([
    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
    'secretKey'      => '3230323330393034313735303032363031353172675f357637355f3273387373745f7233725f73323333383737335f323272383774767276327672323531355f',
    'testMode'       => true,   // canlıya geçerken false
]);
```

### 2. Ödemeyi başlatın (odeme.php)

```php
$response = $gateway->purchase([
    'amount'        => '149.90',
    'currency'      => 'TRY',
    'transactionId' => 'SIP-10045',                          // sizin sipariş numaranız, her denemede benzersiz
    'returnUrl'     => 'https://magaza.com/odeme-donus.php', // başarılı ve başarısız dönüş buraya
    'installment'   => 3,                                    // tek çekim için 1 ya da hiç yazmayın
    'card'          => ['email' => 'ali@ornek.com'],         // kart numarası YOK
])->send();

$response->redirect(); // tarayıcıyı Akbank'ın ödeme sayfasına götüren formu basar ve gönderir
```

### 3. Dönüşü doğrulayın (odeme-donus.php)

```php
$result = $gateway->completePurchase([
    'transactionId' => 'SIP-10045', // KENDİ kaydınızdaki sipariş no
])->send();

if (!$result->isVerified()) {
    // İmza tutmadı ya da sipariş no farklı: bu dönüşe güvenmeyin.
} elseif ($result->isSuccessful()) {
    // Ödeme alındı.
    $result->getTransactionReference(); // bankanın referans no (rrn)
    $result->getAuthCode();
    $result->getInstallment();
} else {
    echo $result->getMessage();         // ret nedeni ya da 3D Secure hatası
}
```

> **Doğrulama nasıl çalışır?** Banka dönüşü imzalar: `hashParams` alanında `+` ile sayılan değerler sırayla
> birleştirilir ve gizli anahtarla HMAC-SHA512 alınır. Paket sonuç kodu ve sipariş numarasının imzanın içinde olmasını
> şart koşar. Tutar imzalı isteğinizde sipariş numarasına bağlanmıştır.

## İade, iptal, sorgu

```php
$gateway->void(['transactionId' => 'SIP-10045'])->send();                         // gün sonundan önce: iptal
$gateway->refund(['transactionId' => 'SIP-10045', 'amount' => '50.00'])->send(); // tam ya da kısmi iade
$gateway->fetchTransaction(['transactionId' => 'SIP-10045'])->send()->getTransactions();
```

İstek gövdesi JSON'dur ve `auth-hash` başlığında HMAC-SHA512 ile imzalanır. **`isReadable()` false dönerse** yanıt
okunamamıştır: işlem gerçekleşmiş olabilir. Otomatik olarak yeniden denemeyin; sanal POS ekranından kontrol edin.

## Test ortamı

`testMode => true` iken istekler Akbank'ın test ortamına gider, para çekilmez. Yukarıdaki örnekteki üye iş yeri,
terminal ve gizli anahtar Akbank'ın herkese açık test bilgileridir. Test kartı: `4355 0930 0031 5232`, son kullanma 01/28,
CVV `264`, 3D şifresi `123456`.

Test ortamının ödeme sayfası açılır ve dönüşler imzalıdır; 3D Secure adımı zaman zaman "BKM 3DS Server Doğrulama
Yapılamadı" ile sonuçlanabilir. İade / iptal / sorgu API'si üye iş yerinin IP adresine açılır.

## Sık karşılaşılan durumlar

| Belirti | Neden |
|---|---|
| Ödeme sayfasında imza hatası | `secretKey`, `merchantSafeId` ya da `terminalSafeId` yanlış |
| `isVerified()` hep false | Farklı `secretKey`, ya da dönüş alanları değiştirilmiş |
| API'den "405" / bağlantı hatası | Sunucunuzun IP adresi Akbank'a tanımlı değil |
| Dönüşte oturum düşüyor | Banka dönüşü siteler arası POST'tur: dönüş adresinde oturum çerezine güvenmeyin |

## Testler

```bash
composer install
vendor/bin/phpunit
```

## Diğer Omnipay paketleri

- **[Omnipay: NestPay (EST / Payten)](https://github.com/yasinkuyu/omnipay-nestpay)** — İş Bankası, Akbank, Halkbank, Ziraat, TEB, QNB, ING, Şekerbank, Anadolubank sanal POS'u; 3D Pay Hosting
- **[Omnipay: GVP (Garanti BBVA)](https://github.com/yasinkuyu/omnipay-gvp)** — Garanti BBVA sanal POS'u; Ortak Ödeme Sayfası
- **[Omnipay: Paratika](https://github.com/yasinkuyu/omnipay-paratika)** — Paratika, VakıfPayS ve ZiraatPay ortak ödeme sayfası
- **[Omnipay: Paycell SDK](https://github.com/yasinkuyu/omnipay-paycell-sdk)** — Turkcell Paycell ödeme sayfası (Web SDK)
- **[Omnipay: Paycell API](https://github.com/yasinkuyu/omnipay-paycell)** — Turkcell Paycell ödeme API'si
- **[Omnipay: iyzico](https://github.com/yasinkuyu/omnipay-iyzico)** — iyzico ödeme formu
- **[Omnipay: PayTR](https://github.com/yasinkuyu/omnipay-paytr)** — PayTR sanal POS
- **[Omnipay: Posnet](https://github.com/yasinkuyu/omnipay-posnet)** — Yapı Kredi Posnet sanal POS'u

## Lisans

MIT — [Yasin Kuyu](https://github.com/yasinkuyu)
