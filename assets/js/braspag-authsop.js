console.log('bpEnvironment: '+braspag_authsop_params.bpEnvironment);
console.log('bpOauthToken: '+braspag_authsop_params.bpOauthToken);
console.log('bpMerchantId: '+braspag_authsop_params.bpMerchantId);
console.log('bpMerchantIdSOP: '+braspag_authsop_params.bpMerchantIdSOP);

function getAccessToken() {
    var merchantId = braspag_authsop_params.bpMerchantId;
    var bpMerchantIdSOP = braspag_authsop_params.bpMerchantIdSOP;
    var environment = braspag_authsop_params.bpEnvironment;
    var bearerOauthToken = "Bearer " + braspag_authsop_params.bpOauthToken;
  
    var url = environment;
    var request = new XMLHttpRequest();
  
    if ('withCredentials' in request) {
      if (bearerOauthToken) {
        url = environment + "/accesstoken";
        request.open("POST", url, true);
        request.setRequestHeader("MerchantId", bpMerchantIdSOP);
        request.setRequestHeader("Authorization", bearerOauthToken);
      } else {
        console.log('sem Bearer Token');
      }
  
      request.onreadystatechange = function () {
        if (request.readyState == 4) {
          if (request.status == 201) {
            var jsonResponse = JSON.parse(request.responseText);
            console.log(jsonResponse.AccessToken + "Issued: " + jsonResponse.Issued + "ExpiresIn: " + jsonResponse.ExpiresIn);
          } else {
            console.log("HTTP " + request.status + ": erro ao obter o 'Access Token' do SOP (" + url + ").");
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
//getAccessToken();