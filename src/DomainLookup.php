<?php
declare(strict_types=1);

/**
 * Small domain lookup helper class.
 *
 * This moves the procedural functions into a single place
 * for easier reuse and testing.
 */
class DomainLookup
{
    public static function whoisQuery(string $domain): string
    {
        $parts = explode('.', $domain);
        if (count($parts) < 2) return '';
        $tld = array_pop($parts);

        $server = 'whois.iana.org';
        $resp = self::whoisLookupRaw($server, $tld);
        if (preg_match('/whois:\s*(\S+)/i', $resp, $m)) {
            $server = trim($m[1]);
        } else {
            $fallbacks = [
                'com' => 'whois.verisign-grs.com',
                'net' => 'whois.verisign-grs.com',
                'org' => 'whois.pir.org',
            ];
            if (isset($fallbacks[$tld])) $server = $fallbacks[$tld];
        }

        return self::whoisLookupRaw($server, $domain);
    }

    public static function whoisLookupRaw(string $server, string $query): string
    {
        $port = 43;
        $timeout = 5;
        $out = '';

        $fp = @fsockopen($server, $port, $errno, $errstr, $timeout);
        if (!$fp) return '';

        fwrite($fp, $query . "\r\n");
        stream_set_timeout($fp, $timeout);
        while (!feof($fp)) {
            $out .= fgets($fp, 128);
        }
        fclose($fp);
        return $out;
    }

    public static function isDomainAvailable(string $domain): array
    {
        $result = [
            'dns_resolves' => false,
            'whois' => '',
            'available' => null,
        ];

        $hasA = checkdnsrr($domain, 'A');
        $hasNS = checkdnsrr($domain, 'NS');
        $result['dns_resolves'] = $hasA || $hasNS;

        $whois = self::whoisQuery($domain);
        $result['whois'] = $whois;

        $notFoundPatterns = [
            '/no match/i',
            '/not found/i',
            '/no entries found/i',
            '/status:\s*available/i',
            '/domain not found/i',
        ];

        foreach ($notFoundPatterns as $pat) {
            if (preg_match($pat, $whois)) {
                $result['available'] = true;
                break;
            }
        }

        if ($result['available'] === null) {
            $result['available'] = !$result['dns_resolves'];
        }

        return $result;
    }
}

// Provide backwards-compatible procedural wrappers so existing index.php
// can continue to call the old functions if desired.
if (!function_exists('whois_query')) {
    function whois_query(string $domain): string { return DomainLookup::whoisQuery($domain); }
}

if (!function_exists('whois_lookup_raw')) {
    function whois_lookup_raw(string $server, string $query): string { return DomainLookup::whoisLookupRaw($server, $query); }
}

if (!function_exists('is_domain_available')) {
    function is_domain_available(string $domain): array { return DomainLookup::isDomainAvailable($domain); }
}
