<?php

namespace SystemNotifications\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use SystemNotifications\Mail\Notification\EmailAndNotification;
use SystemNotifications\Traits\Notification\FCM;

class PushNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    use FCM;

    protected $message;

    protected $title;
    protected $fcmTokens;

    public function __construct($fcmTokens, $title, $message)
    {
        $this->fcmTokens = $fcmTokens;
        $this->title = $title;
        $this->message = $message;

    }
    public function handle()
    {
        // Access job data using $this->eventClone, $this->_userInfo, $this->_templateInfo
        Log::info('EmailNotificationJob handled successfully.');
        try{
        foreach ($this->fcmTokens as $fcmToken) {
            Log::debug('fcm token: ' . $fcmToken . ' message: ' . $this->message);
            $this->sendFCM(['title' => $this->title, 'message' => $this->message], $fcmToken);
        }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    protected static function exceptionalCase($template = null)
    {
        if ($template == 'ApplyOnJobExternal') {
            return true;
        }
        return false;
    }
}
