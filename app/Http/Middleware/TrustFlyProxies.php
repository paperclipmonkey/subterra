<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Trust Fly.io proxy infrastructure.
 *
 * Fly.io routes all inbound traffic through its Anycast edge network before
 * forwarding to the application container. Trusting all proxies ('*') is
 * unsafe because it allows any client to spoof X-Forwarded-For, enabling
 * IP-based rate-limit bypass and cache-poisoning attacks.
 *
 * Instead we trust only Fly.io's private machine network (fdaa::/16) and
 * standard private IPv4 ranges. The Fly-Client-IP header is set exclusively
 * by Fly's edge and cannot be forged by end-clients; it is used as the
 * authoritative source of the real client IP via the SetClientIpFromFly
 * middleware applied before rate-limiting and IP logging.
 *
 * References: https://fly.io/docs/networking/request-headers/
 */
class TrustFlyProxies extends Middleware
{
    /**
     * The trusted proxy IP ranges.
     *
     * fdaa::/16  — Fly.io private machine-to-machine network (6PN)
     * 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16 — RFC 1918 private ranges
     * 127.0.0.1, ::1 — loopback (health checks, local dev)
     *
     * @var array<int, string>|string
     */
    protected $proxies = [
        '127.0.0.1',
        '::1',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        'fdaa::/16',
    ];

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;
}
