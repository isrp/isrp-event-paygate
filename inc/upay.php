<?php

class PayGateUPayProcessor {
	
	private $api_username;
	private $testmode;
	private $api_key;
	private $mode = 'api-link';
	
	public function __construct($account, $api_key, $testmode) {
		$this->api_username = $account;
		$this->api_key = $api_key;
		$this->testmode = $testmode;
		$this->endpoint_url = 'https://app.upay.co.il/API6/clientsecure/redirectpage.php';
		
	}
	
	public function get_form($buttontext, $amount, $description, $order, $okurl, $failurl, $cancelurl) {
		switch ($this->mode) {
			case 'api-link':
				return $this->make_link($buttontext, $amount, $description, $order, $okurl, $failurl, $cancelurl);
			case 'form':
			default:
				return $this->make_form($buttontext, $amount, $description, $order, $okurl, $failurl, $cancelurl);
		}
	}
	
	private function make_form($buttontext, $amount, $description, $order, $okurl, $failurl, $cancelurl) {
		// Basically a least-modified copy of UPay's Woocommerce plugin 1.2.7's class-wc-gateway-upay::generate_upay_form()
		$data["email"] = $this->api_username;
		$data["emailnotify"] = 'oded@geek.co.il'; //$this->api_username; /* replace with BCC mail from options? */
		
		/** TBD support user's phone notification
		if (strpos($order->billing_phone, '05') === 0 || strpos($order->billing_phone, '+9725') === 0){
			$data["cellphonenotify"] = $order->billing_phone;
		}
		*/
		
		$data["comment"] = $description;
		$data["paymentdetails"] = $description;
		$data["livesystem"] = $this->testmode ? 0 : 1;
		$data["maxpayments"] = 1;
		$data["amount"] = $amount;
		$data["templatenumber"] = 15;
		
		$data["returnurl"] = $okurl;
		
		// copied from UPay's "create payment button" form builder
		$data["refername"] = 'UPAY';
		$data["lang"] = 'HE';
		$data["currency"] = 'NIS';
		
		foreach ($data as $key => $value) {
			$data_array[] = "<input type='hidden' name='$key' value='$value'/>";
		}
		
		$html_form = '<form name="paymentform" action="' . $this->endpoint_url . '" method="post" id="upay_payment_form">'
		. implode('', $data_array)
		. '<button class="paygate" type="submit">' . $buttontext . '</button>'
		// . '<script type="text/javascript">
		// jQuery(function(){                
		// jQuery("#upay_payment_form").submit(); 
		// });
		// </script>'
		. '</form>';
		
		return $html_form;
	}
	
	private function make_link($buttontext, $amount, $description, $order, $okurl, $failurl, $cancelurl) {
		// lifted from UPay's Woocommerce plugin 1.2.7's WC_Gateway_Upay_API_Handler
		$api = new UPayActionAPI($this->api_username, $this->api_key, $this->testmode);
		$url = $api->createPaymentLink("paygate:{$order}", $amount, $description, $okurl, $failurl);
		return '<form name="paymentform" action="' . $url .'" method="post">'
			. '<button class="paygate" type="submit">' . $buttontext . '</button>'
			. '</form>';
	}
	
