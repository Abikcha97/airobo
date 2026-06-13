<?php

namespace App\Services;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Ramsey\Uuid\Uuid;

class JwtService
{
    private Configuration $config;

    public function __construct()
    {
        $privateKeyPath = config('jwt.private_key_path');
        $publicKeyPath  = config('jwt.public_key_path');

        $this->config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::file($privateKeyPath),
            InMemory::file($publicKeyPath),
        );
    }

    /**
     * Issue an RS256 access token.
     *
     * @param  array{sub:int, username:string, role:string, agent_id:int|null}  $claims
     */
    public function issueAccessToken(array $claims): string
    {
        $now    = new DateTimeImmutable();
        $expiry = $now->modify(sprintf('+%d seconds', config('jwt.access_ttl', 3600)));

        $token = $this->config->builder()
            ->issuedBy(config('app.url'))
            ->issuedAt($now)
            ->expiresAt($expiry)
            ->relatedTo((string) $claims['sub'])
            ->withClaim('username', $claims['username'])
            ->withClaim('role',     $claims['role'])
            ->withClaim('agent_id', $claims['agent_id'])
            ->withClaim('jti',      Uuid::uuid4()->toString())
            ->getToken($this->config->signer(), $this->config->signingKey());

        return $token->toString();
    }

    /**
     * Issue a refresh token (opaque random string stored in Redis).
     */
    public function issueRefreshToken(): string
    {
        return bin2hex(random_bytes(40));
    }

    /**
     * Parse and validate an access token.
     * Returns the Plain token on success, throws on failure.
     *
     * @throws \RuntimeException
     */
    public function parseAccessToken(string $tokenString): Plain
    {
        $token = $this->config->parser()->parse($tokenString);

        if (!($token instanceof Plain)) {
            throw new \RuntimeException('Invalid token type');
        }

        $constraints = [
            new SignedWith($this->config->signer(), $this->config->verificationKey()),
            new IssuedBy(config('app.url')),
        ];

        if (!$this->config->validator()->validate($token, ...$constraints)) {
            throw new \RuntimeException('Token validation failed');
        }

        if ($token->isExpired(new DateTimeImmutable())) {
            throw new \RuntimeException('Token has expired');
        }

        return $token;
    }
}
