<?php

namespace Omnipay\Akbank\Tests;

use Omnipay\Akbank\Gateway;
use Omnipay\Akbank\Message\AbstractRequest;
use Omnipay\Tests\GatewayTestCase;
use Symfony\Component\HttpFoundation\Request;

class GatewayTest extends GatewayTestCase
{
    /** @var Gateway */
    protected $gateway;

    /** Akbank'ın herkese açık test hesabı */
    private const KEY = '3230323330393034313735303032363031353172675f357637355f3273387373745f7233725f73323333383737335f323272383774767276327672323531355f';
    private const CREDS = ['merchantSafeId' => '2023090417500272654BD9A49CF07574', 'terminalSafeId' => '2023090417500284633D137A249DBBEB', 'secretKey' => self::KEY, 'testMode' => true];

    public function setUp(): void
    {
        parent::setUp();
        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $this->gateway->initialize(self::CREDS);
    }

    public function testPurchaseBuildsSignedHostingForm(): void
    {
        $r = $this->gateway->purchase(['amount' => '149.90', 'transactionId' => 'SIP-1', 'returnUrl' => 'https://shop.test/r', 'installment' => 3, 'card' => ['email' => 'a@b.test']])->send();
        $this->assertTrue($r->isRedirect());
        $this->assertSame(AbstractRequest::TEST_GATE, $r->getRedirectUrl());
        $d = $r->getRedirectData();
        $this->assertSame(['3D_PAY_HOSTING', '3000', '149.90', '949', '3', 'TR'], [$d['paymentModel'], $d['txnCode'], $d['amount'], $d['currencyCode'], $d['installCount'], $d['lang']]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.000$/', $d['requestDateTime']);
        $this->assertSame(128, strlen($d['randomNumber']));
        $s = '3D_PAY_HOSTING3000' . $d['merchantSafeId'] . $d['terminalSafeId'] . 'SIP-1TR149.90' . '9493https://shop.test/rhttps://shop.test/ra@b.test' . $d['randomNumber'] . $d['requestDateTime'];
        $this->assertSame(base64_encode(hash_hmac('sha512', $s, self::KEY, true)), $d['hash']);
        $this->assertArrayNotHasKey('creditCard', $d);
    }

    public function testSinglePaymentAndLiveEndpoint(): void
    {
        $this->gateway->setTestMode(false);
        $r = $this->gateway->purchase(['amount' => '1.00', 'transactionId' => 'X', 'returnUrl' => 'https://shop.test/r'])->send();
        $this->assertSame('1', $r->getRedirectData()['installCount']);
        $this->assertSame(AbstractRequest::LIVE_GATE, $r->getRedirectUrl());
    }

    private function returning(string $file): void
    {
        parse_str(trim(file_get_contents(__DIR__ . '/Mock/' . $file)), $f);
        $this->gateway = new Gateway($this->getHttpClient(), Request::create('/r', 'POST', $f));
        $this->gateway->initialize(self::CREDS);
    }

    /** Akbank test ortamından alınmış gerçek dönüşler (2026-11-21) */
    public function testRealCancelledReturnIsVerifiedAsFailed(): void
    {
        $this->returning('ReturnCancelled.txt');
        $r = $this->gateway->completePurchase(['transactionId' => 'AKB1795291759453'])->send();
        $this->assertTrue($r->isVerified());
        $this->assertFalse($r->isSuccessful());
        $this->assertSame('Kullanıcı tarafından iptal edildi.', $r->getMessage());
    }

    public function testReal3DFailureReturn(): void
    {
        $this->returning('Return3DFailed.txt');
        $r = $this->gateway->completePurchase()->send();
        $this->assertTrue($r->isVerified());
        $this->assertFalse($r->isSuccessful());
        $this->assertSame('0', $r->getMdStatus());
        $this->assertSame('435509******5232', $r->getMaskedCard());
        $this->assertSame('BKM 3DS Server Doğrulama Yapılamadı', $r->getMessage());
    }

    public function testTamperedOrWrongKeyIsRejected(): void
    {
        $this->returning('ReturnCancelled.txt');
        $this->assertFalse($this->gateway->completePurchase(['transactionId' => 'BASKA'])->send()->isVerified());

        parse_str(trim(file_get_contents(__DIR__ . '/Mock/ReturnCancelled.txt')), $f);
        $f['responseCode'] = 'VPS-0000';
        $this->assertFalse($this->gateway->completePurchase(['fields' => $f])->send()->isVerified());

        $this->returning('ReturnCancelled.txt');
        $this->gateway->setSecretKey('BASKA');
        $this->assertFalse($this->gateway->completePurchase()->send()->isVerified());
    }

    public function testApprovedReturn(): void
    {
        $f = ['txnCode' => '3000', 'responseCode' => 'VPS-0000', 'responseMessage' => 'BAŞARILI', 'hostResponseCode' => '00', 'hostMessage' => '000 ONAY KODU 306460',
            'merchantSafeId' => self::CREDS['merchantSafeId'], 'terminalSafeId' => self::CREDS['terminalSafeId'], 'orderId' => 'SIP-1', 'authCode' => '306460', 'rrn' => '411024360239',
            'amount' => '149.90', 'installCount' => '3', 'maskedCardNumber' => '435509******5232'];
        $f['hashParams'] = 'txnCode+responseCode+responseMessage+hostResponseCode+hostMessage+merchantSafeId+terminalSafeId+orderId+authCode+rrn';
        $f['hash'] = base64_encode(hash_hmac('sha512', '3000VPS-0000BAŞARILI00000 ONAY KODU 306460' . self::CREDS['merchantSafeId'] . self::CREDS['terminalSafeId'] . 'SIP-1306460411024360239', self::KEY, true));
        $r = $this->gateway->completePurchase(['fields' => $f, 'transactionId' => 'SIP-1'])->send();
        $this->assertTrue($r->isVerified());
        $this->assertTrue($r->isSuccessful());
        $this->assertSame('411024360239', $r->getTransactionReference());
        $this->assertSame(3, $r->getInstallment());
        $this->assertSame('completed', $r->getTransactionStatus());
    }

    public function testResultCodeMustBeSigned(): void
    {
        $f = ['responseCode' => 'VPS-0000', 'orderId' => 'X', 'hashParams' => 'orderId'];
        $f['hash'] = base64_encode(hash_hmac('sha512', 'X', self::KEY, true));
        $this->assertFalse($this->gateway->completePurchase(['fields' => $f])->send()->isVerified());
    }

    public function testVoidSignsBodyInHeader(): void
    {
        $this->setMockHttpResponse('VoidSuccess.txt');
        $r = $this->gateway->void(['transactionId' => 'SIP-1'])->send();
        $this->assertTrue($r->isSuccessful());
        $this->assertSame('411024360239', $r->getTransactionReference());
        $req = $this->getMockClient()->getLastRequest();
        $body = (string) $req->getBody();
        $this->assertSame(base64_encode(hash_hmac('sha512', $body, self::KEY, true)), $req->getHeaderLine('auth-hash'));
        $j = json_decode($body, true);
        $this->assertSame(['1003', 'SIP-1', '1.00'], [$j['txnCode'], $j['order']['orderId'], $j['version']]);
        $this->assertSame(AbstractRequest::TEST_API, (string) $req->getUri());
    }

    public function testRefundFailure(): void
    {
        $this->setMockHttpResponse('RefundFailure.txt');
        $r = $this->gateway->refund(['transactionId' => 'SIP-1', 'amount' => '50.00'])->send();
        $this->assertTrue($r->isReadable());
        $this->assertFalse($r->isSuccessful());
        $this->assertSame('Islem bulunamadi', $r->getMessage());
        $j = json_decode((string) $this->getMockClient()->getLastRequest()->getBody(), true);
        $this->assertSame(['1002', '50.00', 949], [$j['txnCode'], $j['transaction']['amount'], $j['transaction']['currencyCode']]);
    }
}