	public function parse($query) {
		error_log("Paygate UPay response: {$query}");
		$result = wp_parse_args($query);
		if (array_key_exists('errormessage', $result)) {
			parse_str($query, $errparams);
			$text = mb_convert_encoding($errparams['errordescription'], 'WINDOWS-1252', 'UTF-8');
			wp_die(sprintf(
				esc_html__('Error processing payment - "%1$s". Please contact the site administrator' /*translators: error text*/,'isrp-event-paygate'),
						$text . ' (' . $result['errormessage'] . ')'));
		}
		
		/* Expecting a UPay response like this:
		providererrorcode=0&
		errordescription=SUCCESS&
		providererrordescription=SUCCESS&
		providerconfirmationnumber=040741&
		fourdigits=0012&
		depositamount=30&
		depositnetamount=30&
		amount=30&
		firstpayment=0&
		constantpayment=0&
		numberpayments=1&
		paymentdate=2026-10-20&
		commissionreduction=1&
		clearer=MAX&
		cardtype=VI&
		companytype=CAL&
		foreign=0&
		cardname=MAX%20VISA&
		cardownername=%D7%A2%D7%95%D7%93%D7%93%20%D7%90%D7%A8%D7%91%D7%9C&
		comment=&
		depositcashierid=190926794&
		transactionid=190926795&
		merchantnumber=0759055&
		identitynumber=032017410&
		expirydate=0329&
		application=&
		productdescription=1%20%D7%9B%D7%A8%D7%98%D7%99%D7%A1%D7%99%D7%9D%20%D7%9C-%20%D7%A2%D7%A8%D7%91%20%D7%9E%D7%A6%D7%92%D7%95%D7%AA%201&
		cellphonenotify=0547340014&
		email=treasurer@roleplay.org.il&
		emailnotify=oded@geek.co.il&
		actiondate=2026-10-20 */
		
		if (@$result['providererrorcode'] != 0) {
			wp_die(sprintf(
				esc_html__('Error processing payment - "%1$s". Please contact the site administrator' /*translators: error text*/,'isrp-event-paygate'),
						"{$result['errordescription']} ({$result['providererrorcode']})"));
		}
		
		$result['message'] = $result['errordescription'];
		// we currently don't know how to pass our internal transaction id through UPay
		// @list($prefix, $transaction_id) = explode(':',$result['comment']);
		
		error_log("PayGate: transaction successful: ". print_r($result, true));
		$name = urldecode($result['cardownername']);
		@list($firstname,$lastname) = explode(" ", $name,2);
		return [
			'processor' => 'upay',
			'amount' => $result['amount'],
			'transaction' => false, // no idea how to get that through UPay
			'testmode' => false, // we don't know yet how to access the test system
			'email' => $result['emailnotify'],
			'name' => $name,
			'firstname' => $firstname,
			'lastname' => $lastname,
			'phone' => $result['cellphonenotify'],
			'confirmation' => $result['transactionid'],
		];
	}
	
}

class UPayActionAPI {
	private $session;
	private $endpoint = "https://app.upay.co.il/API6/clientsecure/json.php";
	private $language = "HE";
	private $currency = "NIS";
	private $testmode = false;
	private $email;
	private $api_key;
	
	public function __construct($email, $apiKey, $testmode) {
		$this->email = $email;
		$this->api_key = $apiKey;
		$this->testmode = $testmode;
	}
	
	public function createPaymentLink($orderid, $amount, $description, $returnurl, $cancelurl = null, $email = null, $phone = null) {
		$res = $this->op($this->login(),
						 $this->checkout($orderid, $amount, $description, $returnurl, $cancelurl, $email, $phone));
		/*
		{ "success":true,
		  "header":{
		    "errorcode":"30012000","errormessage":"CREATEREDIRECTPAGE_OK",
		    "errordescription":"יצירת דף הפניה מחדש בוצע בהצלחה","provider":"UPAY",
		    "timestamp":"2026-10-19T23:48:45.637z"},
		  "result":{
		    "link":"https:\/\/app.upay.co.il\/API6\/s.php?m=UEtnNlcxbEVpbTJnTXJ1eGMvQ0pBZz09",
			"qrcode":"https:\/\/app.upay.co.il\/API6\/s.php?m=akZBUzlkZ3Jad2JkeFVUaWZoYzF6UT09",
			"sessionid":"UnREdU4xOXN3bXQ4WkVHRVFGejYveE9jUks2ZUhkNVU1TGVaVWt4Vk1QTHVScUNIcDY2V1lRNE44a1QvZTJINgequalequal"}
		}
		*/
		return $res[1]["link"];
	}
	
	private function startSession() {
		return $this->action("SESSION", "GETSESSION");
	}
	
