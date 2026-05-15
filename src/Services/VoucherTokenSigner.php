<?php

namespace Reach\StatamicResrvVouchers\Services;

class VoucherTokenSigner
{
    public function __construct(private readonly string $key)
    {
        if ($key === '') {
            throw new \InvalidArgumentException('VoucherTokenSigner requires a non-empty key.');
        }
    }

    public static function fromConfig(): self
    {
        $key = config('resrv-vouchers.signing_key') ?: config('app.key');

        if (is_string($key) && str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded === false) {
                throw new \InvalidArgumentException('VoucherTokenSigner could not base64-decode the configured key.');
            }
            $key = $decoded;
        }

        return new self((string) $key);
    }

    public function sign(string $uuid): string
    {
        $signature = hash_hmac('sha256', $uuid, $this->key, true);

        return $this->base64UrlEncode($uuid).'.'.$this->base64UrlEncode($signature);
    }

    public function verify(string $token): ?string
    {
        if (! str_contains($token, '.')) {
            return null;
        }

        [$encodedUuid, $encodedSignature] = explode('.', $token, 2);

        $uuid = $this->base64UrlDecode($encodedUuid);
        $signature = $this->base64UrlDecode($encodedSignature);

        if ($uuid === null || $signature === null) {
            return null;
        }

        $expected = hash_hmac('sha256', $uuid, $this->key, true);

        return hash_equals($expected, $signature) ? $uuid : null;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $padded = strtr($value, '-_', '+/');
        $padding = strlen($padded) % 4;
        if ($padding > 0) {
            $padded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
