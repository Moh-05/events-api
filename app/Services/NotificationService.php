<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Auth\Credentials\ServiceAccountCredentials;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Admin;
use App\Models\Notification;

class NotificationService
{
    protected string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = base_path(env('FIREBASE_CREDENTIALS'));
    }

    private function getAccessToken(): ?string
    {
        if (!file_exists($this->credentialsPath)) {
            Log::error("Firebase credentials file not found at: {$this->credentialsPath}");
            return null;
        }

        $scopes = ['https://www.googleapis.com/auth/cloud-platform'];

        try {
            $creds = new ServiceAccountCredentials($scopes, $this->credentialsPath);
            $authToken = $creds->fetchAuthToken();
            return $authToken['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error("Failed to generate Firebase Access Token: " . $e->getMessage());
            return null;
        }
    }

    public function send(string $fcmToken, string $title, string $body, array $data = []): array
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return [];
        }

        $config    = json_decode(file_get_contents($this->credentialsPath), true);
        $projectId = $config['project_id'] ?? null;

        if (!$projectId) {
            Log::error("Firebase Project ID could not be extracted.");
            return [];
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $payload = [
            'message' => [
                'token'        => $fcmToken,
                'notification' => ['title' => $title, 'body' => $body],
            ]
        ];

        if (!empty($data)) {
            $payload['message']['data'] = array_map('strval', $data);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json',
        ])->post($url, $payload);

        if (!$response->successful()) {
            Log::error("FCM Notification failed to send", $response->json());
            return ['sent' => false, 'firebase_response' => $response->json()];
        }

        return ['sent' => true];
    }

    public function notifyUser(User $user, string $title, string $body, array $data = []): array
    {
        // Always save to the inbox first (so the bell icon shows history)
        $this->saveToInbox('user', $user->id, $title, $body, $data);

        if (!$user->fcm_token) {
            // no device token yet — expected during testing before Flutter sends it
            return ['sent' => false, 'reason' => 'no_device_token', 'title' => $title, 'body' => $body];
        }

        $result          = $this->send($user->fcm_token, $title, $body, $data);
        $result['title'] = $title;
        $result['body']  = $body;
        return $result;
    }

    public function notifyVendor(Vendor $vendor, string $title, string $body, array $data = []): array
    {
        // Always save to the inbox first (so the bell icon shows history)
        $this->saveToInbox('vendor', $vendor->id, $title, $body, $data);

        if (!$vendor->fcm_token) {
            // no device token yet — expected during testing before Flutter sends it
            return ['sent' => false, 'reason' => 'no_device_token', 'title' => $title, 'body' => $body];
        }

        $result          = $this->send($vendor->fcm_token, $title, $body, $data);
        $result['title'] = $title;
        $result['body']  = $body;
        return $result;
    }

    // Store the notification in the DB so it can be listed later (bell icon)
    private function saveToInbox(string $type, int $id, string $title, string $body, array $data = []): void
    {
        Notification::create([
            'notifiable_type' => $type,
            'notifiable_id'   => $id,
            'title'           => $title,
            'body'            => $body,
            'data'            => $data ?: null,
        ]);
    }

    //for Admin based on role
    /*
    public function notifyAdmins(string $role, string $title, string $body): void
    {
        Admin::where('role', $role)
            ->whereNotNull('fcm_token')
            ->each(fn($admin) => $this->send($admin->fcm_token, $title, $body));
    }
    */
}
