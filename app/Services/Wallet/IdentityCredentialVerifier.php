<?php

namespace App\Services\Wallet;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;

class IdentityCredentialVerifier
{
    /**
     * Verify a Node-issued JWT-VC against the locally pinned issuer trust anchor.
     */
    public function verify(string $token, string $expectedDid, string $expectedType): array
    {
        $token = trim($token);
        $expectedDid = trim($expectedDid);
        if ($token === '' || $expectedDid === '') {
            throw new RuntimeException('identity_credential_invalid');
        }

        ['issuer' => $issuer, 'jwks' => $jwks, 'revoked' => $revoked] = $this->trustBundle();
        $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
        $claims = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 32, JSON_THROW_ON_ERROR);
        if (($claims['iss'] ?? '') !== $issuer || ($claims['sub'] ?? '') !== $expectedDid) {
            throw new RuntimeException('identity_credential_subject_mismatch');
        }
        if (empty($claims['jti']) || empty($claims['exp']) || intval($claims['exp']) <= time() || (!empty($claims['nbf']) && intval($claims['nbf']) > time())) {
            throw new RuntimeException('identity_credential_expired');
        }
        $credentialHash = hash('sha256', (string)$claims['jti']);
        if (isset($revoked[$credentialHash])) {
            throw new RuntimeException('identity_credential_revoked');
        }

        $types = $claims['vc']['type'] ?? [];
        if (!is_array($types) || !in_array('VerifiableCredential', $types, true) || !in_array($expectedType, $types, true)) {
            throw new RuntimeException('identity_credential_type_mismatch');
        }
        if (($claims['vc']['credentialSubject']['id'] ?? '') !== $expectedDid) {
            throw new RuntimeException('identity_credential_subject_mismatch');
        }

        return $claims;
    }

    private function trustBundle(): array
    {
        $directory = rtrim(trim((string)config('dootask.identity_trust_dir', '')), DIRECTORY_SEPARATOR);
        if ($directory === '') throw new RuntimeException('identity_trust_directory_not_configured');
        $metadataPath = $directory . DIRECTORY_SEPARATOR . 'issuer-metadata.json';
        $jwksPath = $directory . DIRECTORY_SEPARATOR . 'jwks.json';
        $manifestPath = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
        $signaturePath = $directory . DIRECTORY_SEPARATOR . 'manifest.sig';
        $revocationsPath = $directory . DIRECTORY_SEPARATOR . 'revocations.json';
        foreach ([$metadataPath, $jwksPath, $manifestPath, $signaturePath, $revocationsPath] as $path) {
            if (!is_file($path) || !is_readable($path)) throw new RuntimeException('identity_trust_bundle_unavailable');
        }
        $metadata = json_decode((string)file_get_contents($metadataPath), true);
        $jwks = json_decode((string)file_get_contents($jwksPath), true);
        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        $signature = file_get_contents($signaturePath);
        $revocations = json_decode((string)file_get_contents($revocationsPath), true);
        if (!is_array($metadata) || !is_array($jwks) || !is_array($manifest) || !is_array($revocations) || !is_array($revocations['credentialHashes'] ?? null)) throw new RuntimeException('identity_trust_bundle_invalid');
        if (!hash_equals((string)($manifest['metadataSha256'] ?? ''), hash_file('sha256', $metadataPath))
            || !hash_equals((string)($manifest['jwksSha256'] ?? ''), hash_file('sha256', $jwksPath))
            || !hash_equals((string)($manifest['revocationsSha256'] ?? ''), hash_file('sha256', $revocationsPath))) {
            throw new RuntimeException('identity_trust_bundle_checksum_mismatch');
        }
        $fetchedAt = strtotime((string)($manifest['fetchedAt'] ?? ''));
        $maxAge = intval(config('dootask.identity_trust_max_age_seconds', 3600));
        if ($maxAge <= 0) $maxAge = 3600;
        if (!$fetchedAt || $fetchedAt > time() + 300 || time() - $fetchedAt > $maxAge) {
            throw new RuntimeException('identity_trust_bundle_stale');
        }
        $publicKey = $this->base64UrlDecode((string)config('dootask.identity_trust_manifest_public_key', ''));
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || !sodium_crypto_sign_verify_detached($signature, (string)file_get_contents($manifestPath), $publicKey)) {
            throw new RuntimeException('identity_trust_bundle_signature_invalid');
        }
        $issuer = trim((string)($metadata['issuer'] ?? ''));
        $expectedIssuer = trim((string)config('dootask.identity_trust_issuer_did', ''));
        if ($issuer === '' || $issuer !== ($manifest['issuer'] ?? '') || $issuer !== ($revocations['issuer'] ?? '') || $expectedIssuer === '' || $issuer !== $expectedIssuer) throw new RuntimeException('identity_issuer_invalid');
        if (!is_array($jwks['keys'] ?? null) || $jwks['keys'] === []) throw new RuntimeException('identity_issuer_jwks_invalid');
        $revocationsAt = strtotime((string)($revocations['generatedAt'] ?? ''));
        if (!$revocationsAt || $revocationsAt > time() + 300 || time() - $revocationsAt > 1800) throw new RuntimeException('identity_trust_bundle_stale');
        foreach ($revocations['credentialHashes'] as $credentialHash) {
            if (!is_string($credentialHash) || !preg_match('/^[a-f0-9]{64}$/', $credentialHash)) throw new RuntimeException('identity_trust_bundle_invalid');
        }
        return ['issuer' => $issuer, 'jwks' => $jwks, 'revoked' => array_fill_keys($revocations['credentialHashes'], true)];
    }

    private function base64UrlDecode(string $value): string
    {
        $value = strtr(trim($value), '-_', '+/');
        $decoded = base64_decode($value . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        return $decoded === false ? '' : $decoded;
    }
}
