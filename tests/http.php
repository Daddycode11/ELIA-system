<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $checks++;
    echo "PASS: $message\n";
}

function browser(): CurlHandle
{
    $handle = curl_init();
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15,
        CURLOPT_PROXY => '']);
    return $handle;
}

function request(CurlHandle $handle, string $path, ?array $fields = null): array
{
    global $baseUrl;
    curl_setopt($handle, CURLOPT_URL, $baseUrl . '/' . $path);
    curl_setopt($handle, CURLOPT_POST, $fields !== null);
    if ($fields !== null) {
        $multipart = (bool) array_filter($fields, fn ($value) => $value instanceof CURLFile);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $multipart ? $fields : http_build_query($fields));
    }
    $response = curl_exec($handle);
    if ($response === false) {
        throw new RuntimeException(curl_error($handle));
    }
    $size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    return ['status' => curl_getinfo($handle, CURLINFO_HTTP_CODE),
        'headers' => substr($response, 0, $size), 'body' => substr($response, $size)];
}

function token(array $response): string
{
    if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $response['body'], $match)) {
        throw new RuntimeException('CSRF field missing.');
    }
    return $match[1];
}
