function bpmpi_authenticate() {
    BP.Mpi.authenticate();
}
function bpmpi_load() {
    BP.Mpi.load();
}
var BP = (function () {
    function e(e) {
        return document.getElementsByClassName(e).length > 0;
    }
    function r(r) {
        return e(r) ? document.getElementsByClassName(r)[0].value : null;
    }
    function n(r, n) {
        return e(r) ? document.getElementsByClassName(r)[0].value : n;
    }
    function i(e) {
        return e.replace("bpmpi_", "").replace(/\_/g, "");
    }
    function t(e) {
        return /\#/.test(e);
    }
    function o(e, r) {
        return e.replace(/\#/, r);
    }
    function a(e) {
        var r = i(e),
            n = r.split("#");
        return {
            enumerable: n[0],
            field: n[1]
        };
    }
    function s() {
        const e = window && window.screen ? window.screen.width : "",
            r = window && window.screen ? window.screen.height : "",
            n = window && window.screen ? window.screen.colorDepth : "",
            i = window && window.navigator ? window.navigator.userAgent : "",
            t = window && window.navigator && window.navigator.javaEnabled() ? "Y" : "N";
        var o = "";
        window && window.navigator && (o = window.navigator.language ? window.navigator.language : window.navigator.browserLanguage);
        const a = new Date().getTimezoneOffset(),
            s = { userAgent: i, screenWidth: e, screenHeight: r, colorDepth: n, timeZoneOffset: a, language: o, javaEnabled: t, javascriptEnabled: "Y" };
        return s;
    }
    function p(e) {
        return {
            type: P(e, "recurringtype"),
            validationIndicator: P(e, "recurringvalidationIndicator"),
            maximumAmount: P(e, "recurringmaximumAmount"),
            referenceNumber: P(e, "recurringreferenceNumber"),
            occurrence: P(e, "recurringoccurrence"),
            numberOfPayments: P(e, "recurringnumberOfPayments"),
            amountType: P(e, "recurringamountType"),
        };
    }
    function c() {
        for (var n = {}, c = 0; c < O.length; c++) {
            var u = O[c];
            if (t(u) === !1) {
                var d = i(u),
                    l = r(u);
                l && (n[d] = r(u));
            } else
                for (var m = 1, _ = o(u, m); e(_);) {
                    var b = a(u);
                    n[b.enumerable] || (n[b.enumerable] = []), (l = r(_)), n[b.enumerable][m - 1] || (n[b.enumerable][m - 1] = {}), (n[b.enumerable][m - 1][b.field] = l), m++, (_ = o(u, m));
                }
        }
        return (n.browserInfo = s()), (n.RecurringInfo = p(n)), n;
    }
    function u() {
        return "undefined" != typeof bpmpi_config ? bpmpi_config() : { Debug: !0, Environment: "PRD" };
    }
    function d() {
        return T.Environment || "PRD";
    }
    function l() {
        var e = d(),
            r = {
                TST: "https://songbirdstag.cardinalcommerce.com/edge/v1/songbird.js",
                SDB: "https://songbirdstag.cardinalcommerce.com/edge/v1/songbird.js",
                PRD: "https://songbird.cardinalcommerce.com/edge/v1/songbird.js"
            };
        return r[e];
    }
    function m() {
        return {
            orderNumber: r("bpmpi_ordernumber"),
            currency: r("bpmpi_currency"),
            amount: r("bpmpi_totalamount")
        };
    }
    function _(e) {
        var r = d(),
            n = {
                TST: "https://localhost:44351",
                SDB: "https://mpisandbox.braspag.com.br",
                PRD: "https://mpi.braspag.com.br"
            };
        return n[r] + e;
    }
    function b(e, r) {
        var n = document.getElementsByTagName("head")[0],
            i = document.createElement("script");
        (i.type = "text/javascript"), (i.src = e), (i.onreadystatechange = r), (i.onload = r), n.appendChild(i);
    }
    function g() {
        return "undefined" !== T.Debug ? T.Debug : !1;
    }
    function h() {
        g() && console.log.apply(null, arguments);
    }
    function f() {
        return g() ? "verbose" : "off";
    }
    function C(e, r) {
        (X = r),
            h("[MPI]", "Initializing..."),
            h("[MPI]", "Token =", e),
            h("[MPI]", "ReferenceId =", X),
            Cardinal.configure({
                timeout: "8000",
                maxRequestRetries: "10",
                logging: {
                    level: f()
                }
            }),
            Cardinal.setup("init", {
                jwt: e
            }),
            Cardinal.on("payments.setupComplete", function (e) {
                h("[MPI]", "Setup complete."), h("[MPI]", "SetupCompleteData =", e), D("onReady");
            }),
            Cardinal.on("payments.validated", function (e) {
                switch ((h("[MPI]", "Payment validated."), h("[MPI]", "ActionCode =", e.ActionCode), h("[MPI]", "Data =", e), e.ActionCode)) {
                    case "SUCCESS":
                    case "NOACTION":
                    case "FAILURE":
                        S(e.Payment.ProcessorTransactionId);
                        break;
                    case "ERROR":
                        (U.Number = e.ErrorNumber),
                            (U.Description = e.ErrorDescription),
                            e.Payment && e.Payment.ProcessorTransactionId
                                ? S(e.Payment.ProcessorTransactionId)
                                : D("onError", {
                                    Xid: null,
                                    Eci: null,
                                    ReturnCode:
                                        U.HasError() ?
                                            U.Number : "MPI901",
                                    ReturnMessage: U.HasError() ? U.Description : "Unexpected error",
                                    ReferenceId: null
                                });
                        break;
                    default:
                        (U.Number = e.ErrorNumber),
                            (U.Description = e.ErrorDescription),
                            "Success" === e.ErrorDescription && e.Payment && e.Payment.ProcessorTransactionId
                                ? S(e.Payment.ProcessorTransactionId)
                                : D("onError", {
                                    Xid: null,
                                    Eci: null,
                                    ReturnCode: U.HasError() ?
                                        U.Number : "MPI902",
                                    ReturnMessage: U.HasError() ? U.Description : "Unexpected authentication response",
                                    ReferenceId: null
                                });
                }
            });
    }
    function v(e, n, i) {
        var t = JSON.stringify(n),
            o = new XMLHttpRequest();
        (o.onreadystatechange = function () {
            if (4 === this.readyState)
                if (200 === this.status) {
                    var e = JSON.parse(o.responseText);
                    i(e, this);
                    if (e.Status == 'FAILED' && e.ReturnCode == '476') {
                        setErrorMessage(e.Status, e.ReturnCode);
                    }
                } else {
                    var r = N(this.response);
                    D("onError", {
                        Xid: null,
                        Eci: null,
                        ReturnCode: "MPI900",
                        ReturnMessage: "An error has occurred (" + this.status + ")" + r,
                        ReferenceId: null
                    });
                }
        }),
            o.open("POST", _(e)),
            o.setRequestHeader("Content-Type", "application/json"),
            o.setRequestHeader("Authorization", "Bearer " + r("bpmpi_accesstoken")),
            o.send(t);
    }
    function E() {
        var e = n("bpmpi_auth", "true");
        return h("[MPI]", "Authentication Enabled =", e), "false" === e ? (D("onDisabled"), !1) : !0;
    }
    function I() {
        if ((h("[MPI]", "Debug =", g()), h("[MPI]", "Enviroment =", d()), E())) {
            if (B) return void h("[MPI]", "Resources already loaded...");
            h("[MPI]", "Loading resources..."),
                (B = !0),
                b(l(), function () {
                    h("[MPI]", "Cardinal script loaded."),
                        v("/v2/3ds/init", m(), function (e) {
                            C(e.Token, e.ReferenceId);
                        });
                });
        }
    }
    function y() {
        if (E()) {
            if (!B) return void h("[MPI]", "Resources not loaded...");
            h("[MPI]", "Enrolling..."),
                Cardinal.trigger("accountNumber.update", r("bpmpi_cardnumber")),
                v("/v2/3ds/enroll", c(), function (e) {
                    h("[MPI]", "Enrollment result =", e), e.Version && (k = e.Version);
                    var r = e.Authentication;
                    switch (e.Status) {
                        case "ENROLLED":
                            A(e);
                            break;
                        case "VALIDATION_NEEDED":
                            S(e.AuthenticationTransactionId);
                            break;
                        case "AUTHENTICATION_CHECK_NEEDED":
                            w(r);
                            break;
                        case "NOT_ENROLLED":
                            D("onUnenrolled", {
                                Xid: r.Xid,
                                Eci: r.Eci,
                                Version: k,
                                ReferenceId: r.DirectoryServerTransactionId
                            });
                            if (e.VEResEnrolled == "U") {
                                setErrorMessage(e.Status, e.ReturnCode);
                            }
                            break;
                        case "FAILED":
                            D("onFailure", {
                                Xid: r.Xid,
                                Eci: r.Eci || r.EciRaw,
                                Version: k,
                                ReferenceId: r.DirectoryServerTransactionId
                            });
                            setErrorMessage(e.Status, e.ReturnCode);
                            break;
                        case "UNSUPPORTED_BRAND":
                            D("onUnsupportedBrand", {
                                Xid: null,
                                Eci: null,
                                ReturnCode: e.ReturnCode,
                                ReturnMessage: e.ReturnMessage,
                                ReferenceId: null
                            });
                            setErrorMessage(e.Status, e.ReturnCode);
                            break;
                        case "UNKNOWN":
                            D("unExpectedErrorOcurred", {
                                Xid: null,
                                Eci: null,
                                ReturnCode: e.ReturnCode,
                                ReturnMessage: e.ReturnMessage,
                                ReferenceId: null
                            });
                            setErrorMessage(e.Status, e.ReturnCode);
                            break;
                        default:
                            D("onError", {
                                Xid: null,
                                Eci: null,
                                ReturnCode: e.ReturnCode,
                                ReturnMessage: e.ReturnMessage,
                                ReferenceId: null
                            });
                            setErrorMessage(e.Status, e.ReturnCode);
                    }
                });
        }
    }
    function P(e, r) {
        return e[r] || null;
    }
    function R(e) {
        h("[MPI] Building order object...");
        var r = c(),
            n = {
                OrderDetails: {
                    TransactionId: e,
                    OrderNumber: r.ordernumber,
                    CurrencyCode: P(r, "currency"),
                    OrderChannel: r.transactionmode || "S"
                },
                Consumer: {
                    Account: {
                        AccountNumber: r.cardnumber,
                        ExpirationMonth: r.cardexpirationmonth,
                        ExpirationYear: r.cardexpirationyear
                    },
                    Email1: P(r, "shiptoemail"),
                    Email2: P(r, "billtoemail"),
                    ShippingAddress: {
                        FullName: null,
                        Address1: null,
                        Address2: null,
                        City: null,
                        State: null,
                        PostalCode: null,
                        CountryCode: null,
                        Phone1: null
                    },
                    BillingAddress: {
                        FullName: P(r, "billtocontactname"),
                        Address1: P(r, "billtostreet1"),
                        Address2: P(r, "billtostreet2"),
                        City: P(r, "billtocity"),
                        State: null === P(r, "billtostate") ? null : P(r, "billtostate").toUpperCase(),
                        PostalCode: P(r, "billtozipcode"),
                        CountryCode: P(r, "billtocountry"),
                        Phone1: P(r, "billtophonenumber"),
                    },
                },
                Cart: [],
            };
        if ("true" === r.shiptosameasbillto) {
            var i = n.Consumer.BillingAddress;
            (n.Consumer.ShippingAddress.FullName = i.FullName),
                (n.Consumer.ShippingAddress.Address1 = i.Address1),
                (n.Consumer.ShippingAddress.Address2 = i.Address2),
                (n.Consumer.ShippingAddress.City = i.City),
                (n.Consumer.ShippingAddress.State = i.State),
                (n.Consumer.ShippingAddress.PostalCode = i.PostalCode),
                (n.Consumer.ShippingAddress.Phone1 = i.Phone1),
                (n.Consumer.ShippingAddress.CountryCode = i.CountryCode);
        } else
            (n.Consumer.ShippingAddress.FullName = P(r, "shiptoaddressee")),
                (n.Consumer.ShippingAddress.Address1 = P(r, "shiptostreet1")),
                (n.Consumer.ShippingAddress.Address2 = P(r, "shiptostreet2")),
                (n.Consumer.ShippingAddress.City = P(r, "shiptocity")),
                (n.Consumer.ShippingAddress.State = null === P(r, "shiptostate") ? null : P(r, "shiptostate").toUpperCase()),
                (n.Consumer.ShippingAddress.PostalCode = P(r, "shiptozipcode")),
                (n.Consumer.ShippingAddress.Phone1 = P(r, "shiptophonenumber")),
                (n.Consumer.ShippingAddress.CountryCode = P(r, "shiptocountry"));
        if (r.cart)
            for (var t = 0; t < r.cart.length; t++) n.Cart.push({
                Name: P(r.cart[t], "name"),
                Description: P(r.cart[t], "description"),
                SKU: P(r.cart[t], "sku"),
                Quantity: P(r.cart[t], "quantity"),
                Price: P(r.cart[t], "unitprice")
            });
        return h("[MPI] Order object =", n), n;
    }
    function A(e) {
        var n = r("bpmpi_auth_suppresschallenge");
        if ((h("[MPI] Suppression enabled = " + n), "true" === n))
            return h("[MPI]", "Challenge supressed..."), void D("onChallengeSuppression", {
                Xid: null,
                Eci: null,
                ReturnCode: "MPI601",
                ReturnMessage: "Challenge suppressed",
                ReferenceId: null
            });
        h("[MPI]", "Showing challenge...");
        var i = {
            AcsUrl: e.AcsUrl,
            Payload: e.Pareq,
            TransactionId: e.AuthenticationTransactionId
        },
            t = R(e.AuthenticationTransactionId);
        h("[MPI] Continue object =", i), Cardinal.continue("cca", i, t);
    }
    function w(e) {
        switch ((h("[MPI]", "Authentication result =", e), e.Status)) {
            case "AUTHENTICATED":
                D("onSuccess", {
                    Cavv: e.Cavv,
                    Xid: e.Xid,
                    Eci: e.Eci,
                    Version: e.Version,
                    ReferenceId: e.DirectoryServerTransactionId
                });
                break;
            case "UNAVAILABLE":
                D("onUnenrolled", {
                    Cavv: e.Cavv,
                    Xid: e.Xid,
                    Eci: e.Eci,
                    Version: e.Version,
                    ReferenceId: e.DirectoryServerTransactionId
                });
                break;
            case "FAILED":
                D("onFailure", {
                    Xid: e.Xid,
                    Eci: e.Eci || e.EciRaw,
                    Version: e.Version,
                    ReferenceId: e.DirectoryServerTransactionId
                });
                setErrorMessage(e.ReturnMessage, e.ReturnCode);
                break;
            case "ERROR_OCCURRED":
                D("onError", {
                    Xid: e.Xid,
                    Eci: e.Eci || e.EciRaw,
                    ReturnCode: e.ReturnCode,
                    ReturnMessage: e.ReturnMessage,
                    ReferenceId: e.DirectoryServerTransactionId
                });
                break;
            default:
                D("onError", {
                    Xid: e.Xid,
                    Eci: e.Eci || e.EciRaw,
                    ReturnCode: U.HasError() ? U.Number : e.ReturnCode,
                    ReturnMessage: U.HasError() ? U.Description : e.ReturnMessage,
                    ReferenceId: e.DirectoryServerTransactionId
                });
        }
    }
    function S(e) {
        var r = c();
        (r.transactionId = e),
            h("[MPI]", "Validating..."),
            v("/v2/3ds/validate", r, function (e) {
                w(e);
            });
    }
    function M(e) {
        return "function" == typeof T[e];
    }
    function D(e, r) {
        h("[MPI]", "Notifying..."), h("[MPI]", "Event type =", e), h("[MPI]", "Event data =", r || "None"), M(e) && T[e](r);
    }
    function N(e) {
        var r;
        try {
            r = JSON.parse(e);
        } catch (n) {
            r = {};
        }
        return void 0 !== r.Message ? " - " + r.Message : "";
    }
    function setErrorMessage(strMessage, ErrorCode) {
        var msg = strMessage;
        switch (ErrorCode) {
            case "231":
                msg = 'Invalid card data, please check card data and try again!';
                break;
            case "476":
                msg = 'Customer cannot be authenticated';
                break;
            case 400:
                msg = 'Authentication failed, contact us';
                break;
            default:
                if (msg === "NOT_ENROLLED") {
                    msg = 'Invalid card data, please contact us!';
                } else {
                    msg = 'Authentication failed, contact us';
                }


                break;
        }
        window.scrollTo(0, 0);
        try {
            var wrapper =
                document.querySelector(".woocommerce-notices-wrapper") ||
                document.querySelector(".woocommerce-NoticeGroup") ||
                document.querySelector("form.checkout") ||
                document.body;

            // remove notices anteriores geradas pelo MPI (pra não empilhar lixo)
            var oldNotices = wrapper.querySelectorAll(".braspag-mpi-notice");
            for (var i = 0; i < oldNotices.length; i++) oldNotices[i].remove();

            var ul = document.createElement("ul");
            ul.className = "woocommerce-error braspag-mpi-notice";
            ul.setAttribute("role", "alert");

            var li = document.createElement("li");
            li.textContent = msg;
            ul.appendChild(li);

            // prepend quando possível
            if (wrapper.prepend) wrapper.prepend(ul);
            else wrapper.insertBefore(ul, wrapper.firstChild);

            if (wrapper.style) wrapper.style.display = "block";
        } catch (e2) {
            // último fallback: log
            try { console.error("[3DS]", msg); } catch (e3) { }
        }
    }
    var T = u(),
        O = [
            "bpmpi_transaction_mode",
            "bpmpi_merchant_url",
            "bpmpi_merchant_newcustomer",
            "bpmpi_ordernumber",
            "bpmpi_currency",
            "bpmpi_totalamount",
            "bpmpi_paymentmethod",
            "bpmpi_installments",
            "bpmpi_cardnumber",
            "bpmpi_cardexpirationmonth",
            "bpmpi_cardexpirationyear",
            "bpmpi_cardalias",
            "bpmpi_default_card",
            "bpmpi_cardaddeddate",
            "bpmpi_giftcard_amount",
            "bpmpi_giftcard_currency",
            "bpmpi_billto_customerid",
            "bpmpi_billto_contactname",
            "bpmpi_billto_email",
            "bpmpi_billto_street1",
            "bpmpi_billto_street2",
            "bpmpi_billto_city",
            "bpmpi_billto_state",
            "bpmpi_billto_zipcode",
            "bpmpi_billto_phonenumber",
            "bpmpi_billto_country",
            "bpmpi_shipto_sameasbillto",
            "bpmpi_shipto_addressee",
            "bpmpi_shipto_email",
            "bpmpi_shipto_street1",
            "bpmpi_shipto_street2",
            "bpmpi_shipto_city",
            "bpmpi_shipto_state",
            "bpmpi_shipto_zipcode",
            "bpmpi_shipto_shippingmethod",
            "bpmpi_shipto_phonenumber",
            "bpmpi_shipto_firstusagedate",
            "bpmpi_shipto_country",
            "bpmpi_device_ipaddress",
            "bpmpi_device_#_fingerprint",
            "bpmpi_device_#_provider",
            "bpmpi_device_channel",
            "bpmpi_cart_#_name",
            "bpmpi_cart_#_description",
            "bpmpi_cart_#_sku",
            "bpmpi_cart_#_quantity",
            "bpmpi_cart_#_unitprice",
            "bpmpi_order_recurrence",
            "bpmpi_order_productcode",
            "bpmpi_order_countlast24hours",
            "bpmpi_order_countlast6months",
            "bpmpi_order_countlast1year",
            "bpmpi_order_cardattemptslast24hours",
            "bpmpi_order_marketingoptin",
            "bpmpi_order_marketingsource",
            "bpmpi_useraccount_guest",
            "bpmpi_useraccount_createddate",
            "bpmpi_useraccount_changeddate",
            "bpmpi_useraccount_passwordchangeddate",
            "bpmpi_useraccount_authenticationmethod",
            "bpmpi_useraccount_authenticationprotocol",
            "bpmpi_useraccount_authenticationtimestamp",
            "bpmpi_airline_travelleg_#_carrier",
            "bpmpi_airline_travelleg_#_departuredate",
            "bpmpi_airline_travelleg_#_origin",
            "bpmpi_airline_travelleg_#_destination",
            "bpmpi_airline_passenger_#_name",
            "bpmpi_airline_passenger_#_ticketprice",
            "bpmpi_airline_numberofpassengers",
            "bpmpi_airline_billto_passportcountry",
            "bpmpi_airline_billto_passportnumber",
            "bpmpi_mdd1",
            "bpmpi_mdd2",
            "bpmpi_mdd3",
            "bpmpi_mdd4",
            "bpmpi_mdd5",
            "bpmpi_auth_notifyonly",
            "bpmpi_auth_suppresschallenge",
            "bpmpi_challenge_window_size",
            "bpmpi_recurring_enddate",
            "bpmpi_recurring_frequency",
            "bpmpi_recurring_originalpurchasedate",
            "bpmpi_recurring_type",
            "bpmpi_recurring_validationIndicator",
            "bpmpi_recurring_maximumAmount",
            "bpmpi_recurring_referenceNumber",
            "bpmpi_recurring_occurrence",
            "bpmpi_recurring_numberOfPayments",
            "bpmpi_recurring_amountType",
            "bpmpi_brand_establishment_code",
        ],
        X = null,
        k = null,
        U = {
            Number: null,
            Description: null,
            HasError: function () {
                return null !== this.Number;
            },
        },
        B = !1;
    return {
        Mpi: {
            load: function () {
                I();
            },
            authenticate: function () {
                y();
            },
        },
    };
})();
//bpmpi_load();
