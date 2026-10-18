<?php

namespace App\Http\Controllers\Api\V1\External\Concerns;

use App\Models\ExternalApiClient;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Makes "do the work, remember the answer under the Idempotency-Key" one
 * atomic step. The work and the key are written in the same transaction:
 * if an identical request running at the same moment already took the key,
 * this one rolls back everything it did (records, and - through
 * $onRollback - files) and replays the winner's response, instead of both
 * requests creating a record and only the replay hiding the duplicate.
 *
 * The using controller provides replayIfSeen() (its existing lookup of an
 * already-stored response).
 */
trait RunsIdempotently
{
    /**
     * @param  Closure(): array{0: int, 1: array<string, mixed>}  $work  returns [http status, response body]
     * @param  (Closure(): void)|null  $onRollback  undoes side effects a database rollback cannot (stored files)
     */
    protected function idempotently(ExternalApiClient $client, string $key, string $requestHash, Closure $work, ?Closure $onRollback = null): JsonResponse
    {
        try {
            [$status, $body] = DB::transaction(function () use ($client, $key, $requestHash, $work) {
                [$status, $body] = $work();

                IdempotencyKey::create([
                    'external_api_client_id' => $client->id,
                    'key' => $key,
                    'request_hash' => $requestHash,
                    'response_status' => $status,
                    'response_body' => $body,
                ]);

                return [$status, $body];
            });
        } catch (QueryException $exception) {
            if ($onRollback) {
                $onRollback();
            }

            if ($replay = $this->replayIfSeen($client, $key, $requestHash)) {
                return $replay;
            }

            throw $exception;
        }

        return response()->json($body, $status);
    }
}
