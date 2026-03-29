<?php

declare(strict_types=1);

final class JsonResponse
{
    public static function send(int $status, array $body = []): void
    {
        http_response_code($status);
        if ($status === 204) {
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public static function error(int $status, string $message, ?string $code = null): void
    {
        $payload = ['error' => $message];
        if ($code !== null) {
            $payload['code'] = $code;
        }
        self::send($status, $payload);
    }
}
