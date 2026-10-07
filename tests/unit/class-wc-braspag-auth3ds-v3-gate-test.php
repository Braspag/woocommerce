<?php

use PHPUnit\Framework\TestCase;

/**
 * Testes unitários de WC_Braspag_Auth3ds_V3_Gate — lógica de gate
 * compartilhada entre os builders de crédito e débito na migração para
 * MPI v3, cobrindo especificamente os bugs fechados pela extração
 * (docs/specs/integrations/3ds-auditoria-2026-09-07.md):
 *
 * - 3DS-08: código de `failure_type` desconhecido deve bloquear por
 *   padrão (fail-closed) — antes disso, o switch do débito não tinha
 *   `default` e deixava passar em silêncio.
 * - 3DS-09: o builder de débito não tinha o gate de bloqueio que o de
 *   crédito já tinha — com a extração, ambos usam exatamente a mesma
 *   função, então testar a função uma vez cobre os dois.
 *
 * (3DS-07 — `Authenticate` sempre `true` no débito por comparação não
 * estrita — é testado separadamente, próximo ao código que faz a
 * comparação, já que não faz parte deste gate.)
 */
class WC_Braspag_Auth3ds_V3_Gate_Test extends TestCase
{
    protected function base_settings($overrides = array())
    {
        return array_merge(
            array(
                'authorize_on_error' => 'no',
                'authorize_on_failure' => 'no',
                'authorize_on_unenrolled' => 'no',
                'authorize_on_unsupported_brand' => 'no',
                'test_mode' => true,
                'is_cielo' => true,
            ),
            $overrides
        );
    }

    public function test_sem_falha_nao_bloqueia()
    {
        $this->assertFalse(WC_Braspag_Auth3ds_V3_Gate::should_block('', $this->base_settings()));
        $this->assertFalse(WC_Braspag_Auth3ds_V3_Gate::should_block('0', $this->base_settings()));
    }

    public function test_falha_conhecida_bloqueia_quando_authorize_on_e_no()
    {
        $this->assertTrue(WC_Braspag_Auth3ds_V3_Gate::should_block('4', $this->base_settings(array('authorize_on_error' => 'no'))));
        $this->assertTrue(WC_Braspag_Auth3ds_V3_Gate::should_block('1', $this->base_settings(array('authorize_on_failure' => 'no'))));
        $this->assertTrue(WC_Braspag_Auth3ds_V3_Gate::should_block('2', $this->base_settings(array('authorize_on_unenrolled' => 'no'))));
        $this->assertTrue(WC_Braspag_Auth3ds_V3_Gate::should_block('5', $this->base_settings(array('authorize_on_unsupported_brand' => 'no'))));
    }

    public function test_falha_conhecida_nao_bloqueia_quando_authorize_on_e_yes()
    {
        $this->assertFalse(WC_Braspag_Auth3ds_V3_Gate::should_block('4', $this->base_settings(array('authorize_on_error' => 'yes'))));
        $this->assertFalse(WC_Braspag_Auth3ds_V3_Gate::should_block('1', $this->base_settings(array('authorize_on_failure' => 'yes'))));
        $this->assertFalse(WC_Braspag_Auth3ds_V3_Gate::should_block('2', $this->base_settings(array('authorize_on_unenrolled' => 'yes'))));
        $this->assertFalse(WC_Braspag_Auth3ds_V3_Gate::should_block('5', $this->base_settings(array('authorize_on_unsupported_brand' => 'yes'))));
    }

    /**
     * 3DS-08 / 3DS-09: código de failure_type fora do mapa conhecido
     * (ex.: '9', nunca documentado) deve bloquear por padrão, mesmo com
     * todas as flags de "autorizar mesmo assim" em 'yes'.
     */
    public function test_failure_type_desconhecido_bloqueia_por_padrao()
    {
        $settings = $this->base_settings(array(
            'authorize_on_error' => 'yes',
            'authorize_on_failure' => 'yes',
            'authorize_on_unenrolled' => 'yes',
            'authorize_on_unsupported_brand' => 'yes',
        ));

        $this->assertTrue(WC_Braspag_Auth3ds_V3_Gate::should_block('9', $settings));
    }

    public function test_producao_nao_cielo_forca_bloqueio_em_outros_failure_types()
    {
        $settings = $this->base_settings(array(
            'authorize_on_failure' => 'yes',
            'test_mode' => false,
            'is_cielo' => false,
        ));

        $this->assertTrue(WC_Braspag_Auth3ds_V3_Gate::should_block('1', $settings));
    }

    /**
     * Data Only (failure_type '3') não é falha: antes caía no `default`
     * fail-closed e bloqueava toda transação Data Only.
     */
    public function test_data_only_nunca_bloqueia()
    {
        $settings = $this->base_settings(array(
            'authorize_on_error' => 'no',
            'authorize_on_failure' => 'no',
            'authorize_on_unenrolled' => 'no',
            'authorize_on_unsupported_brand' => 'no',
        ));

        $this->assertFalse(WC_Braspag_Auth3ds_V3_Gate::should_block('3', $settings));
    }

    public function test_data_only_nao_bloqueia_nem_em_producao_fora_da_cielo()
    {
        $settings = $this->base_settings(array(
            'test_mode' => false,
            'is_cielo' => false,
        ));

        $this->assertFalse(WC_Braspag_Auth3ds_V3_Gate::should_block('3', $settings));
    }

    /** ---------------------------------------------------------------
     * build_external_authentication() — nó enviado ao Pagador
     * --------------------------------------------------------------- */

    protected function auth_values($overrides = array())
    {
        return array_merge(
            array(
                'cavv' => 'AJkBBkhgQQAAAE4gSEJydQAAAAA=',
                'xid' => 'XID-123',
                'eci' => '05',
                'version' => '2.2.0',
                'reference_id' => 'ref-abc',
                'data_only' => false,
            ),
            $overrides
        );
    }

    public function test_autenticado_envia_cavv_xid_eci_version_e_reference_id()
    {
        $node = WC_Braspag_Auth3ds_V3_Gate::build_external_authentication($this->auth_values(), true);

        $this->assertSame('AJkBBkhgQQAAAE4gSEJydQAAAAA=', $node['Cavv']);
        $this->assertSame('XID-123', $node['Xid']);
        $this->assertSame('05', $node['Eci']);
        $this->assertSame('2.2.0', $node['Version']);
        // "ID" maiúsculo: é o nome documentado pela Cielo para o Pagador.
        $this->assertSame('ref-abc', $node['ReferenceID']);
        $this->assertArrayNotHasKey('DataOnly', $node);
    }

    /**
     * BDD-3DS-013 / INV-007: prosseguir sem autenticação não pode afirmar
     * autenticação — o Cavv (criptograma que comunica liability shift) fica
     * de fora, mas o Eci é preservado (RN-3DS-008/BDD-3DS-012).
     */
    public function test_nao_autenticado_preserva_eci_mas_omite_cavv_e_xid()
    {
        $node = WC_Braspag_Auth3ds_V3_Gate::build_external_authentication($this->auth_values(), false);

        $this->assertSame('05', $node['Eci']);
        $this->assertSame('2.2.0', $node['Version']);
        $this->assertSame('ref-abc', $node['ReferenceID']);
        $this->assertArrayNotHasKey('Cavv', $node);
        $this->assertArrayNotHasKey('Xid', $node);
    }

    public function test_data_only_adiciona_flag_booleana()
    {
        $node = WC_Braspag_Auth3ds_V3_Gate::build_external_authentication(
            $this->auth_values(array('data_only' => true)),
            true
        );

        $this->assertTrue($node['DataOnly']);
    }
}
