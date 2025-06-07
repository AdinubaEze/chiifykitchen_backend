<?php 

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DeliveryFeeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DeliveryFeeController extends Controller
{
    public function index()
    {
        $fees = DeliveryFeeSetting::all()->mapWithKeys(function ($item) {
            return [$item->delivery_type => $item->fee];
        });

        return response()->json([
            'status' => 'success',
            'data' => $fees
        ]);
    }

    public function update(Request $request)
{
    $validator = Validator::make($request->all(), [
        'delivery' => 'required|numeric|min:0',
        'courier' => 'required|numeric|min:0',
    ]);

    if ($validator->fails()) {
        $errors = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            $errors[$field] = $messages[0];
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $errors
        ], 422);
    }

    DB::beginTransaction();

    try {
        $data = $validator->validated();

        DeliveryFeeSetting::updateOrCreate(
            ['delivery_type' => 'delivery'],
            ['fee' => $data['delivery']]
        );

        DeliveryFeeSetting::updateOrCreate(
            ['delivery_type' => 'courier'],
            ['fee' => $data['courier']]
        );

        DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => 'Delivery fees updated successfully',
            'data' => $data
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Delivery fee update failed: ' . $e->getMessage(), [
            'request' => $request->all(),
            'error' => $e->getTraceAsString()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update delivery fees',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}
}