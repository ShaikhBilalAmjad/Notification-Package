<?php

namespace SystemNotifications\Traits\Notification;

use Fcm\Device\Info;
use Fcm\DeviceGroup\Create;
use Fcm\DeviceGroup\Remove;
use Fcm\DeviceGroup\Update;
use Fcm\FcmClient;
use Fcm\Push\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

trait FCM
{
    /**
     * @var FcmClient
     */
    private $client;
    private $defaultDeviceId = "coUujjG_chI:APA91bGkecTtKu_8uKngXn7j7AoQZY_rizK0p3PNTpOUkabqPizNMfs_SccrQJxkOsTJmmchaiytgjSsZT9Y7ov4DX9Pvz4vp77_NkD52itntEbnUH6AHL8HMataWw-ogjs_Hvr3-TDU";

    public function __construct()
    {
        $serverKey = config('fcm.server_key');
        $senderId = config('fcm.sender_id');
        $this->client = new FcmClient($serverKey, $senderId);
    }

    public function sendFCM($fcmData, $fcm_token)
    {
        $serverKey = config('fcm.server_key');
        $senderId = config('fcm.sender_id');
        $fcmClickUrl = config('fcm.click_url');

        $client = new FcmClient($serverKey, $senderId);

        $data = null;
        try {
            $notification = new Notification();
            $notification
                ->addRecipient($fcm_token)
                ->setTitle("test title")
                ->setBody($fcmData['message'])
                ->setSound("default")
//                ->setIcon(url('Kyndryl_logo.png'))
                ->setClickAction($fcmClickUrl)
                ->setBadge(11);
            //    ->(addDataArray$fcmData['payloads']);
            // Log::info($notification);
            $clientResponse = $client->send($notification);
            Log::debug('FCM Notification Sent');
            return $clientResponse;
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'Error',
                'message' => $e->getMessage(),
                'data' => $data
            ]);
        }
    }

    /**
     * @param string $groupName
     * @return array|null
     */

    public function createGroups($groupName)
    {
        try {
            $newGroup = new Create($groupName);
            $newGroup->addDevice($this->defaultDeviceId);
            return $this->client->send($newGroup);
        } catch (Throwable $e) {
            activity('push-notification')
                ->withProperties(['group' => $groupName])
                ->log($e->getMessage() ?? "Issue With FCM Service. FCM is Not Creating Group.");
        }
    }

    /**
     * @param string $groupName
     * @param string $notificationKey
     * @param string $deviceId
     * @return array|null
     */
    public function addDeviceInGroups($groupName, $notificationKey, $deviceId)
    {
        try {
            $group = new Update($groupName, $notificationKey);
            $group->addDevice($deviceId);

            return $this->client->send($group);
        } catch (Throwable $e) {
            activity('push-notification')
                ->withProperties(['group' => $groupName, 'device_id' => $deviceId])
                ->log($e->getMessage() ?? "Issue With FCM Service. FCM is Not adding device in Group.");

            abort(422, $e->getMessage() ?? "Issue With FCM Service. FCM is Not adding device in Group.");
        }
    }

    /**
     * @param string $groupName
     * @param string $notificationKey
     * @param string $deviceId
     * @return void
     */
    public function removeDeviceInGroup($groupName, $notificationKey, $deviceId)
    {
        try {
            $group = new Remove($groupName, $notificationKey);
            $group->addDevice($deviceId);
            return $this->client->send($group);
        } catch (Throwable $e) {
            activity('push-notification')
                ->withProperties(['group' => $groupName, 'device_id' => $deviceId])
                ->log($e->getMessage() ?? "Issue With FCM Service. FCM is Not removing device in Group.");
            return [
                'success' => false,
                'message' => $e->getMessage() ?? "Issue With FCM Service. FCM is Not removing device in Group."
            ];
        }
    }

    public function checkDeviceIdsIsReal($deviceIds)
    {
        for ($i = 0; $i < count($deviceIds); $i++) {
            try {
                $info = new Info($deviceIds[$i], true);
                $this->client->send($info);
            } catch (Throwable $e) {
                return [
                    "success" => false,
                    "device_id" => $deviceIds[$i],
                ];
                break;
            }
        }
        return [
            "success" => true,
        ];
    }

    public function sendFCMNotification()
    {
        $title = "testing from curl";
        $body = "testing from curl";
        $notificationToken = "d9gSCwI89ESTZ82XtqrEvI:APA91bFCl1zSvx5i7ZAS6gUf5ArCm70wl48sIjCXCFxta6_aClngXeiZpvtQ8XKoeZY2aa_SDS0Kc5cb9CC81pjhmUWkJF7YixEHBQy2wEGNwyaZM1zgD7-wSFcLYoX86OdELSmrikDK";
        try {

            if ($notificationToken != '') {

                $msg = array(
                    'body' => $body,
                    'title' => $title,
                    'color' => '#000fff',
                );

                $fields = array(
                    'to' => $notificationToken,
                    'notification' => $msg
                );


                $headers = array(
                    'Authorization: key=' . config('app.api_access_key'),
                    'Content-Type: application/json'
                );
                #Send Reponse To FireBase Server
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
                $result = curl_exec($ch);
                // echo $result;
                curl_close($ch);

                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

}
