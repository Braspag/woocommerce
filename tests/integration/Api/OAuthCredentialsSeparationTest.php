<?php

/**
 * Testes de integração que confirmam, a partir das configurações reais do
 * WooCommerce (não de arrays soltos como em tests/unit/Api/*), que SOP,
 * Verify Card/antifraude (OAuth genérico) e Authentication 3DS 2.2 usam
 * de fato 3 credenciais independentes — não há sobreposição/fallback
 * acidental entre `silentpost_oauth_client_id`, `oauth_authentication_client_id`
 * e `auth3ds20_oauth_authentication_client_id`.
 *
 * Requerem ambiente WordPress + WooCommerce completo.
 * Execute com: WP_TESTS_DIR=/tmp/wordpress-tests-lib ./vendor/bin/phpunit --testsuite Integration
 *
 * Para ativar, descomente o bloco de bootstrap WP em tests/bootstrap.php.
 *
 * @group integration
 */
class OAuthCredentialsSeparationTest extends WP_UnitTestCase
{
    // ── Credenciais diferentes entre si ─────────────────────────────────────

    /**
     * @test
     * Com as 3 seções configuradas com valores diferentes, cada método de API
     * deve montar um header Authorization diferente, lendo a chave que lhe
     * pertence e nenhuma outra.
     */
    public function test_cada_secao_usa_apenas_sua_propria_credencial(): void
    {
        update_option('woocommerce_braspag_settings', [
            'test_mode' => 'yes',
            'oauth_authentication_client_id'                => 'verify-id',
            'oauth_authentication_client_secret'             => 'verify-secret',
            'silentpost_oauth_client_id'                     => 'sop-id',
            'silentpost_oauth_client_secret'                 => 'sop-secret',
            'auth3ds20_oauth_authentication_client_id'       => 'auth3ds-id',
            'auth3ds20_oauth_authentication_client_secret'   => 'auth3ds-secret',
        ]);

        $settings = get_option('woocommerce_braspag_settings');

        $verifyHeaders = WC_Braspag_OAuth_API::get_headers($settings);
        $sopHeaders    = WC_Braspag_OAuth_API::get_headers_sop($settings);
        $auth3dsHeaders = WC_Braspag_Mpi_API::get_headers($settings);

        $this->assertSame('Basic ' . base64_encode('verify-id:verify-secret'), $verifyHeaders['Authorization']);
        $this->assertSame('Basic ' . base64_encode('sop-id:sop-secret'), $sopHeaders['Authorization']);
        $this->assertSame('Basic ' . base64_encode('auth3ds-id:auth3ds-secret'), $auth3dsHeaders['Authorization']);

        $this->assertNotSame($verifyHeaders['Authorization'], $sopHeaders['Authorization']);
        $this->assertNotSame($verifyHeaders['Authorization'], $auth3dsHeaders['Authorization']);
        $this->assertNotSame($sopHeaders['Authorization'], $auth3dsHeaders['Authorization']);
    }

    /**
     * @test
     * Trocar apenas a credencial de uma seção (ex.: SOP) não deve afetar o
     * header calculado para as outras duas — prova de que não há leitura
     * cruzada de chaves.
     */
    public function test_trocar_credencial_de_uma_secao_nao_afeta_as_outras(): void
    {
        update_option('woocommerce_braspag_settings', [
            'test_mode' => 'yes',
            'oauth_authentication_client_id'                => 'verify-id',
            'oauth_authentication_client_secret'             => 'verify-secret',
            'silentpost_oauth_client_id'                     => 'sop-id-antigo',
            'silentpost_oauth_client_secret'                 => 'sop-secret-antigo',
            'auth3ds20_oauth_authentication_client_id'       => 'auth3ds-id',
            'auth3ds20_oauth_authentication_client_secret'   => 'auth3ds-secret',
        ]);

        $settingsAntes = get_option('woocommerce_braspag_settings');
        $verifyAntes   = WC_Braspag_OAuth_API::get_headers($settingsAntes)['Authorization'];
        $auth3dsAntes  = WC_Braspag_Mpi_API::get_headers($settingsAntes)['Authorization'];

        // Só a credencial do SOP muda.
        $settings = $settingsAntes;
        $settings['silentpost_oauth_client_id']     = 'sop-id-novo';
        $settings['silentpost_oauth_client_secret'] = 'sop-secret-novo';
        update_option('woocommerce_braspag_settings', $settings);

        $settingsDepois = get_option('woocommerce_braspag_settings');

        $this->assertSame($verifyAntes, WC_Braspag_OAuth_API::get_headers($settingsDepois)['Authorization']);
        $this->assertSame($auth3dsAntes, WC_Braspag_Mpi_API::get_headers($settingsDepois)['Authorization']);
        $this->assertNotSame(
            WC_Braspag_OAuth_API::get_headers_sop($settingsAntes)['Authorization'],
            WC_Braspag_OAuth_API::get_headers_sop($settingsDepois)['Authorization']
        );
    }

    // ── Credenciais iguais entre si (cenário relatado pela Ana/Cielo) ───────

    /**
     * @test
     * Configurar as 3 seções com o MESMO client_id/secret (cenário real dos
     * prints trocados com a Cielo) não é um bug: cada método continua lendo
     * sua própria chave de configuração e funcionando normalmente — a
     * "duplicação" visual é apenas coincidência de valores, não uma falha de
     * separação no código.
     */
    public function test_credenciais_iguais_nas_3_secoes_nao_quebra_a_separacao(): void
    {
        update_option('woocommerce_braspag_settings', [
            'test_mode' => 'yes',
            'oauth_authentication_client_id'                => 'mesma-credencial-id',
            'oauth_authentication_client_secret'             => 'mesma-credencial-secret',
            'silentpost_oauth_client_id'                     => 'mesma-credencial-id',
            'silentpost_oauth_client_secret'                 => 'mesma-credencial-secret',
            'auth3ds20_oauth_authentication_client_id'       => 'mesma-credencial-id',
            'auth3ds20_oauth_authentication_client_secret'   => 'mesma-credencial-secret',
        ]);

        $settings = get_option('woocommerce_braspag_settings');

        $expectedAuth = 'Basic ' . base64_encode('mesma-credencial-id:mesma-credencial-secret');

        $this->assertSame($expectedAuth, WC_Braspag_OAuth_API::get_headers($settings)['Authorization']);
        $this->assertSame($expectedAuth, WC_Braspag_OAuth_API::get_headers_sop($settings)['Authorization']);
        $this->assertSame($expectedAuth, WC_Braspag_Mpi_API::get_headers($settings)['Authorization']);
    }
}
