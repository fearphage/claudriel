<?php

declare(strict_types=1);

namespace Claudriel\Domain\Chat\Tool;

/**
 * Shared Google API HTTP helpers for agent tools.
 *
 * Uses file_get_contents with stream context (no curl_exec per pre-push hook).
 */
trait GoogleApiTrait
{
    private function googleApiGet(string $url, string $accessToken): array
    {
        $context = stream_context_create([
            'http' => [
                'header' => "Authorization: Bearer {$accessToken}\r\n",
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['error' => 'Google API request failed'];
        }

        /** @phpstan-ignore isset.variable, booleanAnd.alwaysTrue, function.alreadyNarrowedType */
        $statusCode = isset($http_response_header) ? $this->parseHttpStatusCode($http_response_header) : 0;

        return $this->parseGoogleResponse($response, $statusCode);
    }

    private function googleApiPost(string $url, string $accessToken, array $data): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
                'content' => json_encode($data, JSON_THROW_ON_ERROR),
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['error' => 'Google API request failed'];
        }

        /** @phpstan-ignore isset.variable, booleanAnd.alwaysTrue, function.alreadyNarrowedType */
        $statusCode = isset($http_response_header) ? $this->parseHttpStatusCode($http_response_header) : 0;

        return $this->parseGoogleResponse($response, $statusCode);
    }

    /**
     * @param  list<string>  $headers
     */
    private function parseHttpStatusCode(array $headers): int
    {
        if ($headers === []) {
            return 0;
        }

        if (preg_match('/HTTP\/\S+\s+(\d{3})/', $headers[0], $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function parseGoogleResponse(string $response, int $statusCode): array
    {
        $decoded = json_decode($response, true) ?? ['error' => 'Invalid Google API response'];

        if ($statusCode >= 400 && isset($decoded['error'])) {
            $error = $decoded['error'];
            $message = is_array($error) ? ($error['message'] ?? 'Unknown error') : (string) $error;
            $code = is_array($error) ? ($error['code'] ?? $statusCode) : $statusCode;
            $errorStatus = is_array($error) ? ($error['status'] ?? '') : '';

            if ($statusCode === 401) {
                return ['error' => "Google authentication failed ({$code}): {$message}. Try reconnecting your Google account."];
            }

            if ($statusCode === 403) {
                if (str_contains($message, 'insufficient') || $errorStatus === 'PERMISSION_DENIED') {
                    return ['error' => "Google permission denied ({$code}): {$message}. Reconnect your Google account to grant the required scopes."];
                }

                return ['error' => "Google access forbidden ({$code}): {$message}"];
            }

            return ['error' => "Google API error ({$code}): {$message}"];
        }

        return $decoded;
    }
}
