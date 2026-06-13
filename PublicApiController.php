<?php

/**
 * ============================================================================
 *  GSM EYE — PUBLIC API STANDARD (REFERENCE)
 * ============================================================================
 *
 *  This file is a published REFERENCE of the GSM EYE public API contract.
 *  It documents exactly how an external client / reseller connects to a
 *  GSM EYE server and what request/response shapes to expect.
 *
 *  It mirrors the Dhru Fusion / GSM-style API so existing reseller panels can
 *  integrate without changes. This file is for documentation only — it is not
 *  loaded by the live application (the live implementation lives in
 *  app/Http/Controllers/Api/SiteApiController.php).
 *
 *  ----------------------------------------------------------------------------
 *  ENDPOINT
 *  ----------------------------------------------------------------------------
 *  URL     : https://YOUR-DOMAIN/api/index.php
 *  METHOD  : POST (GET also accepted)
 *  FORMAT  : application/x-www-form-urlencoded
 *
 *  ----------------------------------------------------------------------------
 *  REQUIRED PARAMETERS (every request)
 *  ----------------------------------------------------------------------------
 *  username       string  Your account email.
 *  apiaccesskey   string  Your API access key (from the API settings page).
 *  requestformat  string  Must be "JSON".
 *  action         string  One of: imeiservicelist | accountinfo
 *                                 | placeimeiorder  | getimeiorder
 *
 *  ----------------------------------------------------------------------------
 *  AUTHENTICATION RULES
 *  ----------------------------------------------------------------------------
 *  - The apiaccesskey must belong to the given username (email).
 *  - The first call locks your account to the calling IP. Later calls from a
 *    different IP are rejected (403). Contact admin to reset the bound IP.
 *  - "imeiservicelist" is rate-limited to once every 5 minutes per IP.
 *
 *  ----------------------------------------------------------------------------
 *  RESPONSE ENVELOPE
 *  ----------------------------------------------------------------------------
 *  Success:  { "SUCCESS": [ { ... } ] }
 *  Error:    { "ERROR":   [ { "MESSAGE": "..." } ] }
 *
 * ============================================================================
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderInput;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\SiteApi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PublicApiController extends Controller
{
    /**
     * Single entry point. Dispatches on the `action` parameter.
     *
     * Route: Route::match(['get','post'], 'index.php/{any?}', 'server')
     */
    public function server(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username'      => 'required|string',
            'apiaccesskey'  => 'required|string',
            'requestformat' => 'required|in:JSON',
            'action'        => 'required|in:imeiservicelist,accountinfo,placeimeiorder,getimeiorder',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 400);
        }

        // --- Authenticate the API access key + username ---------------------
        $siteApi = SiteApi::where('api_key', $request->apiaccesskey)
            ->with('user.currencie')
            ->first();

        if (!$siteApi || !$siteApi->user || $siteApi->user->email !== $request->username) {
            return $this->error('Invalid API access key or username', 401);
        }

        // --- Bind / verify caller IP ----------------------------------------
        $requestIp = $request->ip();
        if (empty($siteApi->api_ip)) {
            $siteApi->api_ip = $requestIp;
            $siteApi->save();
        } elseif ($siteApi->api_ip !== $requestIp) {
            return $this->error('Unauthorized IP address', 403);
        }

        return match ($request->action) {
            'accountinfo'     => $this->accountInfo($siteApi->user),
            'imeiservicelist' => $this->imeiServiceList($siteApi->user, $requestIp),
            'placeimeiorder'  => $this->placeImeiOrder($request, $siteApi->user, $requestIp),
            'getimeiorder'    => $this->getImeiOrder($request, $siteApi),
            default           => $this->error('Unknown action', 400),
        };
    }

    /* ===================================================================== *
     *  ACTIONS
     * ===================================================================== */

    /**
     * action = accountinfo
     *
     * Response:
     *  SUCCESS[0].AccountInfo = { credit, creditraw, mail, currency }
     */
    private function accountInfo(User $user)
    {
        return $this->success([
            'SUCCESS' => [[
                'message'     => 'Your Account Info',
                'AccountInfo' => $this->accountBlock($user),
            ]],
        ]);
    }

    /**
     * action = imeiservicelist
     *
     * Returns every active service grouped by service group, plus the
     * caller's account info. Rate limited to once / 5 minutes per IP.
     *
     * Response:
     *  SUCCESS[0].LIST[GROUPNAME] = {
     *      GROUPNAME, GROUPTYPE,
     *      SERVICES[serviceId] = {
     *          SERVICEID, SERVICETYPE, QNT, SERVER, MINQNT, MAXQNT,
     *          SERVICENAME, CREDIT, TIME, INFO, Requires.Custom[]
     *      }
     *  }
     */
    private function imeiServiceList(User $user, string $requestIp)
    {
        $key = 'update-service:' . $requestIp;
        if (Cache::has($key)) {
            $minutesAgo = now()->diffInMinutes(Carbon::createFromTimestamp(Cache::get($key)));
            return $this->error(
                "Action [imeiservicelist] is allowed once per 5 minutes. Last synced {$minutesAgo} minute(s) ago.",
                429
            );
        }

        $serviceGroups = ServiceGroup::whereHas('services', fn ($q) => $q->where('status', 'Active'))
            ->with(['services' => fn ($q) => $q->where('status', 'Active')->with('inputs')])
            ->get();

        $list = [];
        foreach ($serviceGroups as $group) {
            $services = [];
            foreach ($group->services as $service) {
                $requiresCustom = $service->inputs->map(fn ($input) => [
                    'type'         => 'serviceimei',
                    'fieldname'    => $input->field_name ?? '',
                    'fieldtype'    => 'text',
                    'description'  => '',
                    'fieldoptions' => '',
                    'regexpr'      => '',
                    'adminonly'    => '',
                    'required'     => $input->required ?? 'on',
                ])->toArray();

                $services[$service->id] = [
                    'SERVICEID'       => $service->service_id,
                    'SERVICETYPE'     => $service->service_type,
                    'QNT'             => $service->qnt,
                    'SERVER'          => '0',
                    'MINQNT'          => $service->min_qnt,
                    'MAXQNT'          => $service->max_qnt,
                    'SERVICENAME'     => $service->service_name,
                    'CREDIT'          => $service->priceForUser($user),
                    'TIME'            => $service->time,
                    'INFO'            => '',
                    'Requires.Custom' => $requiresCustom,
                ];
            }

            $list[$group->name] = [
                'GROUPNAME' => $group->name,
                'GROUPTYPE' => $group->type,
                'SERVICES'  => $services,
            ];
        }

        Cache::put($key, now()->timestamp, now()->addMinutes(5));

        return $this->success([
            'SUCCESS' => [[
                'MESSAGE'     => 'IMEI Service List',
                'LIST'        => $list,
                'ACCOUNTINFO' => $this->accountBlock($user),
            ]],
        ]);
    }

    /**
     * action = placeimeiorder
     *
     * parameters (XML string):
     *  <PARAMETERS>
     *      <ID>123</ID>                <!-- service_id (required) -->
     *      <QNT>1</QNT>                <!-- optional, defaults to 1 -->
     *      <IMEI>3556...</IMEI>        <!-- required if service needs IMEI -->
     *      <CUSTOMFIELD>base64(json)</CUSTOMFIELD>  <!-- other fields -->
     *  </PARAMETERS>
     *
     * Response:
     *  SUCCESS[0] = { MESSAGE: "Order received", REFERENCEID: <orderId> }
     */
    private function placeImeiOrder(Request $request, User $user, string $requestIp)
    {
        if (!$request->filled('parameters')) {
            return $this->error('Missing parameters', 400);
        }

        try {
            $xml = simplexml_load_string($request->parameters);
            if ($xml === false) {
                throw new \Exception('Invalid format');
            }

            $serviceId = (string) ($xml->ID ?? '');
            $imei      = (string) ($xml->IMEI ?? '');
            $qntRaw    = (string) ($xml->QNT ?? '');
            $qnt       = (ctype_digit($qntRaw) && (int) $qntRaw > 0) ? (int) $qntRaw : 1;
            $custom    = json_decode(base64_decode((string) ($xml->CUSTOMFIELD ?? '')), true) ?: [];

            if ($serviceId === '') {
                return $this->error('Service ID is required', 400);
            }

            $service = Service::where('service_id', $serviceId)->with('inputs')->first();
            if (!$service) {
                return $this->error('Invalid service ID', 404);
            }

            $hasRequiredImei = $service->inputs->contains(
                fn ($i) => $i->field_name === 'IMEI' && in_array($i->required, ['on', '1'])
            );
            if ($hasRequiredImei && $imei === '') {
                return $this->error('Missing required field: IMEI', 400);
            }

            DB::beginTransaction();

            $user = User::where('id', $user->id)->lockForUpdate()->first();

            $unitPrice    = $service->priceForUser($user);
            $servicePrice = $unitPrice * $qnt;

            // Balance with optional overdue support.
            if ($user->balance >= $servicePrice) {
                $user->balance -= $servicePrice;
            } else {
                $remaining     = $servicePrice - $user->balance;
                $user->balance = 0;

                if (!$user->overdue_status) {
                    DB::rollBack();
                    return $this->error('Insufficient balance', 400);
                }
                if ($remaining > ($user->overdue_amount - $user->overdue_used)) {
                    DB::rollBack();
                    return $this->error('Overdue limit exceeded', 400);
                }
                $user->overdue_used += $remaining;
            }
            $user->save();

            $order = Order::create([
                'user_id'        => $user->id,
                'service_type'   => $service->service_type,
                'service_id'     => $service->service_id,
                'service_title'  => $service->service_name,
                'service_qnt'    => $qnt,
                'service_price'  => $servicePrice,
                'currencie_id'   => $user->currencie_id,
                'process_type'   => 'Api',
                'api_id'         => $service->api_id,
                'service_status' => 'Waiting Action',
            ]);

            foreach ($service->inputs as $input) {
                $value = ($input->field_name === 'IMEI' || $input->custom == 1)
                    ? $imei
                    : ($custom[$input->field_name] ?? null);

                OrderInput::create([
                    'order_id'    => $order->id,
                    'field_name'  => $input->field_name,
                    'field_value' => $value,
                    'required'    => in_array($input->required, ['on', '1']),
                ]);
            }

            DB::commit();

            return $this->success([
                'SUCCESS' => [[
                    'MESSAGE'     => 'Order received',
                    'REFERENCEID' => $order->id,
                ]],
            ]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return $this->error('Invalid parameters: ' . $e->getMessage(), 400);
        }
    }

    /**
     * action = getimeiorder
     *
     * parameters (XML string):
     *  <PARAMETERS><ID>orderReferenceId</ID></PARAMETERS>
     *
     * STATUS codes: 0 = waiting, 1 = in process, 3 = rejected, 4 = success
     *
     * Response:
     *  SUCCESS[0] = { IMEI, STATUS, CODE, COMMENTS }
     */
    private function getImeiOrder(Request $request, SiteApi $siteApi)
    {
        if (!$request->filled('parameters')) {
            return $this->error('Missing parameters', 400);
        }

        try {
            $xml = simplexml_load_string($request->parameters);
            if ($xml === false) {
                throw new \Exception('Invalid format');
            }

            $orderId = (string) ($xml->ID ?? '');
            if ($orderId === '') {
                return $this->error('Service ID is required', 400);
            }

            // A client may only inspect orders that belong to its own account.
            $order = Order::where('id', $orderId)
                ->where('user_id', $siteApi->user_id)
                ->first();

            if (!$order) {
                return $this->error('Invalid service ID', 404);
            }

            $status = match ($order->service_status) {
                'In process'     => 1,
                'Order rejected' => 3,
                'Order success'  => 4,
                default          => 0,
            };

            return $this->success([
                'ID'      => $orderId,
                'SUCCESS' => [[
                    'IMEI'     => '',
                    'STATUS'   => $status,
                    'CODE'     => $order->service_comments,
                    'COMMENTS' => '',
                ]],
            ]);
        } catch (\Exception $e) {
            return $this->error('Invalid parameters: ' . $e->getMessage(), 400);
        }
    }

    /* ===================================================================== *
     *  HELPERS
     * ===================================================================== */

    /** Standard account-info block reused by accountinfo + imeiservicelist. */
    private function accountBlock(User $user): array
    {
        $rate = $user->currencie->rate ?? 1;

        return [
            'credit'    => ($user->currencie->icon ?? '') . number_format($user->balance * $rate, 2),
            'creditraw' => round($user->balance * $rate, 2),
            'mail'      => $user->email,
            'currency'  => $user->currencie->code ?? 'USD',
        ];
    }

    /** Success envelope: { "SUCCESS": [ ... ] } */
    private function success(array $data, int $code = 200)
    {
        return response()->json($data, $code)
            ->header('X-Powered-By', 'GSM-EYE');
    }

    /** Error envelope: { "ERROR": [ { "MESSAGE": "..." } ] } */
    private function error(string $message, int $code = 200)
    {
        return response()->json([
            'ERROR' => [['MESSAGE' => $message]],
        ], $code)->header('X-Powered-By', 'GSM-EYE');
    }
}
