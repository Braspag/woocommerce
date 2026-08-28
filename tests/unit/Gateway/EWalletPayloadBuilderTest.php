<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa os builders de payload do gateway braspag_ewallet.
 * Usa newInstanceWithoutConstructor() + ReflectionProperty para evitar dependências WP.
 */
class EWalletPayloadBuilderTest extends TestCase
{
    private WC_Gateway_Braspag_EWallet $gateway;

    protected function setUp(): void
    {
        $ref           = new ReflectionClass(WC_Gateway_Braspag_EWallet::class);
        $this->gateway = $ref->newInstanceWithoutConstructor();

        $this->setProp('installments', 1);
        $this->setProp('soft_descriptor', 'LojaTest');

        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    private function setProp(string $name, mixed $value): void
    {
        $ref  = new ReflectionClass($this->gateway);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($this->gateway, $value);
    }

    private function makeOrder(float $total = 100.00, int $id = 42): WC_Order
    {
        $order = $this->createMock(WC_Order::class);
        $order->method('get_total')->willReturn($total);
        $order->method('get_id')->willReturn($id);
        return $order;
    }

    // ── Apple Pay ──────────────────────────────────────────────────────────────

    public function test_apple_pay_payload_tem_wallet_type_correto(): void
    {
        $_POST['braspag_wallet_type'] = 'ApplePay';
        $_POST['braspag_wallet_key']  = 'base64encodedtoken==';

        $payment = $this->gateway->build_ewallet_payment_request([], $this->makeOrder(), null, null);

        $this->assertSame('ApplePay', $payment['Wallet']['Type']);
        $this->assertSame('base64encodedtoken==', $payment['Wallet']['WalletKey']);
        $this->assertSame('CreditCard', $payment['Type']);
        $this->assertSame(10000, $payment['Amount']);
    }

    public function test_apple_pay_additional_data_tem_ephemeral_e_signature(): void
    {
        $_POST['braspag_wallet_ephemeral_public_key'] = 'ephKey123';
        $_POST['braspag_wallet_signature']            = 'sig456';

        $payment = ['Wallet' => ['Type' => 'ApplePay', 'WalletKey' => 'key']];
        $result  = $this->gateway->build_applepay_additional_data($payment, $this->makeOrder());

        $this->assertArrayHasKey('AdditionalData', $result['Wallet']);
        $this->assertSame('ephKey123', $result['Wallet']['AdditionalData']['EphemeralPublicKey']);
        $this->assertSame('sig456', $result['Wallet']['AdditionalData']['Signature']);
    }

    public function test_apple_pay_sem_ephemeral_nao_adiciona_additional_data(): void
    {
        $_POST['braspag_wallet_ephemeral_public_key'] = '';
        $_POST['braspag_wallet_signature']            = '';

        $payment = ['Wallet' => ['Type' => 'ApplePay', 'WalletKey' => 'key']];
        $result  = $this->gateway->build_applepay_additional_data($payment, $this->makeOrder());

        $this->assertArrayNotHasKey('AdditionalData', $result['Wallet']);
    }

    // ── Google Pay ────────────────────────────────────────────────────────────

    public function test_google_pay_payload_tem_wallet_type_correto(): void
    {
        $_POST['braspag_wallet_type'] = 'GooglePay';
        $_POST['braspag_wallet_key']  = '{"signature":"abc","protocolVersion":"ECv2"}';

        $payment = $this->gateway->build_ewallet_payment_request([], $this->makeOrder(), null, null);

        $this->assertSame('GooglePay', $payment['Wallet']['Type']);
    }

    public function test_google_pay_com_capture_code_adiciona_additional_data(): void
    {
        $_POST['braspag_wallet_capture_code'] = 'CAP123';

        $payment = ['Wallet' => ['Type' => 'GooglePay', 'WalletKey' => 'token']];
        $result  = $this->gateway->build_googlepay_additional_data($payment, $this->makeOrder());

        $this->assertArrayHasKey('AdditionalData', $result['Wallet']);
        $this->assertSame('CAP123', $result['Wallet']['AdditionalData']['CaptureCode']);
    }

    public function test_google_pay_sem_capture_code_nao_adiciona_additional_data(): void
    {
        $_POST['braspag_wallet_capture_code'] = '';

        $payment = ['Wallet' => ['Type' => 'GooglePay', 'WalletKey' => 'token']];
        $result  = $this->gateway->build_googlepay_additional_data($payment, $this->makeOrder());

        $this->assertArrayNotHasKey('AdditionalData', $result['Wallet']);
    }

    // ── Samsung Pay ───────────────────────────────────────────────────────────

    public function test_samsung_pay_payload_sem_additional_data(): void
    {
        $_POST['braspag_wallet_type'] = 'SamsungPay';
        $_POST['braspag_wallet_key']  = 'ref_id_samsung_123';

        $payment = $this->gateway->build_ewallet_payment_request([], $this->makeOrder(), null, null);

        $this->assertSame('SamsungPay', $payment['Wallet']['Type']);
        $this->assertSame('ref_id_samsung_123', $payment['Wallet']['WalletKey']);
        $this->assertArrayNotHasKey('AdditionalData', $payment['Wallet']);
    }

    // ── Soft descriptor ───────────────────────────────────────────────────────

    public function test_soft_descriptor_incluido_quando_configurado(): void
    {
        $_POST['braspag_wallet_type'] = 'ApplePay';
        $_POST['braspag_wallet_key']  = 'key';

        $payment = $this->gateway->build_ewallet_payment_request([], $this->makeOrder(), null, null);

        $this->assertArrayHasKey('SoftDescriptor', $payment);
        $this->assertSame('LojaTest', $payment['SoftDescriptor']);
    }

    public function test_soft_descriptor_ausente_quando_vazio(): void
    {
        $this->setProp('soft_descriptor', '');

        $_POST['braspag_wallet_type'] = 'ApplePay';
        $_POST['braspag_wallet_key']  = 'key';

        $payment = $this->gateway->build_ewallet_payment_request([], $this->makeOrder(), null, null);

        $this->assertArrayNotHasKey('SoftDescriptor', $payment);
    }

    // ── Amount em centavos ────────────────────────────────────────────────────

    public function test_amount_convertido_para_centavos(): void
    {
        $_POST['braspag_wallet_type'] = 'ApplePay';
        $_POST['braspag_wallet_key']  = 'key';

        $payment = $this->gateway->build_ewallet_payment_request([], $this->makeOrder(50.75), null, null);

        $this->assertSame(5075, $payment['Amount']);
    }

    // ── Mascaramento de wallet_key no log ─────────────────────────────────────

    public function test_wallet_key_nao_aparece_completo_no_stdout(): void
    {
        $longKey = str_repeat('ABCDEFGHIJ', 10);
        $_POST['braspag_wallet_type'] = 'ApplePay';
        $_POST['braspag_wallet_key']  = $longKey;

        ob_start();
        $this->gateway->build_ewallet_payment_request([], $this->makeOrder(), null, null);
        $output = ob_get_clean();

        $this->assertStringNotContainsString($longKey, (string) $output);
    }
}
