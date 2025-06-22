<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator; 

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['index']);
        $this->middleware('role:admin')->except(['index']);
    }

    public function index()
    {
        $settings = Setting::firstOrCreate([]);
        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_mode' => 'sometimes|in:test,live',
            'company_info' => 'sometimes|array',
            'company_info.name' => 'sometimes|required|string|max:255',
            'company_info.email' => 'sometimes|required|email|max:255',
            'company_info.phone' => 'sometimes|required|string|max:20',
            'company_info.website' => 'sometimes|nullable|url|max:255',
            'company_info.address' => 'sometimes|nullable|string',
            'company_info.logo' => 'sometimes|nullable|string',
            'notifications' => 'sometimes|array',
            'notifications.email_notifications' => 'sometimes|boolean',
            'notifications.sms_notifications' => 'sometimes|boolean',
            'notifications.push_notifications' => 'sometimes|boolean',
            'notifications.order_updates' => 'sometimes|boolean',
            'notifications.promotional_emails' => 'sometimes|boolean',
            'general_settings' => 'sometimes|array',
            'general_settings.currency' => 'sometimes|string|max:3',
            'general_settings.tax_rate' => 'sometimes|numeric|min:0|max:100',
            'general_settings.delivery_fee' => 'sometimes|numeric|min:0',
            'general_settings.minimum_order_amount' => 'sometimes|numeric|min:0',
            'payment_gateways' => 'sometimes|array',
            'payment_gateways.*.id' => 'sometimes|required|string',
            'payment_gateways.*.name' => 'sometimes|required|string',
            'payment_gateways.*.enabled' => 'sometimes|boolean',
            'payment_gateways.*.public_key' => 'sometimes|nullable|string',
            'payment_gateways.*.secret_key' => 'sometimes|nullable|string',
             'payment_gateways.*.public_test_key' => 'sometimes|nullable|string',
            'payment_gateways.*.secret_test_key' => 'sometimes|nullable|string',
            'payment_gateways.*.logo' => 'sometimes|nullable|string',
            'social_media' => 'sometimes|array',
            'social_media.facebook' => 'sometimes|nullable|url|max:255',
            'social_media.linkedin' => 'sometimes|nullable|url|max:255',
            'social_media.tiktok' => 'sometimes|nullable|url|max:255',
            'social_media.instagram' => 'sometimes|nullable|url|max:255',
            'social_media.youtube' => 'sometimes|nullable|url|max:255',
            'social_media.x' => 'sometimes|nullable|url|max:255',
            'social_media.thread' => 'sometimes|nullable|url|max:255',
            'social_media.snapchat' => 'sometimes|nullable|url|max:255',
        ]);

        
         if ($validator->fails()) {
             // Convert error messages to only the first one per field
             $errors = [];
             foreach ($validator->errors()->messages() as $field => $messages) {
                 $errors[$field] = $messages[0];
             }
     
             return response()->json([
                 'status' => 'error',
                 'message' => 'Validation failed - check input values',
                 'errors' => $errors
             ], 422);
         }

        $settings = Setting::firstOrCreate([]);
        $settings->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Settings updated successfully',
            'data' => $settings
        ]);
    }

    
    public function togglePaymentGateway($id)
    {
        $settings = Setting::firstOrCreate([]);
        $gateways = $settings->payment_gateways;
        
        foreach ($gateways as &$gateway) {
            if ($gateway['id'] == $id) {
                $gateway['enabled'] = !$gateway['enabled'];
                break;
            }
        }
        
        $settings->payment_gateways = $gateways;
        $settings->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Payment gateway toggled successfully',
            'data' => $settings
        ]);
    }


    public function uploadCompanyLogo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
    
        try {
            $settings = Setting::firstOrCreate([]);
            
            // Delete old logo if exists
            if (isset($settings->company_info['logo'])) {
                $oldLogoPath = str_replace(asset('storage/'), '', $settings->company_info['logo']);
                Storage::disk('public')->delete($oldLogoPath);
            }
    
            // Store new logo
            $path = $request->file('logo')->store('logos', 'public');
            $url = asset('storage/'.str_replace('public/', '', $path));
    
            // Update settings
            $companyInfo = $settings->company_info ?? [];
            $companyInfo['logo'] = $url;
            $settings->company_info = $companyInfo;
            $settings->save();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Company logo uploaded successfully',
                'data' => ['url' => $url]
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload logo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadGatewayLogo(Request $request, $gatewayId)
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
    
        try {
            $settings = Setting::firstOrCreate([]);
            $gateways = $settings->payment_gateways ?? [];
            
            // Find the gateway and delete its old logo if exists
            foreach ($gateways as &$gateway) {
                if ($gateway['id'] == $gatewayId) {
                    if (isset($gateway['logo'])) {
                        $oldLogoPath = str_replace(asset('storage/'), '', $gateway['logo']);
                        Storage::disk('public')->delete($oldLogoPath);
                    }
                    
                    // Store new logo
                    $path = $request->file('logo')->store('gateway-logos', 'public');
                    $url = asset('storage/'.str_replace('public/', '', $path));
                    
                    $gateway['logo'] = $url;
                    break;
                }
            }
            
            $settings->payment_gateways = $gateways;
            $settings->save();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Gateway logo uploaded successfully',
                'data' => ['url' => $url]
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload gateway logo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}