<?php

namespace SystemNotifications\Helpers\Notifications;


use App\Models\Seeker;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SystemNotifications\Events\NotificationEvent;
use SystemNotifications\Jobs\EmailNotificationJob;
use SystemNotifications\Jobs\PushNotificationJob;
use SystemNotifications\Traits\Notification\FCM;
use Illuminate\Support\Stringable;

/**
 * Class NotificationHelper
 * @package App\Helpers\Notifications
 *
 * Helper class for handling email and push notifications.
 */
class NotificationHelper
{
    use FCM;

    /**
     * The data table mapping user types to their respective table names.
     *
     * @var array
     */
    protected static $dataTable = [
        'employer' => 'employers',
        'seeker' => 'seekers',
        'recruiter' => 'recruiters',
        'user' => 'users'
    ];
    /**
     * The process action flags for email and push.
     *
     * @var array
     */
    protected static $processAction = [
        'email' => false,
        'push' => false,
    ];

    /**
     * Maps the user type to the corresponding model class.
     *
     * @param string $userType
     * @return User
     */
    protected static function mappedClass($userType)
    {
        $types = [
            EMPLOYER => new \App\Models\Employer,
            RECRUITER => new \App\Models\Recruiter,
            //SEEKER => new Recruiter,
            SEEKER => new \App\Models\Seeker,
        ];
        return $types[$userType] ?? new User();
    }


    protected static $eventClone;

    protected static $userInfo;
    protected static $userInfoCC;
    protected static $userInfoBCC;
    protected static $templateInfo;
    protected static $emailTemplateInfo;
    protected static $pushTemplateInfo;

    /**
     * Initializes the notification process.
     *
     * @param string $user_type
     * @param int $user_id
     * @param string $template
     * @param array $macros
     * @param bool $toAdmin
     * @param mixed $attachment
     * @param mixed $replyTo
     * @return bool
     */

