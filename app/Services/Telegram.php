<?php

namespace App\Services;

use App\Models\LastUpdatedMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use OpenAI;
use Telegram\Bot\Laravel\Facades\Telegram as Tel;

class Telegram
{

    private static $availableCommands = [
        '/register',
        '/login',
        '/logout',
    ];

    private static function sendMessage($chat_id, $text)
    {
        Tel::sendChatAction([
            'chat_id' => $chat_id,
            'action' => 'typing'
        ]);
        Tel::sendMessage([
            'chat_id' => $chat_id,
            'text' => $text
        ]);
    }

    public static function replyUnredMessage()
    {
        // $lastUpdatedId = LastUpdatedMessage::create([
        //     'last_updated_id' => 516966297
        // ]);
        // dd($lastUpdatedId);
        $lastUpdatedId = LastUpdatedMessage::first();

        $response = collect(Tel::getUpdates(['offset' =>  $lastUpdatedId->last_updated_id + 1]));

        $response->each(function ($res) {
            // dd($res);
            $telegram_id = $res['message']['from']['id'];
            $message = isset($res['message']['text']) ? $res['message']['text'] : '';
            $chat_id = $res['message']['chat']['id'];

            // Create or update user
            $guest = User::updateOrCreate(
                ['telegram_id' => $telegram_id],
                ['name' => $res['message']['from']['first_name']]
            );

            // Handle command mode
            if (strpos($message, '/') === 0) {
                $guest->update([
                    'chat_mode' => in_array($message, self::$availableCommands) ? $message : '/default'
                ]);
            }

            // Process chat modes
            switch ($guest->chat_mode) {
                case '/register':
                    self::handleRegisterMode($guest, $message, $chat_id);
                    break;
                case '/login':
                    self::handleLoginMode($guest, $message, $chat_id);
                    break;
                case '/logout':
                    self::handleLogoutMode($guest, $message, $chat_id);
                    break;
                case '/default':
                    self::handleDefaultMode($guest, $telegram_id, $chat_id, $message);
                    break;
                default:
                    self::sendMessage($chat_id, "Invalid mode or command.");
                    break;
            }
        });
        if ($response->count() > 0) {
            $newLastUpdatedId = $response->last();
            $lastUpdatedId->update([
                'last_updated_id' => $newLastUpdatedId['update_id']
            ]);
        }
    }
    private static function handleRegisterMode($guest, $message, $chat_id)
    {
        if ($message !== '/register') {
            $guest->update([
                'password' => bcrypt($message),
                'chat_mode' => '/default'
            ]);
            self::sendMessage($chat_id, 'Password Updated');
        } else {
            self::sendMessage($chat_id, 'Type your password');
        }
    }

    /**
     * Handle login mode.
     */
    private static function handleLoginMode($guest, $message, $chat_id)
    {
        if ($message !== '/login') {
            if (Hash::check($message, $guest->password)) {
                $guest->update([
                    'chat_mode' => '/default',
                    'active_until' => Carbon::now()->addHours(3)
                ]);
                self::sendMessage($chat_id, "You're successfully logged in");
                self::sendMessage($chat_id, "Send me a text that you want me to correct.");
            } else {
                self::sendMessage($chat_id, 'Wrong Password');
            }
        } else {
            self::sendMessage($chat_id, 'Type your password');
        }
    }

    /**
     * Handle default mode.
     */
    private static function handleDefaultMode($guest, $telegram_id, $chat_id, $message)
    {
        if ($guest->password) {
            $user = User::where('telegram_id', $telegram_id)
                ->where('active_until', '>', Carbon::now())
                ->first();

            if ($user) {
                Tel::sendChatAction([
                    'chat_id' => $chat_id,
                    'action' => 'typing'
                ]);
                $client = OpenAI::client(env('OPEN_AI_KEY'));
                $result = $client->chat()->create([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "You are a good grammar corrector, and your job is only and only correct my English words, so please check and edit the English words that all I've sent to you and give me an explanation only if I've made mistakes. you can suggest an alternative sentences if necessary. if i sent you sentences from other languages beside english, translate it to english"
                        ],
                        ['role' => 'user', 'content' => $message],
                    ],
                ]);
                self::sendMessage($chat_id, $result->choices[0]->message->content);
            } else {
                self::sendMessage($chat_id, "You're not login");
            }
        } else {
            self::sendMessage($chat_id, "You don't have an account");
        }
    }
    private static function handleLogoutMode($guest, $telegram_id, $chat_id)
    {
        if ($guest->password) {
            $guest->update([
                'active_until' => Carbon::now(),
                'chat_mode' => '/default'
            ]);
            self::sendMessage($chat_id, "Logged out successfully");
        } else {
            self::sendMessage($chat_id, "You don't have an account");
        }
    }
}
