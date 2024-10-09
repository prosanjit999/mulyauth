<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use GuzzleHttp\Client;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        // Telegram এ ধন্যবাদ মেসেজ পাঠানো
        $this->sendThankYouMessage($request->name); // এখানে name ফিল্ড ব্যবহার করা হচ্ছে

        return redirect(route('dashboard', absolute: false));
    }

    public function sendThankYouMessage($userName)
    {
        $client = new Client();
        $token = env('TELEGRAM_BOT_TOKEN');
        $message = "Thank you, {$userName}, for creating an account on our website!";

        // ব্যবহারকারীর chat_id সংগ্রহ করার জন্য API কল
        $updates = $client->get("https://api.telegram.org/bot{$token}/getUpdates");
        $updates = json_decode($updates->getBody()->getContents(), true);

        // ব্যবহারকারীর chat_id খুঁজে বের করা
        foreach ($updates['result'] as $update) {
            if (isset($update['message']['from']['username']) && $update['message']['from']['username'] == ltrim($userName, '@')) {
                $chat_id = $update['message']['from']['id']; // chat_id সংগ্রহ করা

                // মেসেজ পাঠানো
                $url = "https://api.telegram.org/bot{$token}/sendMessage";
                $client->post($url, [
                    'form_params' => [
                        'chat_id' => $chat_id,
                        'text' => $message,
                    ],
                ]);
                break; // chat_id পাওয়ার পর লুপ বন্ধ করা
            }
        }
    }
}
