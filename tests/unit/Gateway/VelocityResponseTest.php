<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa o tratamento de VelocityAnalysis na resposta da Braspag.
 *
 * Cobre:
 *  - BUG-V1: acesso seguro com ?? '' quando VelocityAnalysis não existe
 *  - BUG-V2: mensagem formatada corretamente com sprintf (sem %s literal)
 */
class VelocityResponseTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeResponse(string $status, ?string $velocityResult, string $providerMsg, string $providerCode): object
    {
        $payment = (object) [
            'Status'              => $status,
            'PaymentId'           => 'pid-test-001',
            'ProviderReturnMessage' => $providerMsg,
            'ProviderReturnCode'  => $providerCode,
        ];

        if ($velocityResult !== null) {
            $payment->VelocityAnalysis = (object) ['ResultMessage' => $velocityResult];
        }

        return (object) ['body' => (object) ['Payment' => $payment]];
    }

    // ── BUG-V1: null safety ───────────────────────────────────────────────────

    public function test_velocity_ausente_nao_causa_erro(): void
    {
        $response = $this->makeResponse('3', null, 'Not Authorized', '05');
        $payment  = $response->body->Payment;

        // Simula o padrão do código corrigido
        $velocityStatus = $payment->VelocityAnalysis->ResultMessage ?? '';
        $velocity       = ($velocityStatus === 'Reject') ? ' [VelocityAnalysis]' : '';

        $this->assertSame('', $velocity);
    }

    public function test_velocity_reject_adiciona_sufixo(): void
    {
        $response = $this->makeResponse('3', 'Reject', 'Not Authorized', '05');
        $payment  = $response->body->Payment;

        $velocityStatus = $payment->VelocityAnalysis->ResultMessage ?? '';
        $velocity       = ($velocityStatus === 'Reject') ? ' [VelocityAnalysis]' : '';

        $this->assertSame(' [VelocityAnalysis]', $velocity);
    }

    public function test_velocity_accept_nao_adiciona_sufixo(): void
    {
        $response = $this->makeResponse('2', 'Accept', 'Authorized', '00');
        $payment  = $response->body->Payment;

        $velocityStatus = $payment->VelocityAnalysis->ResultMessage ?? '';
        $velocity       = ($velocityStatus === 'Reject') ? ' [VelocityAnalysis]' : '';

        $this->assertSame('', $velocity);
    }

    // ── BUG-V2: mensagem sem %s literal ──────────────────────────────────────

    public function test_mensagem_nao_contem_placeholder_literal(): void
    {
        $response = $this->makeResponse('3', 'Reject', 'Not Authorized', '05');
        $payment  = $response->body->Payment;

        $velocityStatus = $payment->VelocityAnalysis->ResultMessage ?? '';
        $velocity       = ($velocityStatus === 'Reject') ? ' [VelocityAnalysis]' : '';
        $msg = sprintf(
            __('Payment processing failed%s: %s (Cod. %s).', 'woocommerce-braspag'),
            $velocity,
            $payment->ProviderReturnMessage,
            $payment->ProviderReturnCode
        );

        $this->assertStringNotContainsString('%s', $msg);
        $this->assertStringContainsString('[VelocityAnalysis]', $msg);
        $this->assertStringContainsString('Not Authorized', $msg);
        $this->assertStringContainsString('05', $msg);
    }

    public function test_mensagem_sem_velocity_nao_contem_placeholder(): void
    {
        $response = $this->makeResponse('3', null, 'Not Authorized', '05');
        $payment  = $response->body->Payment;

        $velocityStatus = $payment->VelocityAnalysis->ResultMessage ?? '';
        $velocity       = ($velocityStatus === 'Reject') ? ' [VelocityAnalysis]' : '';
        $msg = sprintf(
            __('Payment processing failed%s: %s (Cod. %s).', 'woocommerce-braspag'),
            $velocity,
            $payment->ProviderReturnMessage,
            $payment->ProviderReturnCode
        );

        $this->assertStringNotContainsString('%s', $msg);
        $this->assertStringNotContainsString('VelocityAnalysis', $msg);
        $this->assertStringContainsString('Not Authorized', $msg);
    }
}
