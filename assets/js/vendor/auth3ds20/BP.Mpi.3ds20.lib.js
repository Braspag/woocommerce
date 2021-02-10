function bpmpi_authenticate() {
  BP.Mpi.authenticate()
}

function bpmpi_load() {
  BP.Mpi.load()
}
var BP = function() {
  function e(e) {
    return document.getElementsByClassName(e).length > 0
  }
  
  function r(r) {
    return e(r) ? document.getElementsByClassName(r)[0].value : null
  }
  
  function n(r, n) {
    return e(r) ? document.getElementsByClassName(r)[0].value : n
  }
  
  function i(e) {
    
    if (typeof e == "function") {
      return e;
    }
    
    return e.replace("bpmpi_", "").replace(/\_/g, "")
  }
  
  function t(e) {
    return /\#/.test(e)
  }
  
  function o(e, r) {
    return e.replace(/\#/, r)
  }
  
  function a(e) {
    var r = i(e),
      n = r.split("#");
    return {
      enumerable: n[0],
      field: n[1]
    }
  }
  
  function s() {
    var n = {};
    for (var s in N) {
      var p = N[s];
      if (t(p) === !1) {
        var c = i(p),
          u = r(p);
        u && (n[c] = r(p))
      } else
        for (var d = 1, l = o(p, d); e(l);) {
          var m = a(p);
          n[m.enumerable] || (n[m.enumerable] = []), u = r(l), n[m.enumerable][d - 1] || (n[m.enumerable][d - 1] = {}), n[m.enumerable][d - 1][m.field] = u, d++, l = o(p, d)
        }
    }
    return n
  }
  
  function p() {
    return "undefined" != typeof bpmpi_config ? bpmpi_config() : {
      Debug: !0,
      Environment: "PRD"
    }
  }
  
  function c() {
    return D.Environment || "PRD"
  }
  
  function u() {
    var e = c(),
      r = {
        TST: "https://songbirdstag.cardinalcommerce.com/edge/v1/songbird.js",
        SDB: "https://songbirdstag.cardinalcommerce.com/edge/v1/songbird.js",
        PRD: "https://songbird.cardinalcommerce.com/edge/v1/songbird.js"
      };
    return r[e]
  }
  
  function d() {
    return {
      orderNumber: r("bpmpi_ordernumber"),
      currency: r("bpmpi_currency"),
      amount: r("bpmpi_totalamount")
    }
  }
  
  function l(e) {
    var r = c(),
      n = {
        TST: "https://localhost:44351",
        SDB: "https://mpisandbox.braspag.com.br",
        PRD: "https://mpi.braspag.com.br"
      };
    return n[r] + e
  }
  
  function m(e, r) {
    var n = document.getElementsByTagName("head")[0],
      i = document.createElement("script");
    i.type = "text/javascript", i.src = e, i.onreadystatechange = r, i.onload = r, n.appendChild(i)
  }
  
  function _() {
    return "undefined" !== D.Debug ? D.Debug : !1
  }
  
  function b() {
    _() && console.log.apply(null, arguments)
  }
  
  function h() {
    return _() ? "verbose" : "off"
  }
  
  function g(e, r) {
    T = r, b("[MPI]", "Initializing..."), b("[MPI]", "Token =", e), b("[MPI]", "ReferenceId =", T), Cardinal.configure({
      timeout: "8000",
      maxRequestRetries: "10",
      logging: {
        level: h()
      }
    }), Cardinal.setup("init", {
      jwt: e
    }), Cardinal.on("payments.setupComplete", function(e) {
      b("[MPI]", "Setup complete."), b("[MPI]", "SetupCompleteData =", e), M("onReady")
    }), Cardinal.on("payments.validated", function(e) {
      switch (b("[MPI]", "Payment validated."), b("[MPI]", "ActionCode =", e.ActionCode), b("[MPI]", "Data =", e), e.ActionCode) {
        case "SUCCESS":
        case "NOACTION":
        case "FAILURE":
          A(e.Payment.ProcessorTransactionId);
          break;
        case "ERROR":
          k.Number = e.ErrorNumber, k.Description = e.ErrorDescription, e.Payment && e.Payment.ProcessorTransactionId ? A(e.Payment.ProcessorTransactionId) : M("onError", {
            Xid: null,
            Eci: null,
            ReturnCode: k.HasError() ? k.Number : "MPI901",
            ReturnMessage: k.HasError() ? k.Description : "Unexpected error",
            ReferenceId: null
          });
          break;
        default:
          k.Number = e.ErrorNumber, k.Description = e.ErrorDescription, "Success" === e.ErrorDescription && e.Payment && e.Payment.ProcessorTransactionId ? A(e.Payment.ProcessorTransactionId) : M("onError", {
            Xid: null,
            Eci: null,
            ReturnCode: k.HasError() ? k.Number : "MPI902",
            ReturnMessage: k.HasError() ? k.Description : "Unexpected authentication response",
            ReferenceId: null
          })
      }
    })
  }
  
  function f(e, n, i) {
    var t = JSON.stringify(n),
      o = new XMLHttpRequest;
    o.onreadystatechange = function() {
      if (4 === this.readyState)
        if (200 === this.status) {
          var e = JSON.parse(o.responseText);
          i(e, this)
        } else M("onError", {
          Xid: null,
          Eci: null,
          ReturnCode: "MPI900",
          ReturnMessage: "An error has occurred (" + this.status + ")",
          ReferenceId: null
        })
    }, o.open("POST", l(e)), o.setRequestHeader("Content-Type", "application/json"), o.setRequestHeader("Authorization", "Bearer " + r("bpmpi_accesstoken")), o.send(t)
  }
  
  function C() {
    var e = n("bpmpi_auth", "true");
    return b("[MPI]", "Authentication Enabled =", e), "false" === e ? (M("onDisabled"), !1) : !0
  }
  
  function E() {
    if (b("[MPI]", "Debug =", _()), b("[MPI]", "Enviroment =", c()), C()) {
      if (O) return void b("[MPI]", "Resources already loaded...");
      b("[MPI]", "Loading resources..."), O = !0, m(u(), function() {
        b("[MPI]", "Cardinal script loaded."), f("/v2/3ds/init", d(), function(e) {
          g(e.Token, e.ReferenceId)
        })
      })
    }
  }
  
  function I() {
    if (C()) {
      if (!O) return void b("[MPI]", "Resources not loaded...");
      b("[MPI]", "Enrolling..."), Cardinal.trigger("accountNumber.update", r("bpmpi_cardnumber")), f("/v2/3ds/enroll", s(), function(e) {
        b("[MPI]", "Enrollment result =", e), e.Version && (X = e.Version[0]);
        var r = e.Authentication;
        switch (e.Status) {
          case "ENROLLED":
            R(e);
            break;
          case "VALIDATION_NEEDED":
            A(e.AuthenticationTransactionId);
            break;
          case "AUTHENTICATION_CHECK_NEEDED":
            v(r);
            break;
          case "NOT_ENROLLED":
            M("onUnenrolled", {
              Xid: r.Xid,
              Eci: r.Eci,
              Version: X,
              ReferenceId: r.DirectoryServerTransactionId
            });
            break;
          case "FAILED":
            M("onFailure", {
              Xid: r.Xid,
              Eci: r.Eci || r.EciRaw,
              Version: X,
              ReferenceId: r.DirectoryServerTransactionId
            });
            break;
          case "UNSUPPORTED_BRAND":
            M("onUnsupportedBrand", {
              Xid: null,
              Eci: null,
              ReturnCode: e.ReturnCode,
              ReturnMessage: e.ReturnMessage,
              ReferenceId: null
            });
            break;
          default:
            M("onError", {
              Xid: null,
              Eci: null,
              ReturnCode: e.ReturnCode,
              ReturnMessage: e.ReturnMessage,
              ReferenceId: null
            })
        }
      })
    }
  }
  
  function y(e, r) {
    return e[r] || null
  }
  
  function P(e) {
    b("[MPI] Building order object...");
    var r = s(),
      n = {
        OrderDetails: {
          TransactionId: e,
          OrderNumber: r.ordernumber,
          CurrencyCode: y(r, "currency"),
          OrderChannel: r.transactionmode || "S"
        },
        Consumer: {
          Account: {
            AccountNumber: r.cardnumber,
            ExpirationMonth: r.cardexpirationmonth,
            ExpirationYear: r.cardexpirationyear
          },
          Email1: y(r, "shiptoemail"),
          Email2: y(r, "billtoemail"),
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
            FullName: y(r, "billtocontactname"),
            Address1: y(r, "billtostreet1"),
            Address2: y(r, "billtostreet2"),
            City: y(r, "billtocity"),
            State: null === y(r, "billtostate") ? null : y(r, "billtostate").toUpperCase(),
            PostalCode: y(r, "billtozipcode"),
            CountryCode: y(r, "billtocountry"),
            Phone1: y(r, "billtophonenumber")
          }
        },
        Cart: []
      };
    if ("true" === r.shiptosameasbillto) {
      var i = n.Consumer.BillingAddress;
      n.Consumer.ShippingAddress.FullName = i.FullName, n.Consumer.ShippingAddress.Address1 = i.Address1, n.Consumer.ShippingAddress.Address2 = i.Address2, n.Consumer.ShippingAddress.City = i.City, n.Consumer.ShippingAddress.State = i.State, n.Consumer.ShippingAddress.PostalCode = i.PostalCode, n.Consumer.ShippingAddress.Phone1 = i.Phone1, n.Consumer.ShippingAddress.CountryCode = i.CountryCode
    } else n.Consumer.ShippingAddress.FullName = y(r, "shiptoaddressee"), n.Consumer.ShippingAddress.Address1 = y(r, "shiptostreet1"), n.Consumer.ShippingAddress.Address2 = y(r, "shiptostreet2"), n.Consumer.ShippingAddress.City = y(r, "shiptocity"), n.Consumer.ShippingAddress.State = null === y(r, "shiptostate") ? null : y(r, "shiptostate").toUpperCase(), n.Consumer.ShippingAddress.PostalCode = y(r, "shiptozipcode"), n.Consumer.ShippingAddress.Phone1 = y(r, "shiptophonenumber"), n.Consumer.ShippingAddress.CountryCode = y(r, "shiptocountry");
    if (r.cart)
      for (var t in r.cart) n.Cart.push({
        Name: y(r.cart[t], "name"),
        Description: y(r.cart[t], "description"),
        SKU: y(r.cart[t], "sku"),
        Quantity: y(r.cart[t], "quantity"),
        Price: y(r.cart[t], "unitprice")
      });
    return b("[MPI] Order object =", n), n
  }
  
  function R(e) {
    var n = r("bpmpi_auth_suppresschallenge");
    if (b("[MPI] Suppression enabled = " + n), "true" === n) return b("[MPI]", "Challenge supressed..."), void M("onChallengeSuppression", {
      Xid: null,
      Eci: null,
      ReturnCode: "MPI601",
      ReturnMessage: "Challenge suppressed",
      ReferenceId: null
    });
    b("[MPI]", "Showing challenge...");
    var i = {
        AcsUrl: e.AcsUrl,
        Payload: e.Pareq,
        TransactionId: e.AuthenticationTransactionId
      },
      t = P(e.AuthenticationTransactionId);
    b("[MPI] Continue object =", i), Cardinal.continue("cca", i, t)
  }
  
  function v(e) {
    switch (b("[MPI]", "Authentication result =", e), e.Status) {
      case "AUTHENTICATED":
        M("onSuccess", {
          Cavv: e.Cavv,
          Xid: e.Xid,
          Eci: e.Eci,
          Version: e.Version[0],
          ReferenceId: e.DirectoryServerTransactionId
        });
        break;
      case "UNAVAILABLE":
        M("onUnenrolled", {
          Xid: e.Xid,
          Eci: e.Eci,
          Version: e.Version[0],
          ReferenceId: e.DirectoryServerTransactionId
        });
        break;
      case "FAILED":
        M("onFailure", {
          Xid: e.Xid,
          Eci: e.Eci || e.EciRaw,
          Version: e.Version[0],
          ReferenceId: e.DirectoryServerTransactionId
        });
        break;
      case "ERROR_OCCURRED":
        M("onError", {
          Xid: e.Xid,
          Eci: e.Eci || e.EciRaw,
          ReturnCode: e.ReturnCode,
          ReturnMessage: e.ReturnMessage,
          ReferenceId: e.DirectoryServerTransactionId
        });
        break;
      default:
        M("onError", {
          Xid: e.Xid,
          Eci: e.Eci || e.EciRaw,
          ReturnCode: k.HasError() ? k.Number : e.ReturnCode,
          ReturnMessage: k.HasError() ? k.Description : e.ReturnMessage,
          ReferenceId: e.DirectoryServerTransactionId
        })
    }
  }
  
  function A(e) {
    var r = s();
    r.transactionId = e, b("[MPI]", "Validating..."), f("/v2/3ds/validate", r, function(e) {
      v(e)
    })
  }
  
  function S(e) {
    return "function" == typeof D[e]
  }
  
  function M(e, r) {
    b("[MPI]", "Notifying..."), b("[MPI]", "Event type =", e), b("[MPI]", "Event data =", r || "None"), S(e) && D[e](r)
  }
  var D = p(),
    N = ["bpmpi_transaction_mode", "bpmpi_merchant_url", "bpmpi_merchant_newcustomer", "bpmpi_ordernumber", "bpmpi_currency", "bpmpi_totalamount", "bpmpi_paymentmethod", "bpmpi_installments", "bpmpi_cardnumber", "bpmpi_cardexpirationmonth", "bpmpi_cardexpirationyear", "bpmpi_cardalias", "bpmpi_default_card", "bpmpi_cardaddeddate", "bpmpi_giftcard_amount", "bpmpi_giftcard_currency", "bpmpi_billto_customerid", "bpmpi_billto_contactname", "bpmpi_billto_email", "bpmpi_billto_street1", "bpmpi_billto_street2", "bpmpi_billto_city", "bpmpi_billto_state", "bpmpi_billto_zipcode", "bpmpi_billto_phonenumber", "bpmpi_billto_country", "bpmpi_shipto_sameasbillto", "bpmpi_shipto_addressee", "bpmpi_shipto_email", "bpmpi_shipto_street1", "bpmpi_shipto_street2", "bpmpi_shipto_city", "bpmpi_shipto_state", "bpmpi_shipto_zipcode", "bpmpi_shipto_shippingmethod", "bpmpi_shipto_phonenumber", "bpmpi_shipto_firstusagedate", "bpmpi_shipto_country", "bpmpi_device_ipaddress", "bpmpi_device_#_fingerprint", "bpmpi_device_#_provider", "bpmpi_cart_#_name", "bpmpi_cart_#_description", "bpmpi_cart_#_sku", "bpmpi_cart_#_quantity", "bpmpi_cart_#_unitprice", "bpmpi_order_recurrence", "bpmpi_order_productcode", "bpmpi_order_countlast24hours", "bpmpi_order_countlast6months", "bpmpi_order_countlast1year", "bpmpi_order_cardattemptslast24hours", "bpmpi_order_marketingoptin", "bpmpi_order_marketingsource", "bpmpi_useraccount_guest", "bpmpi_useraccount_createddate", "bpmpi_useraccount_changeddate", "bpmpi_useraccount_passwordchangeddate", "bpmpi_useraccount_authenticationmethod", "bpmpi_useraccount_authenticationprotocol", "bpmpi_useraccount_authenticationtimestamp", "bpmpi_airline_travelleg_#_carrier", "bpmpi_airline_travelleg_#_departuredate", "bpmpi_airline_travelleg_#_origin", "bpmpi_airline_travelleg_#_destination", "bpmpi_airline_passenger_#_name", "bpmpi_airline_passenger_#_ticketprice", "bpmpi_airline_numberofpassengers", "bpmpi_airline_billto_passportcountry", "bpmpi_airline_billto_passportnumber", "bpmpi_mdd1", "bpmpi_mdd2", "bpmpi_mdd3", "bpmpi_mdd4", "bpmpi_mdd5", "bpmpi_auth_notifyonly", "bpmpi_auth_suppresschallenge", "bpmpi_recurring_enddate", "bpmpi_recurring_frequency", "bpmpi_recurring_originalpurchasedate"],
    T = null,
    X = null,
    k = {
      Number: null,
      Description: null,
      HasError: function() {
        return null !== this.Number
      }
    },
    O = !1;
  return {
    Mpi: {
      load: function() {
        E()
      },
      authenticate: function() {
        I()
      }
    }
  }
}();
bpmpi_load();