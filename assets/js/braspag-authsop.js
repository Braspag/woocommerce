console.log('bpEnvironment: '+braspag_authsop_params.bpEnvironment);
console.log('bpSopToken: '+braspag_authsop_params.bpSopToken);
console.log('bpMerchantId: '+braspag_authsop_params.bpMerchantId);

function getAccessToken() {
    var merchantId = braspag_authsop_params.bpMerchantId;
    var environment = braspag_authsop_params.bpEnvironment;
    var bearerAccessToken = braspag_authsop_params.bpSopToken;
  
    var url = environment + "?merchantid=" + merchantId;
    var request = new XMLHttpRequest();
  
    if ('withCredentials' in request) {
      if (bearerAccessToken) {
        url = environment+ "/accesstoken";
        request.open("POST", url, true);
        request.setRequestHeader("MerchantId", merchantId);
        request.setRequestHeader("Authorization", bearerAccessToken);
      } else {
        console.log('sem beareToken');
      }
  
      request.onreadystatechange = function () {
        if (request.readyState == 4) {
          if (request.status == 201) {
            var jsonResponse = JSON.parse(request.responseText);
            console.log(jsonResponse.AccessToken + "Issued: " + jsonResponse.Issued + "ExpiresIn: " + jsonResponse.ExpiresIn);
          } else {

            console.log("HTTP " + request.status + ": erro ao obter o 'Access Token' do SOP (<b>" + url + "</b>).");7
          }
        }
      }
      request.setRequestHeader("Accept", "application/json");
      request.send();
    } else if (XDomainRequest) {
      request = new XDomainRequest();
      request.timeout = 3000;
      request.open('POST', url);
      request.onload = function () {
        var jsonResponse = JSON.parse(request.responseText);
        console.log(jsonResponse.AccessToken + "Issued: " + jsonResponse.Issued + "ExpiresIn: " + jsonResponse.ExpiresIn);
      }
      request.onerror = function () {
        console.log("Erro ao obter o 'Access Token' do SOP.");
      }
      request.send();
    }
}