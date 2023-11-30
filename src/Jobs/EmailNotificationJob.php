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

class EmailNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
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
     * Execute the job.
     *
     * @return void
     */

    protected $_userType;
    protected $_userId;
    protected $_template;
    protected $_variables;
    protected $_toAdmin;
    protected $_attachments;
    protected $_replyTo;
    protected $_cc;
    protected $_bcc;

    public function __construct($userInfo, $userType, $userId, $template, $templateInfo, $variables, $toAdmin = false, $attachment = null, $replyTo = null, $cc = null, $bcc = null)
    {
        $this->_userType = $userType;
        $this->_userInfo = $userInfo;
        $this->_userId = $userId;
        $this->_template = $template;
        $this->_variables = $variables;
        $this->_toAdmin = $toAdmin;
        $this->_attachments = $attachment;
        $this->_replyTo = $replyTo;
        $this->_templateInfo = $templateInfo;
        $this->_cc = $cc;
        $this->_bcc = $bcc;
    }
    public function handle()
    {
        // Access job data using $this->eventClone, $this->_userInfo, $this->_templateInfo
        Log::info('EmailNotificationJob handled successfully.');
        // here initialize the variables


        foreach ($this->_variables as $key => $value) {
            if (!is_string($value)) {
                $this->_variables[$key] = json_encode($value);
            }
        }

        try {
            $controller = new EmailAndNotification(
                $this->_userInfo,
                $this->_templateInfo,
                $this->_variables,
                $this->_attachments
            );

            $this->_userInfo = $this->_userInfo->email;

            if (!$this->_userInfo) {
                $this->_userInfo = (env('APP_ENV') == 'production') ? 'andrew@sociabletech.com.au' : 'tariq@sociabletech.com.au';
            }

            if ($controller) {
                $mailer = Mail::to($this->_userInfo);

                if ($this->_toAdmin) {
                    $managementEmails = (env('APP_ENV') == 'production') ? HIGHER_MANAGEMENT_EMAIL : MANAGEMENT_EMAIL_STAGE;
                    $mailer->cc($managementEmails);
                    $mailer->bcc(MANAGEMENT_EMAIL_STAGE);
                } else {
                    if ($this->_template == 'ApplyOnJob' || $this->exceptionalCase()) {
                        $bccmanagementEmails = (env('APP_ENV') == 'production') ? HIGHER_MANAGEMENT_EMAIL : MANAGEMENT_EMAIL_STAGE;
                        if ($this->_cc) {
                            $mailer->cc($this->_cc);
                        }
                        $mailer->bcc($bccmanagementEmails);
                    } else {
                        if ($this->_cc) {
                            $mailer->cc($this->_cc);
                        }
                        if ($this->_bcc) {
                            $mailer->cc($this->_bcc);
                        }
                    }
                }

                if ($this->_replyTo) {
                    $mailer->replyTo($this->_replyTo);
                }

                $mailer->send($controller);
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
