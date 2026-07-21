<?php
declare(strict_types=1);

class PushController
{
    public function __construct(
        private PushSubscriptionRepository $pushRepo
    ) {}

    public function subscribe(): void
    {
        AdminAuth::requireAuth();

        $adminId = (int)($_SESSION['admin_id'] ?? 0);
        $body = (string)file_get_contents('php://input');
        $data = json_decode($body, true);

        $endpoint = (string)($data['endpoint'] ?? '');
        $p256dh   = (string)($data['keys']['p256dh'] ?? '');
        $auth     = (string)($data['keys']['auth'] ?? '');

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid subscription data']);
            return;
        }

        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $this->pushRepo->upsert($adminId, $endpoint, $p256dh, $auth, $ua);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function unsubscribe(): void
    {
        AdminAuth::requireAuth();

        $body = (string)file_get_contents('php://input');
        $data = json_decode($body, true);
        $endpoint = (string)($data['endpoint'] ?? '');

        if ($endpoint !== '') {
            $this->pushRepo->deleteByEndpoint($endpoint);
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function vapidPublic(): void
    {
        AdminAuth::requireAuth();

        $key = (string)(getenv('VAPID_PUBLIC') ?: '');
        header('Content-Type: application/json');
        echo json_encode(['publicKey' => $key]);
    }
}
