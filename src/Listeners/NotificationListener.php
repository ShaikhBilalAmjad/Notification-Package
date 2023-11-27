<?php

namespace SystemNotifications\Listeners;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use SystemNotifications\Mail\Notification\EmailAndNotification;

class NotificationListener
{


    protected $dataTable = [
        'employer' => 'employers',
        'seeker' => 'seekers',
        'recruiter' => 'recruiters',
    ];

    protected $processAction = [
        'email' => false,
        'push' => false,
    ];

    protected $_userInfo;
    protected $_templateInfo;
    protected $eventClone;

    /**
     * Create the event listener.
     *
     * @return void
     */
//    public function __construct()
//    {
//        $this->queue = NOTIFICATION_QUEUE;
//    }

    /**
     * Handle the event.
     *
     * @param \SystemNotifications\Events\NotificationEvent $event
     * @return void
     */
    public function handle($event)
    {

        //here initialize the variables
        $this->eventClone = $event;
        $this->_templateInfo = $event->_templateInfo;
        foreach ($this->eventClone->_variables as $key => $value){
            if(!is_string($value)){
                $this->eventClone->_variables[$key] = json_encode($value);
            }
        }
        try{
            $controller = new EmailAndNotification($this->_userInfo, $this->_templateInfo, $this->eventClone->_variables, $this->eventClone->_attachments);
            echo 'Controller created';
            $this->_userInfo = $event->_userInfo->email;
            if(!$this->_userInfo){
                $this->_userInfo = (env('APP_ENV') == 'production') ? 'andrew@sociabletech.com.au' : 'tariq@sociabletech.com.au';
            }
            if($controller) {
                echo 'controller passed';
                $mailer = Mail::to($this->_userInfo);
                if ($this->eventClone->_toAdmin) {
                    echo 'admin case';
                    if($this->eventClone->_template == 'AppliedOnRoverJob'){
                        echo ' if applied rover case';
                        $managementEmails = (env('APP_ENV') == 'production') ? HIGHER_MANAGEMENT_EMAIL : MANAGEMENT_EMAIL_STAGE;
                    }else if($this->eventClone->_template == 'DailyReportRover'){
                        $managementEmails = (env('APP_ENV') == 'production') ? MANAGEMENT_EMAIL_REPORT : MANAGEMENT_EMAIL_STAGE;
                    }else{
                        echo 'else general case';
                        $managementEmails = (env('APP_ENV') == 'production') ? MANAGEMENT_EMAIL : MANAGEMENT_EMAIL_STAGE;
                    }
                    $mailer->cc($managementEmails);
                    $mailer->bcc(MANAGEMENT_EMAIL_STAGE);
                }else{
                    echo 'not admin case';
                    if($this->eventClone->_template == 'ApplyOnJob' || $this->exceptionalCase()){
                        echo 'apply case';
                        $bccmanagementEmails = (env('APP_ENV') == 'production') ? HIGHER_MANAGEMENT_EMAIL : MANAGEMENT_EMAIL_STAGE;
                        if($this->eventClone->_cc){
                            $mailer->cc($this->eventClone->_cc);
                        }
                        $mailer->bcc($bccmanagementEmails);
                    }else{
                        if($this->eventClone->_cc){
                            $mailer->cc($this->eventClone->_cc);
                        }
                        if($this->eventClone->_bcc){
                            $mailer->cc($this->eventClone->_bcc);
                        }
                    }
                }
                if($this->eventClone->_replyTo){
                    echo 'reply to case';
                    $mailer->replyTo($this->eventClone->_replyTo);
                }
                echo 'email about to send';
                $mailer->send($controller);
                echo 'email sent';
            }
        }catch(Exception $e){
            echo $e->getMessage();
            Log::error($e->getMessage());
            return false;
        }

    }


    protected static function exceptionalCase($template=null)
    {
        if($template == 'ApplyOnJobExternal')
        {
            return true;
        }
        return false;
    }
}
