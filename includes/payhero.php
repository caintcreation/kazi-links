<?php
// includes/payhero.php - PayHero API client for M-Pesa STK Push requests

require_once __DIR__ . '/config.php';

function payHeroConfigured(): bool {
    return defined('PAYHERO_API_USERNAME') && !empty(PAYHERO_API_USERNAME)
        && defined('PAYHERO_API_PASSWORD') && !empty(PAYHERO_API_PASSWORD)
        && defined('PAYHERO_CHANNEL_ID') && !empty(PAYHERO_CHANNEL_ID);
}

function payHeroRequest(string $method, string $path, ?array $payload = null): array {
    if (!payHeroConfigured()) {
        return ['success' => false, 'message' => 'PayHero payment credentials are not configured.'];
    }

    $url = rtrim(PAYHERO_API_BASE_URL, '/') . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    if ($ch === false) {
        return ['success' => false, 'message' => 'Could not initialize the payment service.'];
    }

    $authHeader = (defined('PAYHERO_BASIC_AUTH') && !empty(PAYHERO_BASIC_AUTH))
        ? PAYHERO_BASIC_AUTH
        : 'Basic ' . base64_encode(PAYHERO_API_USERNAME . ':' . PAYHERO_API_PASSWORD);

    $headers = [
        'Authorization: ' . $authHeader,
        'Accept: application/json',
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_SSL_VERIFYPEER => true,
    ];

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_POSTFIELDS] = json_encode($payload);
    }

    $options[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $error !== '') {
        error_log('PayHero request failed: ' . $error);
        return ['success' => false, 'message' => 'Payment service connection failed: ' . ($error ?: 'Network timeout')];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        error_log('PayHero returned invalid JSON: HTTP ' . $status . ' body: ' . $body);
        return ['success' => false, 'message' => 'Payment service returned an invalid response.'];
    }

    if ($status < 200 || $status >= 300) {
        error_log('PayHero rejected request: HTTP ' . $status . ' response ' . $body);
        $msg = $decoded['message'] ?? $decoded['error'] ?? 'Payment request was rejected by PayHero.';
        return ['success' => false, 'message' => $msg, 'data' => $decoded];
    }

    return ['success' => true, 'data' => $decoded];
}

/**
 * Initiate M-Pesa STK Push via PayHero
 */
function payHeroStartStkPush(float $amount, string $phone, string $reference, string $callbackUrl): array {
    // Format phone number to standard Kenyan format (e.g. 0712345678 or 254712345678)
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($cleanPhone, '254') && strlen($cleanPhone) === 12) {
        $formattedPhone = '0' . substr($cleanPhone, 3);
    } elseif (str_starts_with($cleanPhone, '0') && strlen($cleanPhone) === 10) {
        $formattedPhone = $cleanPhone;
    } elseif (strlen($cleanPhone) === 9) {
        $formattedPhone = '0' . $cleanPhone;
    } else {
        $formattedPhone = $cleanPhone;
    }

    $payload = [
        'amount' => (int)round($amount), // PayHero accepts round amounts
        'phone_number' => $formattedPhone,
        'channel_id' => (int)PAYHERO_CHANNEL_ID,
        'provider' => 'm-pesa',
        'external_reference' => $reference,
        'callback_url' => $callbackUrl,
    ];

    return payHeroRequest('POST', 'payments', $payload);
}

