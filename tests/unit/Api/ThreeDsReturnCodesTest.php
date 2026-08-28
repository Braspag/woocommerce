<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/stubs/woocommerce-stubs.php';
require_once dirname(__DIR__, 3) . '/includes/class-wc-braspag-3ds-return-codes.php';

/**
 * Testa WC_Braspag_3ds_Return_Codes — classificação dos return codes 3DS
 * documentados em docs.cielo.com.br/gateway/docs/return-codes-3ds.
 */
class ThreeDsReturnCodesTest extends TestCase
{
    public function test_classifica_sucesso(): void
    {
        $this->assertSame(WC_Braspag_3ds_Return_Codes::SUCCESS, WC_Braspag_3ds_Return_Codes::classify('100'));
    }

    public function test_classifica_erros_de_validacao(): void
    {
        $this->assertSame(WC_Braspag_3ds_Return_Codes::VALIDATION, WC_Braspag_3ds_Return_Codes::classify('101'));
        $this->assertSame(WC_Braspag_3ds_Return_Codes::VALIDATION, WC_Braspag_3ds_Return_Codes::classify('102'));
    }

    public function test_classifica_erros_sistemicos(): void
    {
        $this->assertSame(WC_Braspag_3ds_Return_Codes::SYSTEM, WC_Braspag_3ds_Return_Codes::classify('150'));
        $this->assertSame(WC_Braspag_3ds_Return_Codes::SYSTEM, WC_Braspag_3ds_Return_Codes::classify('151'));
        $this->assertSame(WC_Braspag_3ds_Return_Codes::SYSTEM, WC_Braspag_3ds_Return_Codes::classify('152'));
    }

    public function test_classifica_erro_de_configuracao(): void
    {
        $this->assertSame(WC_Braspag_3ds_Return_Codes::CONFIGURATION, WC_Braspag_3ds_Return_Codes::classify('234'));
    }

    public function test_classifica_autenticacao_obrigatoria(): void
    {
        $this->assertSame(WC_Braspag_3ds_Return_Codes::AUTHENTICATION_REQUIRED, WC_Braspag_3ds_Return_Codes::classify('475'));
        $this->assertSame(WC_Braspag_3ds_Return_Codes::AUTHENTICATION_REQUIRED, WC_Braspag_3ds_Return_Codes::classify('476'));
    }

    public function test_classifica_erros_mpi(): void
    {
        $this->assertSame(WC_Braspag_3ds_Return_Codes::MPI_ERROR, WC_Braspag_3ds_Return_Codes::classify('MPI600'));
        $this->assertSame(WC_Braspag_3ds_Return_Codes::MPI_ERROR, WC_Braspag_3ds_Return_Codes::classify('MPI601'));
        $this->assertSame(WC_Braspag_3ds_Return_Codes::MPI_ERROR, WC_Braspag_3ds_Return_Codes::classify('MPI900'));
        $this->assertSame(WC_Braspag_3ds_Return_Codes::MPI_ERROR, WC_Braspag_3ds_Return_Codes::classify('MPI901'));
        $this->assertSame(WC_Braspag_3ds_Return_Codes::MPI_ERROR, WC_Braspag_3ds_Return_Codes::classify('MPI902'));
    }

    public function test_classifica_codigo_desconhecido_como_unknown(): void
    {
        $this->assertSame(WC_Braspag_3ds_Return_Codes::UNKNOWN, WC_Braspag_3ds_Return_Codes::classify('999'));
        $this->assertSame(WC_Braspag_3ds_Return_Codes::UNKNOWN, WC_Braspag_3ds_Return_Codes::classify(''));
    }

    public function test_classificacao_e_case_insensitive_para_codigos_mpi(): void
    {
        $this->assertSame(WC_Braspag_3ds_Return_Codes::MPI_ERROR, WC_Braspag_3ds_Return_Codes::classify('mpi600'));
    }

    public function test_is_retryable_true_apenas_para_erros_sistemicos(): void
    {
        $this->assertTrue(WC_Braspag_3ds_Return_Codes::is_retryable('150'));
        $this->assertFalse(WC_Braspag_3ds_Return_Codes::is_retryable('234'));
        $this->assertFalse(WC_Braspag_3ds_Return_Codes::is_retryable('100'));
        $this->assertFalse(WC_Braspag_3ds_Return_Codes::is_retryable('999'));
    }
}
