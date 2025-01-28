var VerifyCard = Class.create();

VerifyCard.prototype = {
    initialize: async function () {
        if (typeof braspag_verifycard_params == "undefined") {
            return false;
        }

        this.environment = braspag_verifycard_params.bpEnvironment;
        this.merchantId = braspag_verifycard_params.bpMerchantId;
        this.merchantKey = braspag_verifycard_params.bpMerchantKey;
        this.uuid = braspag_verifycard_params.uuid;
        this.testMode = braspag_verifycard_params.testMode;
        this.enable = braspag_verifycard_params.enable || false;

        // if (["yes", true].includes(braspag_authsop_params.verifyCard)) {
        //     this.enableVerifyCardCheck = true;
        // }

        // if (["yes", true].includes(braspag_authsop_params.binQuery)) {
        //     this.enableBinQueryCheck = true;
        // }

        // if (["yes", true].includes(braspag_authsop_params.tokenize)) {
        //     this.enableTokenizeCheck = true;
        // }

        // this.sop_enable = braspag_authsop_params.enable || false;

        this.apiUrl = braspag_verifycard_params.apiUrl;
    },
    processVerify: function (data, token = false) {
        try {
            if (this.testMode) {
                console.log('Modo Test Habilitado');
                this.logger();
            }

            console.log('Verificando cartão:', data);

            const payload = {
                Card: {
                    CardNumber: data.cardNumber,
                    Holder: data.holderName,
                    ExpirationDate: data.expirationDate,
                    SecurityCode: data.securityCode,
                    Brand: data.brand,
                    Type: data.cardType,
                },
                Provider: 'Simulado'
            };

            this.verify(payload);
        } catch (error) {
            console.error('Erro ao processar VerifyCard:', error);
        }
    },
    logger: function () {
        console.log('bpEnvironment: ' + braspag_verifycard_params.bpEnvironment);
        console.log('bpMerchantKey: ' + braspag_verifycard_params.bpMerchantKey);
        console.log('bpMerchantId: ' + braspag_verifycard_params.bpMerchantId);
        console.log('enable: ' + braspag_verifycard_params.enable);
        console.log('uuid: ' + braspag_verifycard_params.uuid);
        // console.log('verifyCard: ' + braspag_authsop_params.verifyCard);
        // console.log('binQuery: ' + braspag_authsop_params.binQuery);
        // console.log('tokenize: ' + braspag_authsop_params.tokenize);
        // console.log('testMode: ' + braspag_verifycard_params.testMode);
        console.log('apiUrl: ' + braspag_verifycard_params.apiUrl);
    },
    isVerifyEnabled: function () {
        return this.enable;
    },
    clearErrors() {
        const errorContainer = document.querySelector('.woocommerce-notices-wrapper');
        if (errorContainer) {
            errorContainer.innerHTML = ''; // Remove todas as mensagens de erro
            errorContainer.style.display = 'none'; // Esconde o contêiner de erros
        }
    },
    handleError(errorData) {
        const errorContainer = document.querySelector('.woocommerce-notices-wrapper');
        conso
        if (errorContainer) {
            const errorItem = document.createElement('div');
            errorItem.setAttribute('class', 'woocommerce-error');
            errorItem.innerHTML = `Erro ${errorData?.Code}: ${errorData?.Message || 'Erro desconhecido'}`;
            errorContainer.appendChild(errorItem);
            errorContainer.style.display = 'block';
        }
        alert('Verificação de cartão falhou. ' + errorData?.Message || 'Erro crítico no processamento do pagamento');
        throw new Error(errorData?.Message || 'Erro crítico no processamento do pagamento');
    },
    handleWarning(errorData) {
        const errorContainer = document.querySelector('.woocommerce-notices-wrapper');

        if (errorContainer) {
            const errorItem = document.createElement('div');
            errorItem.setAttribute('class', 'woocommerce-error');
            errorItem.innerHTML = `Erro ${errorData?.ProviderReturnCode}: ${errorData?.ProviderReturnMessage || 'Erro desconhecido'}`;
            errorContainer.appendChild(errorItem);
            errorContainer.style.display = 'block';
        }
        alert('Verificação de cartão falhou. ' + errorData?.Message || 'Erro crítico no processamento do pagamento');
        throw new Error(errorData?.Message || 'Erro crítico no processamento do pagamento');
    },
    verify: function (payload) {
        this.clearErrors();

        return fetch(this.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "MerchantId": this.merchantId,
                "MerchantKey": this.merchantKey,
                "RequestId": this.uuid,
            },
            body: JSON.stringify(payload),
        })
            .then(async response => {
                const data = await response.json();

                if (!response.ok) {
                    // Se for array, verificar pelos códigos 70 e BP900
                    if (Array.isArray(data) && data.some(err => err.Code === 70 || err.Code === 'BP900')) {
                        this.handleError(data[0]);
                    }
                    // Se for objeto, verificar diretamente os códigos
                    else if (data.Status != 1) {    
                        this.handleWarning(data);
                    } else {
                        // Lança um erro genérico caso não seja identificado
                        throw new Error('Erro desconhecido no payload');
                    }
                } else {
                    console.log('payload:', data);
                    if (data.Status != 1) {
                        this.handleWarning(data);
                    } else {
                        console.log('Resposta de sucesso:', data);
                        return data;
                    }
                }
            })
            .catch((error) => {
                console.error(error);
                throw error;
            });
    }
};

var verify = new VerifyCard();