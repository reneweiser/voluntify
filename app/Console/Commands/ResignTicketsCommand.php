<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\JwtKeyService;
use Firebase\JWT\JWT;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResignTicketsCommand extends Command
{
    protected $signature = 'app:resign-tickets {--dry-run : Preview changes without modifying tickets}';

    protected $description = 'Re-sign existing HMAC (HS256) tickets with Ed25519 (EdDSA)';

    public function __construct(private JwtKeyService $jwtKeyService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $resigned = 0;
        $skipped = 0;
        $failed = 0;

        $tickets = Ticket::query()->lazy();

        foreach ($tickets as $ticket) {
            try {
                $alg = $this->getTokenAlgorithm($ticket->jwt_token);

                if ($alg === 'EdDSA') {
                    $skipped++;

                    continue;
                }

                $payload = $this->extractPayload($ticket->jwt_token);

                if (! $dryRun) {
                    $signingKey = $this->jwtKeyService->signingKey($ticket->event_id);
                    $newJwt = JWT::encode($payload, $signingKey, 'EdDSA');
                    $ticket->update(['jwt_token' => $newJwt]);
                }

                $resigned++;
            } catch (\Exception $e) {
                $failed++;
                Log::warning("Failed to re-sign ticket #{$ticket->id}: {$e->getMessage()}");
                $this->warn("Failed to re-sign ticket #{$ticket->id}: {$e->getMessage()}");
            }
        }

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Re-signed: {$resigned}, Skipped (already EdDSA): {$skipped}, Failed: {$failed}");

        return self::SUCCESS;
    }

    private function getTokenAlgorithm(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            throw new \RuntimeException('Malformed JWT: expected at least 2 parts');
        }

        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);

        return $header['alg'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            throw new \RuntimeException('Malformed JWT: expected at least 2 parts');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (! is_array($payload)) {
            throw new \RuntimeException('Malformed JWT payload');
        }

        return $payload;
    }
}
