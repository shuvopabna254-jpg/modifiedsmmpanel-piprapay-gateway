<?php
if (!defined('ADDFUNDS')) {
    http_response_code(404);
    die();
}

$apiKey = $methodExtras["api_key"];
$apiUrl = $methodExtras["api_url"];
$currency = $methodExtras["currency"];
$payeeName = $user["name"] ?: "User";
$payeeEmail = $user["email"] ?: "test@test.com";
$paymentURL = site_url("payment/" . $methodCallback);
$orderId = md5(RAND_STRING(5) . time());

$insert = $conn->prepare(
    "INSERT INTO payments SET
client_id=:client_id,
payment_amount=:amount,
payment_method=:method,
payment_mode=:mode,
payment_create_date=:date,
payment_ip=:ip,
payment_extra=:extra"
);

$insert->execute([
    "client_id" => $user["client_id"],
    "amount" => $paymentAmount,
    "method" => $methodId,
    "mode" => "Automatic",
    "date" => date("Y.m.d H:i:s"),
    "ip" => GetIP(),
    "extra" => $orderId
]);


$requestData = [
    'full_name'    => $payeeName,
    'email_mobile' => $payeeEmail,
    'amount'       => $paymentAmount,
    'metadata'     => [
        'order_id' => $orderId,
        'client_id' => $user["client_id"]
    ],
    'redirect_url' => $paymentURL,
    'cancel_url'   => site_url(""),
    'webhook_url'  => $paymentURL,
    'return_type'  => 'GET',
    'currency'     => $currency,
];

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => $apiUrl . '/api/create-charge',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode($requestData),
    CURLOPT_HTTPHEADER => [
        'mh-piprapay-api-key: ' . $apiKey,
        "accept: application/json",
        "content-type: application/json"
    ],
]);

$upresponse = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);
if ($err) {
    errorExit("cURL Error #:" . $err);
} else {
    $result = json_decode($upresponse, true);
    if (isset($result['status']) && isset($result['pp_url'])) {
        $paymentUrl = $result['pp_url'];
        $redirectForm .= '<form method="GET" action=" ' . $paymentUrl . '" name="piprapayCheckoutForm">';
        $redirectForm .= '</form>
        <script type="text/javascript">
        document.piprapayCheckoutForm.submit();
        </script>';
    } else {
        errorExit($result['message']);
    }
}

$response["success"] = true;
$response["message"] = "Your payment has been initiated and you will now be redirected to the payment gateway.";
$response["content"] = $redirectForm;