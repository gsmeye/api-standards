<?php

/**
 * Minimal client for the GSM EYE Reseller API.
 *
 * Requires PHP 8.0+ with the curl extension. No other dependencies.
 *
 *     $api = new GsmEyeClient('https://YOUR-DOMAIN', 'you@example.com', 'YOUR_API_KEY');
 *
 *     $account = $api->accountInfo();
 *     $order   = $api->placeOrder('6904', imei: '356789104512345', fields: [
 *         'Picture on sign-in page' => 'https://i.imgur.com/abc123.jpg',
 *     ]);
 *     $status  = $api->getOrder($order['REFERENCEID']);
 */
final class GsmEyeClient
{
    public const STATUS_WAITING = 0;
    public const STATUS_IN_PROCESS = 1;
    public const STATUS_REJECTED = 3;
    public const STATUS_SUCCESS = 4;

    private string $endpoint;

    public function __construct(
        string $siteUrl,
        private string $username,
        private string $apiKey,
        private int $timeout = 60,
    ) {
        $this->endpoint = rtrim($siteUrl, '/') . '/api/index.php';
    }

    /** @return array{credit: string, creditraw: float, mail: string, currency: string} */
    public function accountInfo(): array
    {
        return $this->call('accountinfo')['SUCCESS'][0]['AccountInfo'];
    }

    /**
     * The full catalogue, keyed by group name. Allowed once per 5 minutes.
     *
     * @return array<string, array{GROUPNAME: string, GROUPTYPE: string, SERVICES: array<string, array<string, mixed>>}>
     */
    public function serviceList(): array
    {
        return $this->call('imeiservicelist')['SUCCESS'][0]['LIST'];
    }

    /**
     * Place one order.
     *
     * @param  array<string, string>  $fields  extra inputs keyed by exact fieldname
     * @return array{MESSAGE: string, REFERENCEID: int}
     */
    public function placeOrder(string $serviceId, string $imei = '', array $fields = [], int $quantity = 1): array
    {
        $xml = '<PARAMETERS>'
            . '<ID>' . htmlspecialchars($serviceId, ENT_XML1) . '</ID>'
            . '<QNT>' . $quantity . '</QNT>'
            . ($imei !== '' ? '<IMEI>' . htmlspecialchars($imei, ENT_XML1) . '</IMEI>' : '')
            . ($fields ? '<CUSTOMFIELD>' . base64_encode(json_encode($fields, JSON_UNESCAPED_UNICODE)) . '</CUSTOMFIELD>' : '')
            . '</PARAMETERS>';

        return $this->call('placeimeiorder', $xml)['SUCCESS'][0];
    }

    /**
     * Place many orders. Rows are keyed by your own reference:
     *   ['A-1001' => ['ID' => '6904', 'IMEI' => '35...', 'CUSTOMFIELD' => ['Field' => 'value']]]
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array{status: string, message: string, referenceid?: int}>
     */
    public function placeBulkOrders(array $rows): array
    {
        return $this->call('placeimeiorderbulk', base64_encode(json_encode($rows, JSON_UNESCAPED_UNICODE)))['SUCCESS'];
    }

    /** @return array{IMEI: string, STATUS: int, CODE: ?string, COMMENTS: string} */
    public function getOrder(int|string $orderId): array
    {
        $xml = '<PARAMETERS><ID>' . htmlspecialchars((string) $orderId, ENT_XML1) . '</ID></PARAMETERS>';

        return $this->call('getimeiorder', $xml)['SUCCESS'][0];
    }

    /**
     * Status of many orders: ['A-1001' => 98214, ...] → ['A-1001' => ['STATUS' => 4, 'CODE' => '...'], ...]
     *
     * @param  array<string, int|string>  $orderIds
     * @return array<string, array{STATUS: int, CODE: ?string}>
     */
    public function getOrders(array $orderIds): array
    {
        $rows = array_map(fn ($id) => ['ID' => (string) $id], $orderIds);
        $response = $this->call('getimeiorderbulk', base64_encode(json_encode($rows)));
        unset($response['apiversion']);

        return array_map(fn (array $row) => $row['SUCCESS'][0], $response);
    }

    public static function isFinal(int $status): bool
    {
        return $status === self::STATUS_SUCCESS || $status === self::STATUS_REJECTED;
    }

    /** @return array<string, mixed> */
    private function call(string $action, ?string $parameters = null): array
    {
        $form = [
            'username' => $this->username,
            'apiaccesskey' => $this->apiKey,
            'requestformat' => 'JSON',
            'action' => $action,
        ];
        if ($parameters !== null) {
            $form['parameters'] = $parameters;
        }

        $curl = curl_init($this->endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($form),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new GsmEyeException("Connection failed: {$error}", 0);
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            throw new GsmEyeException("Unexpected response (HTTP {$status})", $status);
        }

        if (isset($data['ERROR'])) {
            throw new GsmEyeException($data['ERROR'][0]['MESSAGE'] ?? 'Unknown error', $status);
        }

        // Rate limiter and maintenance answers use Laravel's {"message": "..."} shape.
        if ($status >= 400) {
            throw new GsmEyeException($data['message'] ?? "HTTP {$status}", $status);
        }

        return $data;
    }
}

final class GsmEyeException extends RuntimeException
{
    /** The HTTP status: 401 bad key, 403 wrong IP, 404 not found, 429 rate limited, 503 maintenance. */
    public function httpStatus(): int
    {
        return $this->getCode();
    }
}
