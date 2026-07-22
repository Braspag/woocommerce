(function () {
    const NS = 'braspag-wcbcf';
    const lastValues = {
        cpf: '',
        cnpj: '',
        cellphone: '',
    };

    function q(sel, root = document) { return root.querySelector(sel); }

    function onlyDigits(v) { return (v || '').replace(/\D+/g, ''); }

    function maskCPF(v) {
        v = onlyDigits(v).slice(0, 11);
        v = v.replace(/^(\d{3})(\d)/, '$1.$2');
        v = v.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
        v = v.replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d{1,2})$/, '$1.$2.$3-$4');
        return v;
    }

    function maskCNPJ(v) {
        v = onlyDigits(v).slice(0, 14);
        v = v.replace(/^(\d{2})(\d)/, '$1.$2');
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        v = v.replace(/^(\d{2})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3/$4');
        v = v.replace(/^(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d{1,2})$/, '$1.$2.$3/$4-$5');
        return v;
    }

    function maskPhone(v) {
        v = onlyDigits(v).slice(0, 13);
        if (v.length <= 10) {
            v = v.replace(/^(\d{2})(\d)/, '($1) $2');
            v = v.replace(/^(\(\d{2}\)\s\d{4})(\d)/, '$1-$2');
            return v;
        }
        v = v.replace(/^(\d{2})(\d)/, '($1) $2');
        v = v.replace(/^(\(\d{2}\)\s\d{5})(\d)/, '$1-$2');
        return v;
    }

    function findFieldInput(field) {
        // Busca estrita pelo namespace do bridge para coexistir com ECFB ativo.
        let el = q(`select[name="${NS}/${field}"], input[name="${NS}/${field}"]`);
        if (el) return el;

        el = q(`select[name*="${NS}/${field}"], input[name*="${NS}/${field}"]`);
        if (el) return el;

        el = q(`select[id*="${NS}-${field}"], input[id*="${NS}-${field}"]`);
        if (el) return el;

        el = q(`select[name*="${field}"][name*="${NS}"], input[name*="${field}"][name*="${NS}"]`);
        if (el) return el;

        return null;
    }

    function findBillingCompanyInput() {
        // Primeiro tenta pelo wrapper gerado pelo WooCommerce Blocks para o campo company.
        const byClass = q('.wc-block-components-address-form__company input, .wc-block-checkout__billing-fields .wc-block-components-address-form__company input');
        if (byClass) return byClass;

        const selectors = [
            '.wc-block-checkout__billing-fields input[name="company"]',
            '.wc-block-checkout__billing-fields input[autocomplete="organization"]',
            'input[name="company"]',
            'input[autocomplete="organization"]',
        ];

        for (const selector of selectors) {
            const inputs = document.querySelectorAll(selector);

            for (const input of inputs) {
                const shippingAncestor = input.closest('.wc-block-checkout__shipping-fields');
                if (shippingAncestor) {
                    continue;
                }

                return input;
            }
        }

        return null;
    }

    function findBillingCompanyWrapper() {
        // Tenta diretamente pelo wrapper gerado dinamicamente pelo WooCommerce Blocks.
        const byClass = q('.wc-block-checkout__billing-fields .wc-block-components-address-form__company') ||
                        q('.wc-block-components-address-form__company');
        if (byClass) return byClass;

        // Fallback genérico via input.
        const input = findBillingCompanyInput();
        return fieldWrapper(input);
    }

    function fieldWrapper(el) {
        if (!el) return null;

        return (
            el.closest('.wc-block-components-text-input') ||
            el.closest('.wc-block-components-select-control') ||
            el.closest('.wc-block-components-validation-error')?.parentElement ||
            el.parentElement
        );
    }

    // Usa o setter nativo do HTMLInputElement para contornar o value tracker
    // do React, garantindo que alterações programáticas de value sejam
    // detectadas pelo onChange do React (WooCommerce Blocks).
    const nativeInputSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
    const nativeSelectSetter = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value').set;

    function syncField(input) {
        if (!input) return;
        var val = input.value;
        var setter = input.tagName === 'SELECT' ? nativeSelectSetter : nativeInputSetter;
        if (setter) {
            setter.call(input, val);
        }
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setInputValue(input, value) {
        nativeInputSetter.call(input, value);
    }

    function setFieldLabel(wrapper, labelText) {
        if (!wrapper) return;

        const label = wrapper.querySelector('label');
        if (!label) return;

        // Guard: se já foi processado com o texto correto, pula.
        if (label.dataset.braspagLabelSet === labelText) {
            return;
        }

        // Remove todos os text nodes existentes (inclusive o \"(optional)\" embutido pelo WC Blocks).
        for (const node of Array.from(label.childNodes)) {
            if (node.nodeType === Node.TEXT_NODE) {
                node.remove();
            }
        }

        // Insere o novo texto como primeiro filho.
        const textNode = document.createTextNode(`${labelText} `);
        label.insertBefore(textNode, label.firstChild);

        // Marca como processado.
        label.dataset.braspagLabelSet = labelText;
    }

    function setRequiredIndicator(wrapper, required) {
        if (!wrapper) return;

        const label = wrapper.querySelector('label');
        if (!label) return;

        // Guard: se o estado é o mesmo que o indicador atual, pula.
        const hasIndicator = !!label.querySelector('abbr.required[data-braspag-company-required="1"]');
        if (hasIndicator === required) {
            return;
        }

        const optional = label.querySelector('.optional');
        if (optional) {
            optional.remove();
        }
    }

    function placeCompanyBelowCnpj(companyWrapper, cnpjWrapper) {
        if (!companyWrapper || !cnpjWrapper || !cnpjWrapper.parentNode) return;

        // Só pula se já está na posição correta E no mesmo pai (evita falso-positivo
        // quando eles estão em seções diferentes do DOM — billing vs order).
        if (cnpjWrapper.parentNode === companyWrapper.parentNode &&
            cnpjWrapper.nextElementSibling === companyWrapper) {
            return;
        }

        // Move o wrapper do company para o mesmo container dos campos do bridge,
        // logo após o CNPJ. Cruza deliberadamente a fronteira billing → order form
        // porque o React pode re-renderizar o billing form e resgatar o elemento;
        // o MutationObserver garante que esse movimento seja refeito a cada re-render.
        cnpjWrapper.parentNode.insertBefore(companyWrapper, cnpjWrapper.nextSibling);
    }

    function attachCPFMask(input) {
        if (!input || input.dataset.maskCpfBraspag === '1') return;
        input.dataset.maskCpfBraspag = '1';

        input.inputMode = 'numeric';
        input.pattern = '\\d{3}\\.\\d{3}\\.\\d{3}-\\d{2}';

        let lastKnownValue = input.value ? maskCPF(input.value) : '';
        if (lastKnownValue) {
            setInputValue(input, lastKnownValue);
            lastValues.cpf = lastKnownValue;
            syncField(input);
        } else if (!input.value && lastValues.cpf) {
            setInputValue(input, lastValues.cpf);
            lastKnownValue = lastValues.cpf;
            syncField(input);
        }

        input.addEventListener('input', () => {
            const cursorPos = input.selectionStart || 0;
            const oldLength = input.value.length;
            const masked = maskCPF(input.value);
            setInputValue(input, masked);

            if (masked) {
                lastKnownValue = masked;
                lastValues.cpf = masked;
                input.setAttribute('data-last-known-value', masked);
            }

            const newLength = input.value.length;
            const newCursorPos = Math.max(0, cursorPos + (newLength - oldLength));
            input.setSelectionRange(newCursorPos, newCursorPos);
        }, { passive: false });

        input.addEventListener('change', () => {
            if (input.value) {
                lastKnownValue = maskCPF(input.value);
                lastValues.cpf = lastKnownValue;
                input.setAttribute('data-last-known-value', lastKnownValue);
            }
        });

        input.addEventListener('blur', () => {
            if (input.value) {
                setInputValue(input, maskCPF(input.value));
                lastKnownValue = input.value;
                lastValues.cpf = lastKnownValue;
                input.setAttribute('data-last-known-value', lastKnownValue);
                syncField(input);
            }

            // Alguns scripts de terceiros limpam o campo no blur; restaura o último valor.
            setTimeout(() => {
                if (!input.value && lastKnownValue) {
                    setInputValue(input, lastKnownValue);
                    syncField(input);
                }

                const liveCpf = findFieldInput('cpf');
                if (liveCpf && !liveCpf.value && lastValues.cpf) {
                    setInputValue(liveCpf, lastValues.cpf);
                    syncField(liveCpf);
                }
            }, 40);

            setTimeout(() => {
                if (!input.value && lastKnownValue) {
                    setInputValue(input, lastKnownValue);
                    syncField(input);
                }

                const liveCpf = findFieldInput('cpf');
                if (liveCpf && !liveCpf.value && lastValues.cpf) {
                    setInputValue(liveCpf, lastValues.cpf);
                    syncField(liveCpf);
                }
            }, 180);
        });
    }

    function attachCNPJMask(input) {
        if (!input || input.dataset.maskCnpjBraspag === '1') return;
        input.dataset.maskCnpjBraspag = '1';

        input.inputMode = 'numeric';
        input.pattern = '\\d{2}\\.\\d{3}\\.\\d{3}\\/\\d{4}-\\d{2}';

        input.addEventListener('input', () => {
            const cursorPos = input.selectionStart || 0;
            const oldLength = input.value.length;
            setInputValue(input, maskCNPJ(input.value));
            const newLength = input.value.length;
            const newCursorPos = Math.max(0, cursorPos + (newLength - oldLength));
            input.setSelectionRange(newCursorPos, newCursorPos);
        }, { passive: false });

        input.addEventListener('blur', () => {
            if (input.value) {
                setInputValue(input, maskCNPJ(input.value));
                syncField(input);
            }
        });

        if (input.value) {
            setInputValue(input, maskCNPJ(input.value));
            lastValues.cnpj = input.value;
            syncField(input);
        }
    }

    function attachCellphoneMask(input) {
        if (!input || input.dataset.maskCellphoneBraspag === '1') return;
        input.dataset.maskCellphoneBraspag = '1';

        input.inputMode = 'tel';
        input.addEventListener('input', () => { setInputValue(input, maskPhone(input.value)); }, { passive: true });
        input.addEventListener('blur', () => {
            if (input.value) {
                setInputValue(input, maskPhone(input.value));
                lastValues.cellphone = input.value;
                syncField(input);
            }
        });

        if (input.value) {
            setInputValue(input, maskPhone(input.value));
            lastValues.cellphone = input.value;
            syncField(input);
        }
    }

    function applyPersonTypeUI() {
        const personType = findFieldInput('persontype');

        const cpf = findFieldInput('cpf');
        const cnpj = findFieldInput('cnpj');
        const rg = findFieldInput('rg');
        const ie = findFieldInput('ie');
        const cellphone = findFieldInput('cellphone');
        const company = findBillingCompanyInput();

        attachCPFMask(cpf);
        attachCNPJMask(cnpj);
        attachCellphoneMask(cellphone);

        const cpfW = fieldWrapper(cpf);
        const cnpjW = fieldWrapper(cnpj);
        const rgW = fieldWrapper(rg);
        const ieW = fieldWrapper(ie);
        // Usa wrapper dedicado para o company para capturar o wrapper gerado pelo WC Blocks.
        const companyW = findBillingCompanyWrapper();

        if (company) {
            company.setAttribute('aria-label', 'Razão Social');
        }

        setFieldLabel(companyW, 'Razão Social');

        function updateCompanyUI(isCompanyVisible) {
            // Busca novamente o wrapper a cada chamada: o React pode ter re-renderizado
            // o billing form e criado um novo nó para o campo company.
            const liveCompanyW = findBillingCompanyWrapper();
            const liveCompany = findBillingCompanyInput();

            // Guard: se o estado é o mesmo que o último aplicado, pula.
            if (liveCompanyW && liveCompanyW.dataset.braspagCompanyState === (isCompanyVisible ? '1' : '0')) {
                return;
            }

            if (liveCompanyW && cnpjW) {
                placeCompanyBelowCnpj(liveCompanyW, cnpjW);
            }

            // Reescreve a label toda vez para desfazer o "Company (optional)" do React.
            setFieldLabel(liveCompanyW, 'Razão Social');

            if (liveCompanyW) {
                liveCompanyW.style.display = isCompanyVisible ? '' : 'none';
                // Marca o estado aplicado para evitar reprocessamento.
                liveCompanyW.dataset.braspagCompanyState = isCompanyVisible ? '1' : '0';
            }

            if (liveCompany) {
                liveCompany.setAttribute('aria-label', 'Razão Social');
                liveCompany.required = isCompanyVisible;
                liveCompany.setAttribute('data-braspag-pj-company', isCompanyVisible ? '1' : '0');
            }

            setRequiredIndicator(liveCompanyW, isCompanyVisible);
        }

        function updateVisibility() {
            const v = personType ? personType.value : '';

            if (v === '1') {
                if (cpfW) cpfW.style.display = '';
                if (rgW) rgW.style.display = '';
                if (cnpjW) cnpjW.style.display = 'none';
                if (ieW) ieW.style.display = 'none';
                updateCompanyUI(false);
            } else if (v === '2') {
                if (cnpjW) cnpjW.style.display = '';
                if (ieW) ieW.style.display = '';
                if (cpfW) cpfW.style.display = 'none';
                if (rgW) rgW.style.display = 'none';
                updateCompanyUI(true);
            } else {
                if (cpfW) cpfW.style.display = 'none';
                if (rgW) rgW.style.display = 'none';
                if (cnpjW) cnpjW.style.display = 'none';
                if (ieW) ieW.style.display = 'none';
                updateCompanyUI(false);
            }
        }

        if (!personType) {
            updateCompanyUI(false);
            return;
        }

        if (personType.dataset.ptListener !== '1') {
            personType.dataset.ptListener = '1';
            personType.addEventListener('change', updateVisibility, { passive: true });
        }

        updateVisibility();
    }

    function preserveAddressFields() {
        setTimeout(() => {
            const numberField = findFieldInput('number') || q('input[name*="number"][name*="braspag-wcbcf"]');
            const neighborhoodField = findFieldInput('neighborhood') || q('input[name*="neighborhood"][name*="braspag-wcbcf"]');

            [numberField, neighborhoodField].forEach((field) => {
                if (field && !field.dataset.preserveListener) {
                    field.dataset.preserveListener = '1';

                    field.addEventListener('blur', () => {
                        if (field.value) {
                            syncField(field);
                        }
                    });
                }
            });
        }, 100);
    }

    function boot() {
        applyPersonTypeUI();

        const liveCpf = findFieldInput('cpf');
        if (liveCpf && !liveCpf.value && lastValues.cpf) {
            liveCpf.value = lastValues.cpf;
            syncField(liveCpf);
        }

        preserveAddressFields();
    }

    let t = null;
    const mo = new MutationObserver(() => {
        clearTimeout(t);
        t = setTimeout(boot, 120);
    });

    mo.observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('DOMContentLoaded', boot);
})();
