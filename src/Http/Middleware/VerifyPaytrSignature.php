<?php
namespace Paytr\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerifyPaytrSignature Middleware
 * Validates incoming PayTR webhook signatures.
 */
class VerifyPaytrSignature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-PayTR-Signature');
        $payload   = $request->getContent();
        $secret    = Config::get('paytr.webhook_secret');

        if (empty($secret)) {
            return response('Webhook secret not configured', 500);
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        if (empty($signature) || !hash_equals($expected, $signature)) {
            return response('Invalid PayTR signature', 403);
        }

        return $next($request);
    }
}
