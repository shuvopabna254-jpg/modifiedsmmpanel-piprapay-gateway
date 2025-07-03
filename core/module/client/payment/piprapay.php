<?php
if (!defined('PAYMENT')) {
    http_response_code(404);
    die();
}

$raw = file_get_contents("php://input");

if (!$raw) {
    header("Location: " . site_url("addfunds"));
    exit();
}

$apiKey =  trim($methodExtras['api_key']);

$data = json_decode($raw, true);

$headers = getallheaders();
$receivedKey = $headers['mh-piprapay-api-key'] ?? $headers['Mh-Piprapay-Api-Key'] ?? $headers['MH-PIPRAPAY-API-KEY'] ?? '';

if (empty($receivedKey) || $receivedKey !== $apiKey) {
    header("HTTP/1.1 401 Unauthorized");
    die("Invalid API Key");
}

$ppId      = $data['pp_id'] ?? null;

// Step 1: Verify the payment with PipraPay
$verifyPayload = json_encode(['pp_id' => $ppId]);

$ch = curl_init($methodExtras['api_url'] . '/api/verify-payments');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'mh-piprapay-api-key: ' . $apiKey,
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $verifyPayload);
$response = curl_exec($ch);

$data = json_decode($response, true);

if (isset($data['status']) && $data['status'] == 'completed') {
    
    
    $orderId = $data['metadata']['order_id'];
    $userId = $data['metadata']['client_id'];
    $paymentDetails = $conn->prepare("SELECT * FROM payments WHERE payment_extra=:orderId");
    $paymentDetails->execute([
        "orderId" => $orderId
    ]);
    
    $user = $conn->prepare("SELECT * FROM clients WHERE client_id=:id");
    $user->execute(array("id"=>$userId ));
    $user = $user->fetch(PDO::FETCH_ASSOC);

    if ($paymentDetails->rowCount()) {
        $paymentDetails = $paymentDetails->fetch(PDO::FETCH_ASSOC);

            $paidAmount = floatval($paymentDetails["payment_amount"]);
            if ($paymentFee > 0) {
                $fee = ($paidAmount * ($paymentFee / 100));
                $paidAmount -= $fee;
            }
            if ($paymentBonusStartAmount != 0 && $paidAmount > $paymentBonusStartAmount) {
                $bonus = $paidAmount * ($paymentBonus / 100);
                $paidAmount += $bonus;
            }

            $update = $conn->prepare('UPDATE payments SET 
                    client_balance=:balance,
                    payment_status=:status, 
                    payment_delivery=:delivery WHERE payment_id=:id');
            $update->execute([
                'balance' => $user["balance"],
                'status' => 3,
                'delivery' => 2,
                'id' => $paymentDetails['payment_id']
            ]);
            
            $balance = $conn->prepare('UPDATE clients SET balance = balance + :amount WHERE client_id = :id');
            $balance->execute([
                "amount" => $paidAmount,
                "id" => $user["client_id"]
            ]);

    } else {
        errorExit("Order ID not found.");
    }
}

header("Location: " . site_url("addfunds"));
exit();

http_response_code(405);
die();