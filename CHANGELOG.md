# Değişiklikler

## 1.0.0

- İlk sürüm: Akbank yeni sanal POS altyapısı, Omnipay v3, PHP 8.1+
- Ortak ödeme sayfası (3D Pay Hosting, txnCode 3000); kart bilgisi sunucudan geçmez
- HMAC-SHA512 imza; dönüşte `hash` / `hashParams` doğrulaması (sonuç kodu ve sipariş no imzada olmalı)
- JSON API: iade (1002), iptal (1003), sipariş sorgusu (1010), `auth-hash` başlığı
- Akbank test ortamında ödeme sayfası ve imzalı dönüşler doğrulandı
