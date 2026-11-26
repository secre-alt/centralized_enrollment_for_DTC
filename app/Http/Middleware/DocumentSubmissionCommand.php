<?php

namespace App\Http\Middleware;

use App\Services\DocumentSubmissionService;
use Closure;
use Illuminate\Http\Request;

class DocumentSubmissionCommand
{
    public function handle(Request $request, Closure $next)
    {
        $request->validate(['operation_key' => ['required', 'uuid']]);
        $fingerprint = app(\App\Support\WorkflowCommandFingerprint::class)->fingerprint($request, 'documents.submitted');
        if (app(DocumentSubmissionService::class)->replay($request, $fingerprint)) {
            return redirect()->route('portal.documents.index')
                ->with('success', 'Your document requests were already submitted.');
        }

        return $next($request);
    }
}
