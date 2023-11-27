<?php

namespace SystemNotifications\Mail\Notification;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use SystemNotifications\Helpers\Notifications\NotificationHelper;
use function App\Mail\Notification\resolve_template;

class EmailAndNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The order instance.
     *
     * @var
     */
    public $user;

    public $template;
    public $variable;
    public $attachment;

    /**
     * Create a new message instance.
     *
     * @param $seeker
     * @param $variable
     */
    public function __construct($user, $template,$variable = [], $attachments = null)
    {
        $this->user = $user;
        $this->template = $template;
        $this->variable = $variable;
        $this->attachment = $attachments;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // some constants
        $this->variable['app_url'] = APPURL;
        $this->variable['app_url_hire'] = EMPLOYER_APP_URL;
        $this->variable['fb_url'] = FBURL;
        $this->variable['tw_url'] = TWURL;
        $this->variable['insta_url'] = INSTAURL;
        $this->variable['linkedin_url'] = LINKEDINURL;
        $this->variable['youtube_url'] = YOUTUBEURL;
        $this->variable['copyright_year'] = date('Y');
        $temp =(string)  NotificationHelper::replaceMacros($this->template->content,$this->variable);
        $tempSubject = NotificationHelper::replaceMacros($this->template->subject,$this->variable);
        $this->subject($tempSubject);
        if($this->attachment){
            $this->attach($this->attachment);
        }

        return $this->html($temp);
    }
}
