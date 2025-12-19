<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserKyc;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request) {
        $user = Auth::user()->load(['kyc', 'linkedAccounts']);
        $user->name = $user->name ?: trim($user->first_name . ' ' . $user->last_name);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

public function update(Request $r) {
    $user = Auth::user();
    
    $r->validate([
        'email' => 'required|email|unique:users,email,' . $user->id,
        'name' => 'required|string|max:255',
    ]);

    $data = $r->only(['email', 'phone', 'address']);

    $parts = explode(' ', $r->name, 2);
    $data['first_name'] = $parts[0];
    $data['last_name'] = $parts[1] ?? '';

    $data['name'] = $r->name;
    
    $user->update($data);
    
    return response()->json([
        'success' => true, 
        'data' => $user->fresh()
    ]);
}

    public function submitKyc(Request $r) {
        $r->validate([
            'id_type' => 'required|string',
            'id_value' => 'required|string',
            'photo' => 'nullable|image|max:2048',
            'document' => 'nullable|file|max:5120'
        ]);

        $user = Auth::user();

        $kyc = UserKyc::updateOrCreate(
            ['user_id' => $user->id],
            [
                'provider' => 'dummy',
                'id_type' => $r->id_type,
                'id_value' => $r->id_value,
                'status' => 'pending'
            ]
        );

        if ($r->hasFile('photo')) {
            $kyc->photo_path = $r->file('photo')->store('kyc/photos','public');
        }
        if ($r->hasFile('document')) {
            $kyc->document_path = $r->file('document')->store('kyc/docs','public');
        }
        $kyc->save();

        // optionally trigger async verification job here
        return response()->json(['success'=>true,'message'=>'KYC submitted','data'=>$kyc]);
    }

    public function getKyc(Request $r) {
        $user = Auth::user();
        $kyc = UserKyc::where('user_id',$user->id)->first();
        return response()->json(['success'=>true,'data'=>$kyc]);
    }
}
