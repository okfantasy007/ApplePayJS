<?php
require_once ('/your/path/to/applepay_includes/apple_pay_conf.php');
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
<style>
#applePay {  
	width: 80%;  
	height: 150px;  
	display: none;  
	border: 1px solid black;  
	box-sizing: border-box; 
	margin-left: auto;
    margin-right: auto;
    margin-top: 20px;
    background-color: lightblue;
    
} 
</style>
</head>
<body>
<div>
<button type="button" id="applePay">ApplePay test button</button>
<p style="display:none" id="got_notactive">ApplePay is possible on this browser, but not currently activated.</p>
<p style="display:none" id="notgot">ApplePay not available on this browser</p>
</div>
<script type="text/javascript">

if (window.ApplePaySession) {
   var merchantIdentifier = '<?=PRODUCTION_MERCHANTIDENTIFIER?>';
   var promise = ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier);
   promise.then(function (canMakePayments) {
	  if (canMakePayments) {
		 document.getElementById("applePay").style.display = "block";
		 logit('hi, I can do ApplePay');
	  } else {   
		 document.getElementById("got_notactive").style.display = "block";
		 logit('ApplePay is possible on this browser, but not currently activated.');
	  }
	}); 
} else {
	logit('ApplePay not available on this browser');
	document.getElementById("notgot").style.display = "block";
}

document.getElementById("applePay").onclick = function(evt) {

	 var runningAmount 	= 42;
	 var runningPP		= 0; getShippingCosts('domestic_std', true);
	 var runningTotal	= function() { return runningAmount + runningPP; }
	 var shippingOption = "";
	 
	 var subTotalDescr	= "Test Goodies";
	 
	 function getShippingOptions(shippingCountry){
	 logit('getShippingOptions: ' + shippingCountry );
		if( shippingCountry.toUpperCase() == "<?=PRODUCTION_COUNTRYCODE?>" ) {
			shippingOption = [{label: 'Standard Shipping', amount: getShippingCosts('domestic_std', true), detail: '3-5 days', identifier: 'domestic_std'},{label: 'Expedited Shipping', amount: getShippingCosts('domestic_exp', false), detail: '1-3 days', identifier: 'domestic_exp'}];
		} else {
			shippingOption = [{label: 'International Shipping', amount: getShippingCosts('international', true), detail: '5-10 days', identifier: 'international'}];
		}
	 
	 }
	 
	 function getShippingCosts(shippingIdentifier, updateRunningPP ){
	 
		var shippingCost = 0;
		
			 switch(shippingIdentifier) {
		case 'domestic_std':
			shippingCost = 3;
			break;
		case 'domestic_exp':
			shippingCost = 6;
			break;
		case 'international':
			shippingCost = 9;
			break;
		default:
			shippingCost = 11;
			}
		
		if (updateRunningPP == true) {
			runningPP = shippingCost;
		}
			
		logit('getShippingCosts: ' + shippingIdentifier + " - " + shippingCost +"|"+ runningPP );
		
		return shippingCost;
	 
	 }

	 var paymentRequest = {
	   currencyCode: '<?=PRODUCTION_CURRENCYCODE?>',
	   countryCode: '<?=PRODUCTION_COUNTRYCODE?>',
	   requiredShippingContactFields: ['postalAddress'],
	   //requiredShippingContactFields: ['email'],
	   lineItems: [{label: subTotalDescr, amount: runningAmount }, {label: 'P&P', amount: runningPP }],
	   total: {
		  label: '<?=PRODUCTION_DISPLAYNAME?>',
		  amount: runningTotal()
	   },
	   supportedNetworks: ['amex', 'masterCard', 'visa' ],
	   merchantCapabilities: [ 'supports3DS', 'supportsEMV', 'supportsCredit', 'supportsDebit' ]
	};
	
	var session = new ApplePaySession(1, paymentRequest);
	
	// Merchant Validation
	session.onvalidatemerchant = function (event) {
		logit(event);
		var promise = performValidation(event.validationURL);
		promise.then(function (merchantSession) {
			session.completeMerchantValidation(merchantSession);
		}); 
	}
	

	function performValidation(valURL) {
		return new Promise(function(resolve, reject) {
			var xhr = new XMLHttpRequest();
			xhr.onload = function() {
				var data = JSON.parse(this.responseText);
				logit(data);
				resolve(data);
			};
			xhr.onerror = reject;
			xhr.open('GET', 'apple_pay_comm.php?u=' + valURL);
			xhr.send();
		});
	}

	session.onshippingcontactselected = function(event) {
		logit('starting session.onshippingcontactselected');
		logit(event);
		
		getShippingOptions( event.shippingContact.countryCode );
		
		var status = ApplePaySession.STATUS_SUCCESS;
		var newShippingMethods = shippingOption;
		var newTotal = { type: 'final', label: '<?=PRODUCTION_DISPLAYNAME?>', amount: runningTotal() };
		var newLineItems =[{type: 'final',label: subTotalDescr, amount: runningAmount }, {type: 'final',label: 'P&P', amount: runningPP }];
		
		session.completeShippingContactSelection(status, newShippingMethods, newTotal, newLineItems );
		
		
	}
	
	session.onshippingmethodselected = function(event) {
		logit('starting session.onshippingmethodselected');
		logit(event);
		
		getShippingCosts( event.shippingMethod.identifier, true );
		
		var status = ApplePaySession.STATUS_SUCCESS;
		var newTotal = { type: 'final', label: '<?=PRODUCTION_DISPLAYNAME?>', amount: runningTotal() };
		var newLineItems =[{type: 'final',label: subTotalDescr, amount: runningAmount }, {type: 'final',label: 'P&P', amount: runningPP }];
		
		session.completeShippingMethodSelection(status, newTotal, newLineItems );
		
		
	}
	
	session.onpaymentmethodselected = function(event) {
		logit('starting session.onpaymentmethodselected');
		logit(event);
		
		var newTotal = { type: 'final', label: '<?=PRODUCTION_DISPLAYNAME?>', amount: runningTotal() };
		var newLineItems =[{type: 'final',label: subTotalDescr, amount: runningAmount }, {type: 'final',label: 'P&P', amount: runningPP }];
		
		session.completePaymentMethodSelection( newTotal, newLineItems );
		
		
	}
	
	session.onpaymentauthorized = function (event) {

		logit('starting session.onpaymentauthorized');
		logit(event);

		var promise = sendPaymentToken(event.payment.token);
		promise.then(function (success) {	
			var status;
			if (success)
				status = ApplePaySession.STATUS_SUCCESS;
			else
				status = ApplePaySession.STATUS_FAILURE;
			
			logit( "result of sendPaymentToken() function =  " + success );
			session.completePayment(status);
		});
	}

	function sendPaymentToken(paymentToken) {
		return new Promise(function(resolve, reject) {
			logit('starting function sendPaymentToken()');
			logit(paymentToken);
			
			logit("this is where you would pass the payment token to your third-party payment provider to use the token to charge the card. Only if your provider tells you the payment was successful should you return a resolve(true) here. Otherwise reject;");
			logit("defaulting to resolve(true) here, just to show what a successfully completed transaction flow looks like");
			resolve(true);
			//reject;
		});
	}
	
	session.oncancel = function(event) {
		logit('starting session.cancel');
		logit(event);
	}
	
	session.begin();

};
	
function logit( data ){
	var debug = <?=DEBUG?>;
	
	if( debug == true ){
		console.log(data);
	}	

};
</script>
</body>
</html>