	private function login() {
		return $this->action("CONNECTION", "LOGIN", [ "email" => $this->email, "key" => $this->api_key ]);
	}
	
	private function checkout($orderid, $amount, $description, $returnurl, $cancelurl = null, $email = null, $phone = null) {
		return $this->action("CASHIER", "CREATEREDIRECTPAGE", [
			"amount" => $amount,
			"paymentdetails" => $description,
			"comment" => $orderid,
			"numberpayments" => 1,
			"language" => $this->language,
			"currency" => $this->currency,
			"commissionreduction" => "1",
			"returnurl" => $returnurl,
			"returnapplicationurl" => $cancelurl,
			"encoding" => 1,
		]);
	}
	
	private function deposit($amount, $description, $returnurl, $email = null, $phone = null) {
		$transfer = [
			"email" => $this->email,
			"commissionreduction" => 0,
			"amount" => $amount,
			"currency" => $this->currency,
			"maxpayments" => 1,
			"paymentdate" => date("Y-m-d"),
			"productdescription" => $description,
			"returnurl" => $returnurl,
			"ipnurl" => $returnurl,
		];
		if ($phone && (strpos($phone, '05') === 0 || strpos($phone, '+9725') === 0))
			$transfer["cellphonenotify"] = str_replace('-', '', $phone);
		if ($email)
			$transfer["emailnotify"] = $email;
		
		return $this->action("CASHIER", "REDIRECTDEPOSITCREDITCARDTRANSFER", [
			"transfers" => [$transfer],
			"foreign" => "0",
			"key" => $this->api_key,
			"cardreader" => "0",
			"creditcardcompanytype" => "ISR",
			"creditcardtype" => "PR"
			]);
	}
	
	private function action($main, $minor, $parameters = null) {
		$msg = [
			"header" => ($this->session ? 
				[ "sessionid" => $this->session ] :
				[ "refername" => "UPAY", "livesystem" => ($this->testmode ? 0 : 1), "language" => $this->language ]
			),
			"request" => [
				"mainaction" => $main,
				"minoraction" => $minor,
				"encoding" => "json",
			]
		];
		if ($parameters)
			$msg["request"]["parameters"] = $parameters;
		return $msg;
	}
	
	private function op(...$msgs) {
		if (count($msgs) == 1)
			$res = $this->send(["msg" => json_encode($msg)]);
		else
			$res = $this->send(["msgs" => json_encode($msgs)]);
		error_log("UPay response: " . print_r($res, true));
		if(!$res)
			throw new Exception("Unexpected response from UPay API!");
		if (array_key_exists("results", $res))
			return $this->parseMulti($res["results"]);
		$this->handleError($res);
		return $res["result"];
	}
	
	private function parseMulti($res) {
		return array_map(function($result) {
			if (is_string($result))
				$result = json_decode($result, true);
			$this->handleError($result);
			return $result["result"];
		}, $res);
	}
	
	private function handleError($res) {
		if (@$res["success"] === false) {
			$errmsg = "{$res["header"]["errormessage"]} ({$res["header"]["errorcode"]})";
			if (array_key_exists("errordescription", $res["header"]))
				$errmsg .= ": {$res["header"]["errordescription"]}}";
			throw new Exception("Error from UPay API: " . $errmsg);
		}
		if (!@$res["success"])
			throw new Exception("Unparsable response from UPay API");
		if (!isset($res["result"]))
			throw new Exception("Missing result in UPay API response!");
		// handle session ID here, because I'm lazy
		if ($res["result"]["sessionid"])
			$this->session = $res["result"]["sessionid"];
	}
	
	private function send($data) {
		$curl = curl_init($this->endpoint);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl,CURLOPT_HEADER, 0 );
		curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl,CURLOPT_POST,1);
		$query = http_build_query($data);
		error_log("Paygate UPay: sending request " . print_r($query, true));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
		curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
		$res = curl_exec($curl);
		curl_close ($curl);
		return json_decode($res, true);
	}
}
