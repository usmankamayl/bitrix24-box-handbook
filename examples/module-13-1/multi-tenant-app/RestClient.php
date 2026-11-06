<?php

require_once __DIR__ . '/TokenProvider.php';

final class RestClient
{
    public function __construct(
        private readonly string $domain,
        private readonly TokenProvider $tokenProvider,
        private readonly int $maxRetries = 5,
    ) {
    }

    public function call(string $method, array $params = []): array
    {
        $attempt = 0;

        while (true) {
            $token = $this->tokenProvider->getToken();
            $response = $this->request($method, $params, $token);

            if (($response['error'] ?? null) === 'QUERY_LIMIT_EXCEEDED') {
                $attempt++;
                if ($attempt > $this->maxRetries) {
                    throw new RuntimeException("Rate limit retries exhausted for {$method}");
                }
                // Экспоненциальный backoff с джиттером — не долбим одновременно с другими воркерами
                $delayMs = (int) (100 * (2 ** $attempt) + random_int(0, 100));
                usleep($delayMs * 1000);
                continue;
            }

            if (($response['error'] ?? null) === 'expired_token') {
                $this->tokenProvider->forceRefresh();
                continue; // не считается попыткой rate-limit, но общий лимит ретраев всё равно есть
            }

            return $response;
        }
    }

    /**
     * @param array<string, array{method: string, params?: array}> $commands
     */
    public function batch(array $commands): array
    {
        if (count($commands) > 50) {
            throw new InvalidArgumentException('Batch limit is 50 commands per request');
        }

        $cmd = [];
        foreach ($commands as $key => $c) {
            $cmd[$key] = $c['method'] . '?' . http_build_query($c['params'] ?? []);
        }

        // batch — ОДИН запрос с точки зрения rate limit, даже если внутри 50 команд.
        // Для массовых операций это всегда предпочтительнее N отдельных call().
        return $this->call('batch', ['halt' => 0, 'cmd' => $cmd]);
    }

    private function request(string $method, array $params, string $token): array
    {
        $ch = curl_init("https://{$this->domain}/rest/{$method}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        return json_decode($raw, true) ?? ['error' => 'invalid_response'];
    }
}
