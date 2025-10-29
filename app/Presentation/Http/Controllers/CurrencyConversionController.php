<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Application\Queries\ConvertCurrencyQuery;
use App\Application\Queries\ConvertCurrencyQueryHandler;
use App\Domain\Currency\Exceptions\InvalidAmountException;
use App\Domain\Currency\Exceptions\UnsupportedCurrencyException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class CurrencyConversionController
{
    public function __construct(
        private ConvertCurrencyQueryHandler $queryHandler
    ) {}

    public function convert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'size:3', 'alpha'],
            'to' => ['required', 'string', 'size:3', 'alpha'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $query = new ConvertCurrencyQuery(
                from: $request->input('from'),
                    to: $request->input('to'),
                    amount: (string) $request->input('amount')
                );

                $result = $this->queryHandler->handle($query);

                return response()->json($result);

            } catch (UnsupportedCurrencyException $e) {
            return response()->json([
                'error' => 'Unsupported currency',
                'message' => $e->getMessage()
            ], 400);

        } catch (InvalidAmountException $e) {
            return response()->json([
                'error' => 'Invalid amount',
                'message' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Unable to process conversion'
            ], 500);
        }
    }
}