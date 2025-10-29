<?php

// app/Http/Controllers/Api/CurrencyController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CurrencyController extends Controller
{
    private const SUPPORTED_CURRENCIES = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD'];

    public function convert(Request $request)
    {
        $startTime = microtime(true);

        $validator = Validator::make($request->all(), [
            'from' => 'required|string|size:3|alpha',
            'to' => 'required|string|size:3|alpha',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $from = strtoupper($request->from);
        $to = strtoupper($request->to);
        $amount = $request->amount;

        if (!in_array($from, self::SUPPORTED_CURRENCIES) ||
            !in_array($to, self::SUPPORTED_CURRENCIES)) {
            return response()->json([
                'error' => 'Unsupported currency',
                'supported' => self::SUPPORTED_CURRENCIES
            ], 400);
        }

        if ($from === $to) {
            return response()->json([
                'data' => [
                    'from' => $from,
                    'to' => $to,
                    'amount' => number_format($amount, 2, '.', ''),
                    'result' => number_format($amount, 2, '.', ''),
                    'rate' => '1.0',
                    'last_updated' => now()->toIso8601String(),
                ],
                'meta' => [
                    'source' => 'same_currency',
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]
            ]);
        }

        try {
            $rateData = $this->getExchangeRate($from, $to);

            if (!$rateData) {
                return response()->json([
                    'error' => 'Unable to fetch exchange rate'
                ], 503);
            }

            $result = bcmul((string)$amount, $rateData['rate'], 8);

            $executionTime = (microtime(true) - $startTime) * 1000;

            return response()->json([
                'data' => [
                    'from' => $from,
                    'to' => $to,
                    'amount' => number_format($amount, 2, '.', ''),
                    'result' => number_format((float)$result, 2, '.', ''),
                    'rate' => $rateData['rate'],
                    'last_updated' => $rateData['last_updated'],
                ],
                'meta' => [
                    'source' => $rateData['source'],
                    'execution_time_ms' => round($executionTime, 2),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getExchangeRate(string $from, string $to): ?array
    {
        $cacheKey = "rate:{$from}:{$to}";

        $cached = Cache::remember($cacheKey, 1, function() use ($from, $to) {
            $dbRate = DB::table('currency_rates')
                ->where('from_currency', $from)
                ->where('to_currency', $to)
                ->where('last_updated', '>', now()->subHour())
                ->first();

            if ($dbRate) {
                return [
                    'rate' => $dbRate->rate,
                    'last_updated' => $dbRate->last_updated,
                    'source' => 'local_db',
                ];
            }

            return $this->fetchFromExternalProvider($from, $to);
        });

        return $cached;
    }

    private function fetchFromExternalProvider(string $from, string $to): ?array
    {
        try {
            $response = Http::timeout(0.5)
                ->get("https://open.er-api.com/v6/latest/{$from}");

            if ($response->successful()) {
                $data = $response->json();
                $rate = $data['rates'][$to] ?? null;

                if ($rate) {
                    $rateData = [
                        'rate' => (string)$rate,
                        'last_updated' => now()->toIso8601String(),
                        'source' => 'external_api',
                    ];

                    DB::table('currency_rates')->updateOrInsert(
                        [
                            'from_currency' => $from,
                            'to_currency' => $to,
                        ],
                        [
                            'rate' => $rate,
                            'source' => 'external_api',
                            'last_updated' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    return $rateData;
                }
            }
        } catch (\Exception $e) {
            \Log::error("External API error: " . $e->getMessage());
        }

        return null;
    }

    public function health()
    {
        $checks = [];

        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'ok'];
        } catch (\Exception $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        try {
            Cache::put('health_check', 'ok', 5);
            $checks['cache'] = ['status' => Cache::get('health_check') === 'ok' ? 'ok' : 'error'];
        } catch (\Exception $e) {
            $checks['cache'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $isHealthy = !in_array('error', array_column($checks, 'status'));

        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $isHealthy ? 200 : 503);
    }
}