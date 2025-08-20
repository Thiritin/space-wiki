<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login()
    {
        return Socialite::driver('identity')
            ->scopes(['openid', 'profile', 'groups'])
            ->redirect();
    }

    public function loginCallback()
    {
        try {
            $socialiteUser = Socialite::driver('identity')->user();
        } catch (\Exception $e) {
            return redirect()->route('login');
        }

        // For wiki access, you can configure allowed group IDs in config
        $claims = $socialiteUser->user ?? [];
        $allowedGroupIds = config('wiki.allowed_group_ids', []);
        $userGroupIds = [];
        
        if (isset($claims['groups']) && is_array($claims['groups'])) {
            $userGroupIds = $claims['groups'];
        } elseif (isset($claims['groups'])) {
            $userGroupIds = [$claims['groups']];
        }
        
        // Check if user has any allowed group access
        if (!empty($allowedGroupIds) && !array_intersect($allowedGroupIds, $userGroupIds)) {
            return redirect()->route('login')->withErrors(['access' => 'You are not authorized to access this wiki application.']);
        }

        $user = User::updateOrCreate([
            'remote_id' => $socialiteUser->getId(),
        ], [
            'remote_id' => $socialiteUser->getId(),
            'name' => $socialiteUser->getName(),
        ]);

        Auth::login($user, true);

        return redirect()->route('dashboard');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }
}