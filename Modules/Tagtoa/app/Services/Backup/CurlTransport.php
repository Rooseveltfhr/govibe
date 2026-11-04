<?php

namespace Modules\Tagtoa\App\Services\Backup;

/** Transport cURL direct : aucune dépendance Composer (ext-curl suffit), vérification TLS toujours active, aucune redirection suivie. */
class CurlTransport implements S3Transport
{
    public function request(string $method, string $url, array $headers, ?string $uploadFile, int $timeout): array
    {
        $ch = curl_init($url);
        $out = ['status' => 0, 'headers' => [], 'body' => ''];
        $fh = null;

        $hdrs = ['Expect:'];   // pas de « 100-continue » : certains points d'accès (proxy, B2) l'ignorent ou attendent 1 s
        foreach ($headers as $k => $v) {
            $hdrs[] = $k.': '.$v;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $hdrs,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$out) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $out['headers'][strtolower(trim($k))] = trim($v);
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($c, $chunk) use (&$out) {
                if (strlen($out['body']) < 2000) {
                    $out['body'] .= substr($chunk, 0, 2000 - strlen($out['body']));
                }

                return strlen($chunk);
            },
        ]);
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        if ($uploadFile !== null) {
            $fh = fopen($uploadFile, 'rb');
            curl_setopt_array($ch, [CURLOPT_UPLOAD => true, CURLOPT_INFILE => $fh, CURLOPT_INFILESIZE => filesize($uploadFile)]);
        }

        try {
            if (curl_exec($ch) === false) {
                throw new \RuntimeException('Réseau : '.curl_error($ch));
            }
            $out['status'] = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($ch);
            if (is_resource($fh)) {
                fclose($fh);
            }
        }

        return $out;
    }
}
