> **ℹ️ Kapsamlı Türkçe Dokümantasyon:**
>
> Bu paketin tüm fonksiyonlarını, kullanım senaryolarını ve testlerini detaylıca incelemek için modern, interaktif HTML dokümantasyonunu kullanabilirsiniz:
>
> - `docs/index.html` dosyasını tarayıcınızda açın.
> - Veya çevrimiçi olarak: [https://developertugrul.github.io/paytr-laravel-client/](https://developertugrul.github.io/paytr-laravel-client/)
>
> **Not:** Bu paket MIT lisansı ile yayınlanmıştır ve tamamen açık kaynaklıdır (opensource). Dilediğiniz gibi kullanabilir, dağıtabilir ve geliştirebilirsiniz.

# PayTR Laravel Client

Laravel için güncel, güvenli ve kapsamlı PayTR ödeme entegrasyon paketi. Güncel PayTR API dokümantasyonuna tam uyumlu.

## 🚀 Özellikler

### ✅ Temel Özellikler
- **Ödeme Yapma**: Direct API, iFrame API desteği
- **Ödeme Durumu Sorgulama**: Gerçek zamanlı ödeme durumu
- **İptaller**: Tam ve kısmi iptal işlemleri
- **İadeler**: Tam ve kısmi iade işlemleri
- **Kart Saklama**: PCI-DSS uyumlu kart saklama ve yönetimi

### 🔥 Gelişmiş Özellikler
- **Webhook Desteği**: Otomatik bildirim sistemi
- **Test/Sandbox Modu**: Geliştirme ortamı desteği
- **Güvenlik**: HMAC imzalama, IP kontrolü, SSL doğrulama
- **Hata Yönetimi**: Kapsamlı exception handling

### 🛡️ Güvenlik Özellikleri
- Tüm hassas veriler .env ve config ile yönetilir
- Tüm API çağrıları HMAC/Hash ile imzalanır
- Güçlü input validation ve sanitizasyon
- Sensitive data asla loglanmaz
- Webhooks için IP ve signature doğrulama
- SSL sertifika doğrulama
- User-Agent header ile güvenlik

## 📦 Kurulum

```bash
composer require developertugrul/paytr-laravel-client
php artisan vendor:publish --tag=paytr-config
```

## ⚙️ Konfigürasyon

`.env` dosyanıza aşağıdaki satırları ekleyin:

```env
PAYTR_MERCHANT_ID=xxxx
PAYTR_MERCHANT_KEY=xxxx
PAYTR_MERCHANT_SALT=xxxx
PAYTR_DEBUG=false
PAYTR_SANDBOX=true
PAYTR_WEBHOOK_SECRET=your_webhook_secret
PAYTR_ALLOWED_IPS=192.168.1.1,192.168.1.2
PAYTR_TIMEOUT=30
PAYTR_DEFAULT_TIMEOUT=0
PAYTR_VERIFY_SSL=true
PAYTR_DIRECT_API_URL=https://www.paytr.com/odeme
```

`PAYTR_SANDBOX` ayarı Direct API taleplerinde `test_mode` değerini otomatik olarak belirler.

> **Not:** PayTR, `user_ip` değerinin gerçek IP adresi olmasını bekler ve bu
> bilgiyi doğrular. Lokal geliştirme yaparken `request()->ip()` sonucu (örn.
> `127.0.0.1`) geçersiz sayılır. Bu nedenle test ortamında `user_ip`
> parametresini makinenizin **public IP** adresiyle (örneğin `.env` içinde
> `PAYTR_USER_IP` tanımlayarak) göndermelisiniz. Bu uyarı PayTR
> dokümantasyonunda da belirtilmektedir.

`PAYTR_WEBHOOK_SECRET` mutlaka tanımlanmalıdır, aksi halde gelen webhook
istekleri imza doğrulamasından geçmeyecek ve reddedilecektir.

## 🎯 Kullanım Örnekleri

### 📦 Sepet Formatı

PayTR API'de sepet verisi özel formatta gönderilmelidir:

```php
// PayTR Sepet Formatı: [['Ürün Adı', 'Fiyat', 'Adet'], ...]
$basket = [
    ['Test Ürün 1', '100.00', 1],     // [ürün_adı, fiyat, adet]
    ['Test Ürün 2', '50.50', 2],      // [ürün_adı, fiyat, adet]
    ['Test Ürün 3', '25.25', 3],      // [ürün_adı, fiyat, adet]
];

// Paket otomatik olarak sepeti base64_encode(json_encode(...)) ile kodlar
```

### Temel Ödeme İşlemleri

```php
use Paytr\Facades\Paytr;

// Direct API ile ödeme
$response = Paytr::payment()->pay([
    // Zorunlu parametreler (otomatik alınır: merchant_id, user_ip, test_mode, debug_on, client_lang)
    'merchant_oid' => 'TEST_' . time(), // Benzersiz sipariş numarası
    'email' => 'customer@example.com',
    'payment_amount' => 100.00, // PayTR'de ondalık nokta kullanılır
    'payment_type' => 'card',
    'installment_count' => 0,
    'currency' => 'TL',
    'non_3d' => 0,
    'request_exp_date' => date('Y-m-d H:i:s', strtotime('+1 hour')),
    
    // Müşteri bilgileri
    'user_name' => 'John Doe',
    'user_address' => 'İstanbul, Türkiye',
    'user_phone' => '5551234567',
    
    // URL'ler (doğru isimlendirme)
    'merchant_ok_url' => 'https://example.com/success',
    'merchant_fail_url' => 'https://example.com/fail',
    
    // Sepet (PayTR formatında)
    'basket' => [
        ['Test Ürün', '100.00', 1], // [ürün_adı, fiyat, adet]
    ],

    // Direct API için zorunlu kart bilgileri
    'cc_owner' => 'John Doe',
    'card_number' => '4355084355084358',
    'expiry_month' => '12',
    'expiry_year' => '25',
    'cvv' => '000',

    // Opsiyonel parametreler
    'lang' => 'tr',
    'sync_mode' => 0, // 0: async, 1: sync
    'non3d_test_failed' => 0,
    'card_type' => '', // Boş bırakılabilir
]);

// iFrame API ile token oluşturma
$token = Paytr::payment()->createIframeToken([
    'merchant_oid' => 'TEST_' . time(),
    'email' => 'customer@example.com',
    'payment_amount' => 100.00,
    'user_name' => 'John Doe',
    'user_address' => 'İstanbul, Türkiye',
    'user_phone' => '5551234567',
    'merchant_ok_url' => 'https://example.com/success',
    'merchant_fail_url' => 'https://example.com/fail',
    'basket' => [
        ['Test Ürün', '100.00', 1], // [ürün_adı, fiyat, adet]
    ],
]);

// Ödeme durumu sorgulama
$status = Paytr::payment()->getPaymentStatus('ORDER123');
```

### İptal İşlemleri

```php
// Tam iptal
Paytr::cancel()->cancel('ORDER123');

// Kısmi iptal
Paytr::cancel()->partialCancel('ORDER123', 5000); // 50 TL
```


### Kart Saklama

```php
// Yeni kart kaydetme
$cardToken = Paytr::card()->storeCard([
    'customer_id' => 'CUST123',
    'cc_owner' => 'John Doe',
    'card_number' => '4111111111111111',
    'expiry_month' => '12',
    'expiry_year' => '2025',
    'cvv' => '123',
]);

// Kayıtlı kartla ödeme
Paytr::card()->payWithCard($cardToken, [
    'amount' => 10000,
    'merchant_oid' => 'ORDER123',
    'installment_count' => 0,
]);

// Kartları listeleme
$cards = Paytr::card()->listCards('CUST123');

// Kart silme
Paytr::card()->deleteCard($cardToken);
```

### Webhook İşleme

PayTR webhook'larının doğruluğunu kontrol etmek için `paytr.signature` middleware'ini kullanın. Middleware, gelen isteğin gövdesi ile `X-PayTR-Signature` başlığını karşılaştırarak imzayı doğrular.

```php
// routes/paytr.php dosyasında
Route::post('/paytr/webhook', [WebhookController::class, 'handle'])
    ->middleware('paytr.signature');
```

### Gelişmiş PayTR API Özellikleri

```php
use Paytr\Facades\Paytr;

// 1. Link ile ödeme oluşturma
$link = Paytr::link()->createLink([
    'email' => 'customer@example.com',
    'amount' => 10000,
    'user_name' => 'John Doe',
    'user_address' => 'İstanbul',
    'user_phone' => '5551234567',
    'basket' => [
        ['name' => 'Ürün 1', 'price' => 10000, 'quantity' => 1],
    ],
]);

// Link silme
Paytr::link()->deleteLink($link['link_id']);

// Link SMS/Email bildirimi
Paytr::link()->sendLinkNotification($link['link_id'], 'sms');

// 2. Ön Provizyon (Pre-Provision)
Paytr::payment()->preProvision([
    'merchant_oid' => 'ORDER123',
    'email' => 'customer@example.com',
    'amount' => 10000,
    'user_name' => 'John Doe',
    'user_address' => 'İstanbul',
    'user_phone' => '5551234567',
    'basket' => [
        ['name' => 'Ürün 1', 'price' => 10000, 'quantity' => 1],
    ],
]);

// 3. EFT/Havale iFrame ile ödeme
Paytr::payment()->createEftIframe([
    'merchant_oid' => 'ORDER123',
    'email' => 'customer@example.com',
    'amount' => 10000,
    'user_name' => 'John Doe',
    'user_address' => 'İstanbul',
    'user_phone' => '5551234567',
    'basket' => [
        ['name' => 'Ürün 1', 'price' => 10000, 'quantity' => 1],
    ],
]);

// 4. Platform Transfer işlemleri
Paytr::platform()->createTransfer([
    'amount' => 10000,
    'iban' => 'TR000000000000000000000000',
    'description' => 'Alt bayi ödemesi',
]);
Paytr::platform()->getTransferResult('TRANSFER_ID');
Paytr::platform()->getReturningPayments([
    'date_start' => '2024-01-01',
    'date_end' => '2024-01-31',
]);
Paytr::platform()->sendReturningPayment([
    'trans_id' => '123456',
    'amount' => 5000,
    'iban' => 'TR000000000000000000000000',
    'name' => 'John Doe',
]);
// 5. BKM Express ile ödeme
Paytr::payment()->payWithBkmExpress([
    'merchant_oid' => 'ORDER123',
    'email' => 'customer@example.com',
    'amount' => 10000,
    'user_name' => 'John Doe',
    'user_address' => 'İstanbul',
    'user_phone' => '5551234567',
    'basket' => [
        ['name' => 'Ürün 1', 'price' => 10000, 'quantity' => 1],
    ],
]);

// 6. Taksit oranı sorgulama
Paytr::payment()->getInstallmentRates('411111');

// 7. BIN sorgulama
Paytr::payment()->lookupBin('411111');

// 8. İşlem detayı sorgulama
Paytr::payment()->getTransactionDetail('ORDER123');

// 9. Ödeme raporu (statement)
Paytr::payment()->getPaymentStatement([
    'date_start' => '2024-01-01',
    'date_end' => '2024-01-31',
]);

// 10. Ödeme detayı sorgulama
Paytr::payment()->getPaymentDetail('PAYMENT_ID');

// 11. İade durumu sorgulama
Paytr::refund()->getRefundStatus('ORDER123');

// 12. Tekrarlayan ödeme (recurring)
Paytr::card()->recurringPayment($cardToken, [
    'amount' => 10000,
    'merchant_oid' => 'ORDER123',
    'installment_count' => 0,
]);
```

## 🔧 Piyasadaki Diğer Kütüphanelerden Farklar

| Özellik | Bu Paket | Diğer Kütüphaneler |
|---------|-----------|-------------------|
| **Direct API** | ✅ | ✅ |
| **iFrame API** | ✅ | ✅ |
| **Kart Saklama** | ✅ | ❌ |
| **Webhook Güvenliği** | ✅ | ❌ |
| **SSL Doğrulama** | ✅ | ❌ |
| **User-Agent Header** | ✅ | ❌ |
| **Tam Yorumlu Kod** | ✅ | ❌ |
| **Kapsamlı Testler** | ✅ | ❌ |
| **Güncel API Uyumluluğu** | ✅ | ❌ |

## 🧪 Test

```bash
./vendor/bin/phpunit
```

## 📚 API Dokümantasyonu

Detaylı API dokümantasyonu için [PayTR Developer Portal](https://dev.paytr.com/en/direkt-api) adresini ziyaret edin.

## 🤝 Katkıda Bulunma

1. Fork edin
2. Feature branch oluşturun (`git checkout -b feature/amazing-feature`)
3. Commit edin (`git commit -m 'Add amazing feature'`)
4. Push edin (`git push origin feature/amazing-feature`)
5. Pull Request oluşturun

## 📄 Lisans

Bu proje MIT lisansı altında lisanslanmıştır. Detaylar için `LICENSE` dosyasına bakın.

## 🆘 Destek

- **GitHub Issues**: [GitHub Issues](https://github.com/developertugrul/paytr-laravel-client/issues)
- **Email**: iletisim@tugrulyildirim.com
- **PayTR Destek**: [PayTR Destek Merkezi](https://www.paytr.com/destek)
- **Whatsapp Destek**: [Whatsapp İletişim](https://wa.me/905312354229)

## 🔄 Changelog

### v1.0.0
- İlk sürüm
- Güncel PayTR API uyumluluğu
- Direct API ve iFrame API desteği
- Kart saklama ve yönetimi
- Webhook güvenliği
- SSL doğrulama
- Kapsamlı hata yönetimi
