<?php

use App\Exceptions\InvalidTicketException;
use App\Models\Event;
use App\Models\Project;
use App\Services\JwtKeyService;
use App\Services\PeriodResolver;
use App\Services\TokenVerifier;
use Carbon\Carbon;
use Firebase\JWT\JWT;

beforeEach(function () {
    $this->verifier = app(TokenVerifier::class);
    $this->jwtKeyService = app(JwtKeyService::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('EdDSA tokens', function () {
    it('verifies with current period key', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $signingKey = $this->jwtKeyService->signingKey(5);
        $payload = ['volunteer_id' => 1, 'event_id' => 5, 'iat' => time()];
        $jwt = JWT::encode($payload, $signingKey, 'EdDSA');

        $decoded = $this->verifier->verify($jwt, 5);

        expect($decoded->volunteer_id)->toBe(1)
            ->and($decoded->event_id)->toBe(5);
    });

    it('verifies with previous period key (dual-key)', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        // Sign with previous period key
        $resolver = app(PeriodResolver::class);
        $previousPeriod = $resolver->previousPeriodDate();
        $seed = hex2bin(hash_hmac('sha256', '5:'.$previousPeriod, config('app.key')));
        $keypair = sodium_crypto_sign_seed_keypair($seed);
        $signingKey = base64_encode(sodium_crypto_sign_secretkey($keypair));

        $payload = ['volunteer_id' => 1, 'event_id' => 5, 'iat' => time()];
        $jwt = JWT::encode($payload, $signingKey, 'EdDSA');

        $decoded = $this->verifier->verify($jwt, 5);

        expect($decoded->volunteer_id)->toBe(1)
            ->and($decoded->event_id)->toBe(5);
    });

    it('rejects token signed with wrong key', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        // Generate a random Ed25519 keypair
        $keypair = sodium_crypto_sign_keypair();
        $wrongKey = base64_encode(sodium_crypto_sign_secretkey($keypair));

        $jwt = JWT::encode(['volunteer_id' => 1, 'event_id' => 5, 'iat' => time()], $wrongKey, 'EdDSA');

        $this->verifier->verify($jwt, 5);
    })->throws(InvalidTicketException::class);

    it('rejects modified payload (tampered)', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $signingKey = $this->jwtKeyService->signingKey(5);
        $jwt = JWT::encode(['volunteer_id' => 1, 'event_id' => 5, 'iat' => time()], $signingKey, 'EdDSA');

        // Tamper with the payload
        $parts = explode('.', $jwt);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $payload['volunteer_id'] = 999;
        $parts[1] = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $tampered = implode('.', $parts);

        $this->verifier->verify($tampered, 5);
    })->throws(InvalidTicketException::class);

    it('rejects malformed token string', function () {
        $this->verifier->verify('not.a.valid.token', 5);
    })->throws(InvalidTicketException::class);
});

describe('security: algorithm confusion', function () {
    it('rejects HS256 token (unsupported algorithm)', function () {
        $hmacKey = hash_hmac('sha256', 'test-data', 'test-secret');
        $jwt = JWT::encode(['volunteer_id' => 1, 'event_id' => 5, 'iat' => time()], $hmacKey, 'HS256');

        $this->verifier->verify($jwt, 5);
    })->throws(InvalidTicketException::class);

    it('rejects token with alg: none', function () {
        // Manually craft a token with alg: none
        $header = base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode(['volunteer_id' => 1, 'event_id' => 5, 'iat' => time()]));
        $jwt = "$header.$payload.";

        $this->verifier->verify($jwt, 5);
    })->throws(InvalidTicketException::class);
});

describe('project ID semantics', function () {
    it('rejects token when verified with event ID instead of project ID', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        // Push IDs apart so event->id != event->project_id
        Project::factory()->count(3)->create();
        $project = Project::factory()->create();
        $event = Event::factory()->for($project)->create();

        // Sanity: IDs must differ for this test to be meaningful
        expect($event->id)->not->toBe($project->id);

        $signingKey = $this->jwtKeyService->signingKey($project->id);
        $payload = ['volunteer_id' => 1, 'project_id' => $project->id, 'iat' => time()];
        $jwt = JWT::encode($payload, $signingKey, 'EdDSA');

        // Verifying with event ID (wrong) must fail
        $this->verifier->verify($jwt, $event->id);
    })->throws(InvalidTicketException::class);

    it('verifies token with correct project ID using distinct IDs', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $projectId = 10;
        $signingKey = $this->jwtKeyService->signingKey($projectId);
        $payload = ['volunteer_id' => 1, 'project_id' => $projectId, 'iat' => time()];
        $jwt = JWT::encode($payload, $signingKey, 'EdDSA');

        $decoded = $this->verifier->verify($jwt, $projectId);

        expect($decoded->volunteer_id)->toBe(1)
            ->and($decoded->project_id)->toBe($projectId);
    });

    it('rejects token verified with wrong project ID', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $signingKey = $this->jwtKeyService->signingKey(10);
        $payload = ['volunteer_id' => 1, 'project_id' => 10, 'iat' => time()];
        $jwt = JWT::encode($payload, $signingKey, 'EdDSA');

        $this->verifier->verify($jwt, 99);
    })->throws(InvalidTicketException::class);
});