function payHeroFindValue(array $payload, array $keys) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $payload)) {
            return $payload[$key];
        }
    }
    foreach ($payload as $value) {
        if (is_array($value)) {
            $found = payHeroFindValue($value, $keys);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}

/**
 * Parse detailed failure reason and message from callback payload or API response
 */
function payHeroParseFailureDetails(?string $callbackPayload): array {
    if (empty($callbackPayload)) {
        return [
            'reason' => 'unknown',
            'title' => 'Payment Incomplete',
            'message' => 'No payment was completed on your phone. If you did not enter your PIN or cancelled the prompt, please click Try Again.'
        ];
    }

    $data = json_decode($callbackPayload, true);
    if (!is_array($data)) {
        return [
            'reason' => 'unknown',
            'title' => 'Payment Incomplete',
            'message' => 'Payment could not be confirmed. Please click Try Again.'
        ];
    }

    $rawDesc = (string)(payHeroFindValue($data, ['result_desc', 'ResultDesc', 'description', 'message', 'error', 'errorMessage', 'status_message']) ?? '');
    $resultCode = payHeroFindValue($data, ['result_code', 'ResultCode', 'response_code']);
    $status = strtolower((string)(payHeroFindValue($data, ['status', 'payment_status', 'transaction_status']) ?? ''));
    $descLower = strtolower($rawDesc);

    // 1. User Cancelled Prompt on Phone
    if ((string)$resultCode === '1032' 
        || str_contains($descLower, 'cancel') 
        || str_contains($status, 'cancel')) {
        return [
            'reason' => 'cancelled',
            'title' => 'Payment Cancelled on Phone',
            'message' => 'You cancelled the M-Pesa prompt on your phone. If this was a mistake, click Try Again and enter your M-Pesa PIN to unlock the level.'
        ];
    }

    // 2. Incorrect PIN Entered
    if ((string)$resultCode === '2001' 
        || str_contains($descLower, 'wrong pin') 
        || str_contains($descLower, 'incorrect pin') 
        || str_contains($descLower, 'initiator information')) {
        return [
            'reason' => 'wrong_pin',
            'title' => 'Incorrect M-Pesa PIN',
            'message' => 'The M-Pesa PIN entered was incorrect. Please click Try Again and enter your correct M-Pesa PIN.'
        ];
    }

    // 3. Insufficient Funds
    if ((string)$resultCode === '1' 
        || str_contains($descLower, 'insufficient') 
        || str_contains($descLower, 'balance')) {
        return [
            'reason' => 'insufficient_funds',
            'title' => 'Insufficient M-Pesa Balance',
            'message' => 'Your M-Pesa balance is insufficient for this level unlock. Please top up your M-Pesa account and click Try Again.'
        ];
    }

    // 4. Prompt Timed Out
    if ((string)$resultCode === '1037' 
        || str_contains($descLower, 'timeout') 
        || str_contains($descLower, 'cannot be reached')) {
        return [
            'reason' => 'timeout',
            'title' => 'M-Pesa Prompt Timed Out',
            'message' => 'The payment prompt timed out before the PIN was entered. Please ensure your phone screen is unlocked and try again.'
        ];
    }

    // 5. General failure
    $customMsg = !empty($rawDesc) ? $rawDesc : 'No payment was detected. If you did not enter your PIN or cancelled the prompt, please click Try Again.';
    return [
        'reason' => 'failed',
        'title' => 'Payment Failed',
        'message' => $customMsg
    ];
}

/**
 * Direct PayHero API verification: Checks if an external reference has been paid.
 * Matches by exact reference or by phone + amount in recent transactions as a fallback.
 * Ensures instant payment confirmation even if incoming webhooks are delayed or blocked.
 */
function payHeroVerifyTransaction(string $externalReference, string $userPhone = '', float $amount = 0.0): array {
    $res = payHeroRequest('GET', 'transactions');
    if (!empty($res['data']['transactions']) && is_array($res['data']['transactions'])) {
        foreach ($res['data']['transactions'] as $tx) {
            $txRef = trim((string)($tx['external_reference'] ?? ''));
            $txType = trim((string)($tx['transaction_type'] ?? ''));
            $txAmt = (float)($tx['amount'] ?? 0);
            $txDesc = (string)($tx['description'] ?? '');
            $txStatus = strtolower(trim((string)($tx['status'] ?? '')));

            // Match by exact external reference OR by phone & amount in recent transactions
            $matchesRef = (!empty($externalReference) && $txRef === $externalReference);
            $matchesPhone = false;
            if (!empty($userPhone) && $amount > 0 && abs($txAmt - $amount) < 1.0) {
                $cleanP = preg_replace('/[^0-9]/', '', $userPhone);
                $last9 = substr($cleanP, -9);
                if (str_contains($txDesc, $last9) || str_contains($txDesc, $userPhone)) {
                    $txTime = strtotime($tx['created_at'] ?? '');
                    if ($txTime && (time() - $txTime) < 900) { // Within last 15 minutes
                        $matchesPhone = true;
                    }
                }
            }

            if (($matchesRef || $matchesPhone) && ($txType === 'inbound_payment' || in_array($txStatus, ['success', 'successful', 'paid', 'completed']))) {
                return [
                    'verified' => true,
                    'amount' => $txAmt,
                    'provider_reference' => (string)($tx['provider_reference'] ?? ($tx['receipt_number'] ?? ($tx['mpesa_receipt'] ?? ''))),
                    'description' => $txDesc,
                    'external_reference' => $txRef
                ];
            }
        }
    }
    return ['verified' => false];
}
