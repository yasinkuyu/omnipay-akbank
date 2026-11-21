# Omnipay: Akbank

**[Omnipay](https://github.com/thephpleague/omnipay) driver for the Akbank virtual POS**

[![Latest Stable Version](https://poser.pugx.org/yasinkuyu/omnipay-akbank/v/stable)](https://packagist.org/packages/yasinkuyu/omnipay-akbank)
[![Total Downloads](https://poser.pugx.org/yasinkuyu/omnipay-akbank/downloads)](https://packagist.org/packages/yasinkuyu/omnipay-akbank)
[![License](https://poser.pugx.org/yasinkuyu/omnipay-akbank/license)](https://packagist.org/packages/yasinkuyu/omnipay-akbank)

[Türkçe](README.md) | **English**

## What does this package do?

It lets your shop take card payments through your Akbank virtual POS. The customer goes to **Akbank's hosted
payment page**, enters the card there, completes 3D Secure and comes back to your site. Card data never touches your
server.

```
Your site ──(signed form)──▶ Akbank hosted payment page ──(3D Secure)──▶ Your site (return URL)
```

This package is for Akbank's new virtual POS platform (32-character "Merchant Safe ID" and "Terminal Safe ID").
Akbank merchants on the old NestPay / EST platform should use [omnipay-nestpay](https://github.com/yasinkuyu/omnipay-nestpay).

**Features**

| | |
|---|---|
| Payment | Hosted payment page (3D Pay Hosting) |
| Installments | 2-12 (whatever your bank agreement allows) |
| Refund / void | `refund()` (full or partial), `void()` (before end-of-day) |
| Status | `fetchTransaction()` |
| Security | HMAC-SHA512 signing; the return is checked for signature, merchant / terminal and order number |

## Installation

```bash
composer require yasinkuyu/omnipay-akbank php-http/guzzle7-adapter
```

Requires PHP 8.1 or later.

## What you need from your bank

| Setting | What is it? | Where to find it |
|---|---|---|
| `merchantSafeId` | Merchant safe ID (32 characters) | Akbank virtual POS admin / details sent by the bank |
| `terminalSafeId` | Terminal safe ID (32 characters) | Same |
| `secretKey` | Secret key | Generated in the virtual POS admin |
| `subMerchantId` | Sub-merchant ID (optional) | Marketplace / payment institution accounts only |

## Quick start

### 1. Set up the gateway

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('Akbank');
$gateway->initialize([
    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
    'secretKey'      => '3230323330393034313735303032363031353172675f357637355f3273387373745f7233725f73323333383737335f323272383774767276327672323531355f',
    'testMode'       => true,   // false in production
]);
```

### 2. Start the payment (pay.php)

```php
$response = $gateway->purchase([
    'amount'        => '149.90',
    'currency'      => 'TRY',
    'transactionId' => 'ORD-10045',                       // your order number, unique per attempt
    'returnUrl'     => 'https://shop.com/pay-return.php',  // both success and failure come back here
    'installment'   => 3,                                 // 1 or omit for a single payment
    'card'          => ['email' => 'ali@example.com'],    // NO card number
])->send();

$response->redirect(); // prints a form that takes the browser to Akbank's payment page
```

### 3. Verify the return (pay-return.php)

```php
$result = $gateway->completePurchase([
    'transactionId' => 'ORD-10045', // the order number from YOUR records
])->send();

if (!$result->isVerified()) {
    // Bad signature or a different order number: don't trust this return.
} elseif ($result->isSuccessful()) {
    // Payment received.
    $result->getTransactionReference(); // bank reference (rrn)
    $result->getAuthCode();
    $result->getInstallment();
} else {
    echo $result->getMessage();         // decline reason or 3D Secure error
}
```

> **How does verification work?** The bank signs the return: the values listed in `hashParams` (separated by `+`) are
> concatenated in order and HMAC-SHA512 is taken with the secret key. The package requires the result code and order
> number to be inside the signature. The amount is bound to the order number in your signed request.

## Refund, void, status

```php
$gateway->void(['transactionId' => 'ORD-10045'])->send();                         // before end-of-day: void
$gateway->refund(['transactionId' => 'ORD-10045', 'amount' => '50.00'])->send(); // full or partial refund
$gateway->fetchTransaction(['transactionId' => 'ORD-10045'])->send()->getTransactions();
```

The request body is JSON, signed with HMAC-SHA512 in the `auth-hash` header. **If `isReadable()` returns false**, the
response could not be read: the operation may have gone through. Do not retry automatically; check the virtual POS panel.

## Test environment

With `testMode => true` requests go to Akbank's test environment; no money is charged. The merchant, terminal and
secret key in the example above are Akbank's public test credentials. Test card: `4355 0930 0031 5232`, expiry 01/28,
CVV `264`, 3D password `123456`.

The test payment page opens and the returns are signed; the 3D Secure step may sometimes end with "BKM 3DS Server
Doğrulama Yapılamadı" (3DS server could not authenticate). The refund / void / status API is opened to the merchant's IP.

## Troubleshooting

| Symptom | Cause |
|---|---|
| Signature error on the payment page | Wrong `secretKey`, `merchantSafeId` or `terminalSafeId` |
| `isVerified()` always false | Different `secretKey`, or the return fields were changed |
| "405" / connection error from the API | Your server's IP is not registered with Akbank |
| Session is lost on return | The bank's return is a cross-site POST: don't rely on the session cookie on the return URL |

## Tests

```bash
composer install
vendor/bin/phpunit
```

## Other Omnipay packages

- **[Omnipay: NestPay (EST / Payten)](https://github.com/yasinkuyu/omnipay-nestpay)** — İş Bankası, Akbank, Halkbank, Ziraat, TEB, QNB, ING, Şekerbank, Anadolubank virtual POS; 3D Pay Hosting
- **[Omnipay: GVP (Garanti BBVA)](https://github.com/yasinkuyu/omnipay-gvp)** — Garanti BBVA virtual POS; hosted payment page
- **[Omnipay: QNB](https://github.com/yasinkuyu/omnipay-qnb)** — QNB virtual POS (PayFor); hosted payment page
- **[Omnipay: Paratika](https://github.com/yasinkuyu/omnipay-paratika)** — Paratika, VakıfPayS and ZiraatPay hosted payment page
- **[Omnipay: Paycell SDK](https://github.com/yasinkuyu/omnipay-paycell-sdk)** — Turkcell Paycell payment page (Web SDK)
- **[Omnipay: Paycell API](https://github.com/yasinkuyu/omnipay-paycell)** — Turkcell Paycell payment API
- **[Omnipay: iyzico](https://github.com/yasinkuyu/omnipay-iyzico)** — iyzico checkout form
- **[Omnipay: PayTR](https://github.com/yasinkuyu/omnipay-paytr)** — PayTR virtual POS
- **[Omnipay: Posnet](https://github.com/yasinkuyu/omnipay-posnet)** — Yapı Kredi Posnet virtual POS

## License

MIT — [Yasin Kuyu](https://github.com/yasinkuyu)