    public static function initializeNotification($user_type, $user_id, $template, $macros, $toAdmin = false, $attachment = null, $replyTo = null)
    {
        try {

            // Retrieve user information, email template, and push template
            self::$userInfo = self::retrieveUserInfo($user_type, $user_id, $template);
            self::$emailTemplateInfo = self::getEmailNotificationTemplate($template);
            self::$pushTemplateInfo = self::getPushNotificationTemplate($template);


            // Ensure macros values are strings
            foreach ($macros as $key => $value) {
                if (!is_string($value)) {
                    $macros[$key] = json_encode($value);
                }
            }

            // Check notification permissions and process email/push notifications
            self::retrieveAndCheckNotificationPermission($user_type, $user_id, $template);
            self::processEmailAndPushNotifications($user_type, $user_id, $template, $macros, $toAdmin, $attachment, $replyTo);

            return true;
        } catch (Exception $e) {
            Log::error("Error while sending notification" . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves an email or notification template from the database.
     *
     * @param string $template
     * @return mixed
     */
    protected static function getTemplate($template)
    {
        return DB::table('email_and_notification_template')->where('module', $template)->first();
    }


    /**
     * Processes the email notification.
     *
     * @param string $userType
     * @param int $userId
     * @param string $template
     * @param mixed $templateInfo
     * @param array $macros
     * @param bool $toAdmin
     * @param mixed $attachment
     * @param mixed $replyTo
     */
    protected static function processEmail($userType, $userId, $template, $templateInfo, $macros, $toAdmin, $attachment, $replyTo)
    {
        try {
            // Trigger the NotificationEvent event

            dispatch(new EmailNotificationJob(self::$userInfo, $userType, $userId, $template, $templateInfo, $macros, $toAdmin, $attachment, $replyTo, self::$userInfoCC, self::$userInfoBCC));
//            event(new NotificationEvent(self::$userInfo, $userType, $userId, $template, $templateInfo, $macros, $toAdmin, $attachment, $replyTo, self::$userInfoCC, self::$userInfoBCC));
        } catch (Exception $e) {
            log::error($e->getMessage());
        }
    }

    /**
     * Processes the push notification.
     *
     * @param string $userType
     * @param int $userId
     * @param array $macros
     * @param string $template
     */
    protected static function processPush($userType, $userId, $macros, $template)
    {
        Log::debug("processPush $userType, ... $userId");

        // Initialize variables
        $title = "";
        $message = "";

        $tinfo = self::$pushTemplateInfo;
        $tinfo->content = null;

        try {
            // Check conditions before processing push
            if (is_int($userId) && self::$userInfo && (bool)self::$pushTemplateInfo->user_enabled) {
                $referenceId = $macros['reference_id'] ?? $macros['refrence_id'] ?? null;

                $title = self::replaceMacros($tinfo->title, $macros);
                $message = self::replaceMacros($tinfo->content, $macros);

                // Insert push notification data into the database

                DB::beginTransaction();

                try {
                    $dataInsert = [
                        'title' => $title,
                        'message' => $message,
                        'source' => 'system bot',
                        'destination' => ($userType == SEEKER) ? $userType : EMPLOYER,
                        'user_id' => self::$userInfo->id,
                        'reference_id' => $referenceId,
                        'payload' => $macros['payload'] ?? null,
                        'is_read' => 0,
                        'notification_template_id' => self::$pushTemplateInfo->id,
                        'notification_type' => $template,
                        'job_id' => $referenceId,
                        'content_type' => true,
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ];

                    DB::table('user_notifications')->insert($dataInsert);

                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollback();
                    throw $e; // Re-throw the exception after rolling back
                }
            }
        } catch (\Throwable $e) {
            Log::debug("user id # " . self::$userInfo->id . ' facing error => ' . $e->getMessage());
        }

        Log::debug('check before send - self::$userInfo: ' . json_encode(self::$userInfo));

        // Send push notification
        if (!empty(self::$userInfo) && self::$processAction['push']) {
            $fcmTokens = self::$userInfo->fcm_tokens->pluck('token')->toArray();
            $userFcmToken = self::$userInfo->fcm_token ?? '';

            Log::debug('fcm tokens: ' . json_encode($fcmTokens));

            try {
                $fcmTrait = new self();

                if (!empty($fcmTokens)) {
                    Log::debug('user has fcm tokens: ' . json_encode($fcmTokens));

                    // Send push to user login session tables token

                    dispatch(new PushNotificationJob($fcmTokens,$title, $message));
                } else {
                    if (!empty($userFcmToken)) {
                        // Send push to user table token
                        Log::debug('fcm token: ' . $userFcmToken . ' message: ' . $message);
                        dispatch(new PushNotificationJob([$userFcmToken],$title, $message));

                    }
                }
            } catch (\Throwable $e) {
                Log::debug("user id # " . self::$userInfo->id . ' facing error => ' . $e->getMessage());
            }
        }
    }

    /**
     * Checks if the provided template is an exceptional case and performs actions accordingly.
     *
     * @param string $template The notification template.
     * @return bool Returns true if it's an exceptional case, otherwise false.
     */
    protected static function exceptionalCase($template)
    {
        if ($template == 'AggregationPartnerConfirmation' || $template == 'EnterprisePartnerConfirmation') {
            // Set userInfoCC based on the environment for specific templates
            self::$userInfoCC = (env('APP_ENV') == 'production') ? PARTNER_CONFIRM_EMAIL : PARTNER_CONFIRM_EMAIL_STAGE;
        }

        if ($template == 'AggregationPartner') {
            // Reset userInfoCC for 'AggregationPartner' template
            self::$userInfoCC = [];
        }

        if (in_array($template, EXCEPTIONAL_EMAIL_TEMPLATE)) {
            // Set email and push process actions for exceptional email templates
            self::$processAction['email'] = true;
            self::$processAction['push'] = false;
            return true;
        }

        return false;
    }

    /**
     * Retrieves the list of users (CompanyAdmin, CompanySuperAdmin, and ConcernPerson) based on the provided user type, user ID, and template.
     *
     * @param string $userType The type of user (e.g., 'employer', 'recruiter').
     * @param int $userId The ID of the user.
     * @param string $template The notification template.
     * @return array An associative array containing CompanyAdmin, CompanySuperAdmin, and ConcernPerson for the user based on the template.
     */
    protected static function getUsersForEmail($userType, $userId, $template)
    {
        switch ($template) {
            case 'JobAboutToRenew':
            case 'JobRenew':
            case 'JobAboutToExpired':
            case 'FeaturedJobExpired':
            case 'ApplyOnJob':
                // Retrieve users according to ConcernPerson for specific job-related templates
                return self::getListOfUserAccordingToConcernPerson($userType, $userId, $template);

            case 'TransactionInvoice':
            case 'SubcriptionAboutToRenew':
            case 'FoundationPackageSubscribe':
            case 'SubscriptionAboutToExpired':
            case 'AppliedJob':
            case 'ViewedJob':
            case 'SubscriptionExpired':
            case 'SubscriptionRenew':
            case 'FreeSubscription':
            case 'CancelSubscription':
            case 'PlanReSubscription':
            case 'SubscriptionRenewFailed':
            case 'JobExpiredGrouped':
            case 'FoundationSubscriptionRenewFailed':
                // Retrieve users according to CompanyAdmin for various subscription-related templates
                return self::getListOfUserAccordingToCompanyAdmin($userType, $userId, $template);

            default:
                // Retrieve only the user for other templates
                return self::getUserOnly($userType, $userId, $template);
        }
    }

    /**
     * Retrieves the CompanyAdmin, CompanySuperAdmin, and ConcernPerson based on the ConcernPerson's user type, user ID, and template.
     *
     * @param string $userType The type of user (e.g., 'employer', 'recruiter') for the ConcernPerson.
     * @param int $userId The ID of the ConcernPerson user.
     * @param string $template The notification template.
     * @return array An associative array containing CompanyAdmin, CompanySuperAdmin, and ConcernPerson for the ConcernPerson.
     */
    protected static function getListOfUserAccordingToConcernPerson($userType, $userId, $template)
    {
        $CompanySuperAdmin = null;
        $CompanyAdmin = null;
        $concernPerson = null;

        // Retrieve the ConcernPerson based on their user type and user ID
        $user = self::mappedClass($userType)->where('id', $userId)->first();

        if ($user) {
            // Log the details of the ConcernPerson user
            Log::debug(json_encode($user));

            // Check if the ConcernPerson has permission and set ConcernPerson accordingly
            $concernPerson = (self::checkPermission($userType, $userId, $template)) ? $user : null;

            // Retrieve the company associated with the ConcernPerson
            $company = DB::table(self::$dataTable[$userType])->where('id', $user->default_company_id)->first();

            if ($company) {
                // Check if the company source is not 'rover'
                if (!((($userType == 'employer') || ($userType == 'recruiter')) && $company->source == 'rover')) {
                    // Retrieve CompanyAdmin and CompanySuperAdmin using the getAdminAndSuperAdmin method
                    $companyManagement = self::getAdminAndSuperAdmin($company, $template, $userType);
                    $CompanyAdmin = $companyManagement['CompanyAdmin'];
                    $CompanySuperAdmin = $companyManagement['CompanySuperAdmin'];
                } else {
                    // Reset ConcernPerson to null if the source is 'rover'
                    $concernPerson = null;
                }
            }
        }

        // Return CompanyAdmin, CompanySuperAdmin, and ConcernPerson for the ConcernPerson in an associative array
        return ['CompanyAdmin' => $CompanyAdmin, 'CompanySuperAdmin' => $CompanySuperAdmin, 'concernPerson' => $concernPerson];
    }

    /**
     * Retrieves the CompanyAdmin, CompanySuperAdmin, and ConcernPerson based on the user type, user ID, and template.
     *
     * @param string $userType The type of user (e.g., 'employer', 'recruiter').
     * @param int $userId The ID of the user.
     * @param string $template The notification template.
     * @return array An associative array containing CompanyAdmin, CompanySuperAdmin, and ConcernPerson.
     */
    protected static function getListOfUserAccordingToCompanyAdmin($userType, $userId, $template)
    {
        $CompanySuperAdmin = null;
        $CompanyAdmin = null;
        $concernPerson = null;

        // Retrieve the company based on user type and user ID
        $company = self::mappedClass($userType)->where('id', $userId)->first();

        if ($company) {
            // Check if the company source is not 'rover'
            if (!((($userType == 'employer') || ($userType == 'recruiter')) && $company->source == 'rover')) {
                // Retrieve CompanyAdmin and CompanySuperAdmin using the getAdminAndSuperAdmin method
                $companyManagement = self::getAdminAndSuperAdmin($company, $template, $userType);
                $CompanyAdmin = $companyManagement['CompanyAdmin'];
                $CompanySuperAdmin = $companyManagement['CompanySuperAdmin'];
            }
        }

        // Return CompanyAdmin, CompanySuperAdmin, and ConcernPerson in an associative array
        return ['CompanyAdmin' => $CompanyAdmin, 'CompanySuperAdmin' => $CompanySuperAdmin, 'concernPerson' => $concernPerson];
    }

    /**
     * Retrieves only the ConcernPerson based on the user type, user ID, and template.
     *
     * @param string $userType The type of user (e.g., 'employer', 'recruiter').
     * @param int $userId The ID of the user.
     * @param string $template The notification template.
     * @return array An associative array containing only ConcernPerson.
     */
    protected static function getUserOnly($userType, $userId, $template)
    {
        $CompanySuperAdmin = null;
        $CompanyAdmin = null;
        $concernPerson = null;

        // Retrieve the company based on user type and user ID
        $company = self::mappedClass($userType)->where('id', $userId)->first();

        if ($company) {
            // Set ConcernPerson to the retrieved company
            $concernPerson = $company;
        }

        // Return only ConcernPerson in an associative array
        return ['CompanyAdmin' => $CompanyAdmin, 'CompanySuperAdmin' => $CompanySuperAdmin, 'concernPerson' => $concernPerson];
    }

    /**
     * Retrieves the CompanyAdmin and CompanySuperAdmin based on the company, template, and user type.
     *
     * @param mixed $company The company object.
     * @param string $template The notification template.
     * @param string $userType The type of user (RECRUITER, EMPLOYER).
     * @return array An associative array containing CompanyAdmin and CompanySuperAdmin.
     */
    protected static function getAdminAndSuperAdmin($company, $template, $userType)
    {
        $CompanyAdmin = null;
        $CompanySuperAdmin = null;

        // Switch based on user type
        switch ($userType) {
            case RECRUITER:
                $CompanyAdmin = ($company->admin && self::checkPermission(USER, $company->admin->id, $template)) ? $company->admin : null;
                break;
            case EMPLOYER:
                $CompanyAdmin = ($company->admin && self::checkPermission(USER, $company->admin->id, $template)) ? $company->admin : null;

                // Check if the company has a parent company
                if ($company->parent_company_id) {
                    // Retrieve the parent company
                    $parentCompany = self::mappedClass($userType)->where('id', $company->parent_company_id)->first();

                    // Check permission for the parent company admin
                    $CompanySuperAdmin = ($parentCompany->admin && self::checkPermission(USER, $parentCompany->admin->id, $template)) ? $parentCompany->admin : null;
                }
                break;
        }

        // Return the CompanyAdmin and CompanySuperAdmin in an associative array
        return ['CompanyAdmin' => $CompanyAdmin, 'CompanySuperAdmin' => $CompanySuperAdmin];
    }


    /**
     * Checks the notification permission for a user based on user type, user ID, and notification template.
     *
     * @param string $userType The type of user (SEEKER, EMPLOYER, RECRUITER).
     * @param int $userId The ID of the user.
     * @param string $template The notification template.
     * @return bool Whether the user has permission for the specified notification.
     */
    protected static function checkPermission($userType, $userId, $template)
    {
        // Normalize the user type to lowercase if not null, defaulting to 'employer'
        $userType = !is_null($userType) ? Str::lower($userType) : 'employer';

        // Check if user ID is an integer and user type is not empty
        if (gettype($userId) == 'integer' && !empty($userType)) {
            // Retrieve notification permission from the database
            $notificationPermission = DB::table('notification_switches')
                ->whereIn('user_type', ($userType == SEEKER) ? [SEEKER] : [EMPLOYER, RECRUITER])
                ->where('user_id', $userId)
                ->where('notification_id', self::$templateInfo->id)
                ->first();
        } else {
            $notificationPermission = null;
        }

        // Check for exceptional case (ApplyOnJobExternal)
        if ($template == 'ApplyOnJobExternal') {
            return true;
        }

        // If permissions not found, check if email is available and build
        if (!$notificationPermission) {
            if (!(boolean)self::$templateInfo->is_editable && (boolean)self::$templateInfo->email_available) {
                return true;
            }
        } else {
            // If permissions found, retrieve user information and check email availability
            self::$userInfo = User::find($userId);

            if ((boolean)self::$userInfo->email_notification && (boolean)self::$templateInfo->email_available && $notificationPermission->email_value) {
                return true;
            }
        }

        return false;
    }


    /**
     * Retrieves user information based on user type, user ID, and notification template.
     *
     * @param string $userType The type of user (SEEKER, EMPLOYER, RECRUITER).
     * @param int $userId The ID of the user.
     * @param string $template The notification template.
     * @return mixed|null The user information or null if not found.
     */
    protected static function retrieveUserInfo($userType, $userId, $template)
    {
        // If the user type is SEEKER, retrieve and return the user by ID directly
        if ($userType == SEEKER) {
            return self::mappedClass($userType)->where('id', $userId)->first();
        }

        // If the user type is not null, retrieve user information based on email template
        if (!is_null($userType)) {
            $usersList = self::getUsersForEmail($userType, $userId, $template);

            $userInfo = null;

            // Determine the user information based on the priority order
            if (!empty($usersList['concernPerson'])) {
                self::$userInfoCC = $usersList['CompanyAdmin'];
                self::$userInfoBCC = $usersList['CompanySuperAdmin'];
                $userInfo = $usersList['concernPerson'];
            } elseif (!empty($usersList['CompanyAdmin'])) {
                self::$userInfoCC = $usersList['CompanySuperAdmin'];
                $userInfo = $usersList['CompanyAdmin'];
            } elseif (!empty($usersList['CompanySuperAdmin'])) {
                $userInfo = $usersList['CompanySuperAdmin'];
            }

            return $userInfo;
        }

        return null;
    }


    /**
     * Retrieves the email notification template based on the provided template slug.
     *
     * @param string $template The slug of the email notification template.
     * @return mixed The email notification template or null if not found.
     */
    protected static function getEmailNotificationTemplate($template)
    {
        return DB::table('email_notifications')->where('slug', $template)->first();
    }

    /**
     * Retrieves the push notification template based on the provided template slug.
     *
     * @param string $template The slug of the push notification template.
     * @return mixed The push notification template or null if not found.
     */
    protected static function getPushNotificationTemplate($template)
    {
        return DB::table('push_notifications')->where('slug', $template)->first();
    }


    /**
     * Retrieves and checks notification permissions based on user type, user ID, and notification template.
     *
     * @param string $userType The type of user (e.g., SEEKER, EMPLOYER, RECRUITER).
     * @param int $userId The ID of the user.
     * @param string $template The notification template.
     */
    protected static function retrieveAndCheckNotificationPermission($userType, $userId, $template)
    {
        // Check for exceptional cases where certain actions are predefined
        if (self::exceptionalCase($template)) {
            return;
        }

        // Retrieve user information if not already available
        if (!self::$userInfo && $userId && !is_array($userId) && gettype($userId) == 'integer') {
            self::$userInfo = ($userType == SEEKER) ? Seeker::find($userId) : User::find($userId);
        }

        // Retrieve email notification permission
        if (self::$userInfo && !is_array($userId) && gettype($userId) == 'integer') {
            $emailNotificationPermission = DB::table('user_enable_notifications')
                ->whereIn('user_type', ($userType == SEEKER) ? [SEEKER] : [EMPLOYER, RECRUITER])
                ->where('user_id', self::$userInfo->id)
                ->where('notifiable_id', self::$emailTemplateInfo->id)
                ->where('notifiable_type', 'App\Models\Notification\EmailNotification')
                ->first();
        } else {
            $emailNotificationPermission = null;
        }

        // Retrieve push notification permission
        if (self::$userInfo && !is_array($userId) && gettype($userId) == 'integer') {
            $pushNotificationPermission = DB::table('user_enable_notifications')
                ->whereIn('user_type', ($userType == SEEKER) ? [SEEKER] : [EMPLOYER, RECRUITER])
                ->where('user_id', self::$userInfo->id)
                ->where('notifiable_id', self::$emailTemplateInfo->id)
                ->where('notifiable_type', 'App\Models\Notification\PushNotification')
                ->first();
        } else {
            $pushNotificationPermission = null;
        }

        // Check and set process actions based on permissions
        if (!$emailNotificationPermission) {
            self::$processAction['email'] = !(boolean)self::$emailTemplateInfo->user_enabled && (boolean)self::$emailTemplateInfo->status == "Publish";
            self::$processAction['push'] = self::$userInfo && !(boolean)self::$pushTemplateInfo->user_enabled && (boolean)self::$pushTemplateInfo->status == "Publish";
        } else {
            $emailEnabled = self::$userInfo && (boolean)self::$userInfo->email_notification;
            $pushEnabled = self::$userInfo && (boolean)self::$userInfo->push_notification;

            // Check if email is available and build
            self::$processAction['email'] = $emailEnabled &&
            (boolean)self::$emailTemplateInfo->user_enabled &&
            $emailNotificationPermission->is_active ?? false;

            // Check if push is available and build
            self::$processAction['push'] = $pushEnabled &&
            (boolean)self::$pushTemplateInfo->user_enabled &&
            $pushNotificationPermission->is_active ?? false;
        }
    }

    /**
     * Processes both email and push notifications based on the specified conditions.
     *
     * @param string $user_type
     * @param int $user_id
     * @param string $template
     * @param array $macros
     * @param bool $toAdmin
     * @param mixed $attachment
     * @param mixed $replyTo
     */
    protected static function processEmailAndPushNotifications($user_type, $user_id, $template, $macros, $toAdmin, $attachment, $replyTo)
    {
        // Process email notification
        self::processEmail($user_type, $user_id, $template, self::$emailTemplateInfo, $macros, $toAdmin, $attachment, $replyTo);

        // Process push notification if applicable
        if (self::$processAction['push']) {
            self::processPush($user_type, $user_id, $macros, $template);
        }
    }


    public static function replaceMacros($template, array $macros = [])
    {

        foreach($macros as $key => $value)
        {
            $str = new Stringable($template);
            $template = $str->replace('{{$'.$key.'}}',$value);
        }

        return $template;
    }


}
