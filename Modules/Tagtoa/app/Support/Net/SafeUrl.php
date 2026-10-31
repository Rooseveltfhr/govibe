<?php

namespace Modules\Tagtoa\App\Support\Net;

/**
 * TAGTOA — une URL fournie par un tiers peut-elle être appelée PAR LE SERVEUR ?
 *
 * Les callbacks de paiement (callback_url, webhook_url) sont des adresses choisies par
 * le titulaire d'une clé API. Sans contrôle, le serveur POSTait n'importe où : localhost,
 * réseau privé, adresse de métadonnées du cloud (169.254.169.254), service interne sans
 * authentification — l'appelant ne voit pas la réponse (SSRF « aveugle »), mais déclenche
 * l'action. Règles (décision D6, défaut du plan) :
 *   - https uniquement (http seulement si `tagtoa.api.allow_http_callbacks` est vrai) ;
 *   - pas d'identifiants dans l'URL ;
 *   - le nom d'hôte est RÉSOLU et CHAQUE adresse obtenue doit être publique ;
 *   - l'appel est ensuite épinglé sur l'adresse vérifiée (pas de seconde résolution : un DNS
 *     « rebinding » ne peut pas changer de réponse entre le contrôle et l'appel).
 *
 * LOGIQUE PURE hors la résolution DNS, qui s'injecte (`resolveUsing`) pour les tests.
 */
class SafeUrl
{
    /** @var (callable(string):array<int,string>)|null */
    protected static $resolver = null;

    /** Remplace la résolution DNS (tests). null = résolution réelle. */
    public static function resolveUsing(?callable $fn): void
    {
        self::$resolver = $fn;
    }

    /**
     * @return array{ok:bool, reason:?string, host:?string, port:?int, ip:?string, scheme:?string}
     */
    public static function check(?string $url, ?bool $allowHttp = null): array
    {
        $allowHttp ??= (bool) config('tagtoa.api.allow_http_callbacks', false);
        $no = fn (string $reason) => ['ok' => false, 'reason' => $reason, 'host' => null, 'port' => null, 'ip' => null, 'scheme' => null];

        $url = trim((string) $url);
        if ($url === '' || strlen($url) > 500 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return $no('invalid');
        }
        $p = parse_url($url);
        if (! is_array($p) || empty($p['host']) || empty($p['scheme'])) {
            return $no('invalid');
        }

        $scheme = strtolower($p['scheme']);
        if ($scheme !== 'https' && ! ($scheme === 'http' && $allowHttp)) {
            return $no('scheme');
        }
        if (isset($p['user']) || isset($p['pass'])) {
            return $no('credentials');
        }

        $host = rtrim(strtolower(trim($p['host'], '[]')), '.');   // « localhost. » = « localhost »
        if ($host === '' || (! filter_var($host, FILTER_VALIDATE_IP) && ! preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $host))) {
            return $no('invalid');                                 // seuls les noms ASCII usuels : ni « \ », ni IDN, ni caractères exotiques
        }
        $port = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port < 1 || $port > 65535) {
            return $no('port');
        }
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return $no('private_host');
        }

        // Une adresse IP écrite telle quelle : pas de DNS, on la juge directement.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [self::normalizeIp($host)];
        } else {
            $ips = self::resolve($host);
            if ($ips === []) {
                return $no('unresolvable');
            }
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return $no('private_address');
            }
        }

        return ['ok' => true, 'reason' => null, 'host' => $host, 'port' => $port, 'ip' => $ips[0], 'scheme' => $scheme];
    }

    /** Adresse publique routable ? (refuse privé, loopback, lien-local, CGNAT, réservé, multicast, ULA IPv6, IPv4 mappée IPv6 privée). */
    public static function isPublicIp(string $ip): bool
    {
        $ip = self::normalizeIp($ip);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false; // 10/8, 172.16/12, 192.168/16, 127/8, 0/8, 169.254/16, 240/4, ::1, fc00::/7, fe80::/10…
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $n = ip2long($ip);
            $deny = [
                ['100.64.0.0', 10],   // CGNAT
                ['192.0.0.0', 24],    // IETF protocol assignments
                ['192.0.2.0', 24], ['198.51.100.0', 24], ['203.0.113.0', 24], // documentation
                ['198.18.0.0', 15],   // bancs d'essai
                ['224.0.0.0', 4],     // multicast
            ];
            foreach ($deny as [$base, $bits]) {
                $mask = -1 << (32 - $bits);
                if (($n & $mask) === (ip2long($base) & $mask)) {
                    return false;
                }
            }

            return true;
        }

        // IPv6 : multicast, documentation, NAT64/6to4 (peuvent encapsuler une IPv4 privée).
        $bin = inet_pton($ip);
        $b0 = ord($bin[0]);
        if ($b0 === 0xff) {
            return false;                                  // ff00::/8 multicast
        }
        if (strncmp($bin, hex2bin('20010db8'), 4) === 0) {
            return false;                                  // 2001:db8::/32 documentation
        }
        if (strncmp($bin, hex2bin('0064ff9b'), 4) === 0 || strncmp($bin, hex2bin('2002'), 2) === 0) {
            return false;                                  // 64:ff9b::/96 NAT64, 2002::/16 6to4
        }
        if (strncmp($bin, str_repeat("\0", 12), 12) === 0) {
            return false;                                  // ::/96 compatible IPv4 (::7f00:1 = 127.0.0.1 sur certaines piles)
        }
        if (strncmp($bin, hex2bin('0064ff9b0001'), 6) === 0) {
            return false;                                  // 64:ff9b:1::/48 NAT64 local
        }
        if (strncmp($bin, hex2bin('0100000000000000'), 8) === 0) {
            return false;                                  // 100::/64 discard
        }
        if (ord($bin[0]) === 0x20 && ord($bin[1]) === 0x01 && ord($bin[2]) < 0x02) {
            return false;                                  // 2001::/23 : Teredo, ORCHID, protocoles IETF
        }
        if (ord($bin[0]) === 0xfe && (ord($bin[1]) & 0xc0) === 0xc0) {
            return false;                                  // fec0::/10 site-local (déprécié)
        }

        return true;
    }

    /** « ::ffff:10.0.0.1 » → « 10.0.0.1 » : l'adresse mappée se juge comme l'IPv4 qu'elle cache. */
    protected static function normalizeIp(string $ip): string
    {
        $ip = trim($ip, '[] ');
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = inet_pton($ip);
            if ($bin !== false && strlen($bin) === 16 && strncmp($bin, str_repeat("\0", 10)."\xff\xff", 12) === 0) {
                return inet_ntop(substr($bin, 12));
            }
        }

        return $ip;
    }

    /** @return array<int,string> toutes les adresses A / AAAA du nom, normalisées. */
    protected static function resolve(string $host): array
    {
        if (self::$resolver) {
            return array_values(array_map([self::class, 'normalizeIp'], (self::$resolver)($host)));
        }

        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
            if (! empty($r['ip'])) {
                $ips[] = $r['ip'];
            } elseif (! empty($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }
        if ($ips === []) {
            // Repli : résolveur système (hôtes de test, /etc/hosts).
            foreach ((array) @gethostbynamel($host) as $ip) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique(array_map([self::class, 'normalizeIp'], $ips)));
    }
}
