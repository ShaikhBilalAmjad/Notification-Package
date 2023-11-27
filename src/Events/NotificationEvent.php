<?php

namespace SystemNotifications\Events;

use Illuminate\Support\Facades\Event;

class NotificationEvent extends Event
{
    public $_userType;
    public $_userId;
    public $_userInfo;
    public $_template;
    public $_templateInfo;
    public $_variables;
    public $_toAdmin;
    public $_attachments;
    public $_replyTo;
    public $_cc;
    public $_bcc;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($userInfo, $userType,$userId,$template,$templateInfo,$variables, $toAdmin = false, $attachment = null, $replyTo = null,$cc = null,$bcc = null)
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
}
