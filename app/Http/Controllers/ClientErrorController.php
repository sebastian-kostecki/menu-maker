<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientErrorRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ClientErrorController extends Controller
{
    /**
     * Log a browser error report at warning level (below the mail alert threshold).
     */
    public function __invoke(StoreClientErrorRequest $request): Response
    {
        Log::warning('Client error: '.$request->validated('message'), [
            'url' => $request->validated('url'),
            'stack' => $request->validated('stack'),
            'source' => $request->validated('source'),
        ]);

        return response()->noContent();
    }
}
