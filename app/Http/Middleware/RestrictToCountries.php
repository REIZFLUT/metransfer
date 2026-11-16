<?php

namespace App\Http\Middleware;

use App\Support\GeoIp\CountryResolver;
use App\Support\GeoIp\GeoIpDatabaseMissing;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class RestrictToCountries
{
    /**
     * Routes that stay reachable from everywhere (public download links).
     *
     * @var list<string>
     */
    protected array $except = ['public.transfers.*'];

    public function __construct(protected CountryResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('geoblock.enabled') || $this->isExcepted($request)) {
            return $next($request);
        }

        $ip = (string) $request->ip();

        if ($this->isAllowlisted($ip)) {
            return $next($request);
        }

        try {
            $country = $this->resolver->country($ip);
        } catch (GeoIpDatabaseMissing $exception) {
            // Fail closed: without a database we cannot verify the origin.
            Log::error($exception->getMessage());
            $country = null;
        }

        if ($country !== null && in_array($country, config('geoblock.countries'), true)) {
            return $next($request);
        }

        return $this->deny($request);
    }

    protected function isExcepted(Request $request): bool
    {
        return $request->is('up') || $request->routeIs(...$this->except);
    }

    protected function isAllowlisted(string $ip): bool
    {
        $allowed = config('geoblock.allowed_ips');

        return $allowed !== [] && IpUtils::checkIp($ip, $allowed);
    }

    protected function deny(Request $request): Response
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Zugriff aus deiner Region ist nicht erlaubt.'], 403);
        }

        return Inertia::render('public/blocked')->toResponse($request)->setStatusCode(403);
    }
}
