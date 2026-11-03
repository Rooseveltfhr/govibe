<?php

namespace Modules\Tagtoa\App\Services\Backup;

/**
 * Client S3 minimal (PUT / HEAD d'un objet) avec signature AWS SigV4 — compatible Cloudflare R2, Backblaze B2 et AWS S3.
 * Écrit à la main pour ne rien ajouter à composer.json (l'application hôte du VPS n'est pas touchée).
 * Adressage « path-style » (https://endpoint/bucket/clé). Le corps est envoyé en flux (mémoire constante) et son SHA-256 est
 * signé : le service refuse l'objet s'il a été altéré en route.
 */
class S3Client
{
    public function __construct(
        private string $endpoint,
        private string $bucket,
        private string $keyId,
        private string $secret,
        private string $region = 'auto',
        private int $timeout = 600,
        private ?S3Transport $transport = null,
    ) {
        $this->transport ??= new CurlTransport();
        $this->endpoint = rtrim($endpoint, '/');
        // https obligatoire ; http seulement vers la boucle locale (tests de bout en bout avec un faux serveur, jamais un vrai bucket).
        if (! preg_match('#^(https://[^/\s]+|http://(127\.0\.0\.1|localhost)(:\d+)?)$#', $this->endpoint)) {
            throw new \InvalidArgumentException('Le point d\'accès doit être en https:// (sans chemin).');
        }
    }

    /**
     * En-têtes signés pour une requête. Fonction pure (testée contre un vecteur officiel AWS).
     *
     * @param  array<string,string>  $headers  en-têtes à signer en plus de host / x-amz-*  (clés en minuscules)
     * @return array<string,string> tous les en-têtes à envoyer, Authorization compris
     */
    public function sign(string $method, string $path, string $payloadHash, \DateTimeInterface $now, array $headers = [], string $query = ''): array
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST).(($p = parse_url($this->endpoint, PHP_URL_PORT)) ? ':'.$p : '');
        $date = (new \DateTimeImmutable('@'.$now->getTimestamp()))->setTimezone(new \DateTimeZone('UTC'));
        $amzDate = $date->format('Ymd\THis\Z');
        $day = $date->format('Ymd');

        $h = array_change_key_case($headers, CASE_LOWER);
        $h['host'] = $host;
        $h['x-amz-content-sha256'] = $payloadHash;
        $h['x-amz-date'] = $amzDate;
        ksort($h);

        $canonHeaders = '';
        foreach ($h as $k => $v) {
            $canonHeaders .= $k.':'.trim(preg_replace('/\s+/', ' ', (string) $v))."\n";
        }
        $signed = implode(';', array_keys($h));
        $canonical = implode("\n", [$method, $path, $query, $canonHeaders, $signed, $payloadHash]);

        $scope = "$day/{$this->region}/s3/aws4_request";
        $toSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonical)]);
        $k = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', 's3', hash_hmac('sha256', $this->region, hash_hmac('sha256', $day, 'AWS4'.$this->secret, true), true), true), true);
        $signature = hash_hmac('sha256', $toSign, $k);

        $h['authorization'] = "AWS4-HMAC-SHA256 Credential={$this->keyId}/$scope, SignedHeaders=$signed, Signature=$signature";

        return $h;
    }

    /** Chemin canonique d'un objet : chaque segment encodé une fois (RFC 3986), les « / » conservés. */
    public function objectPath(string $key): string
    {
        $segments = array_map('rawurlencode', explode('/', ltrim($key, '/')));

        return '/'.rawurlencode($this->bucket).'/'.implode('/', $segments);
    }

    /** Envoie un fichier local (en flux). Lève une exception sur tout échec. Retourne l'ETag (sans guillemets). */
    public function putFile(string $key, string $file): string
    {
        $hash = hash_file('sha256', $file);
        $path = $this->objectPath($key);
        $headers = $this->sign('PUT', $path, $hash, new \DateTimeImmutable(), ['content-type' => 'application/octet-stream']);
        $res = $this->transport->request('PUT', $this->endpoint.$path, $headers, $file, $this->timeout);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new \RuntimeException('Envoi refusé (HTTP '.$res['status'].') : '.$this->snippet($res['body']));
        }

        return trim($res['headers']['etag'] ?? '', '"');
    }

    /** @return array{size:int,etag:string}|null  null si l'objet n'existe pas */
    public function head(string $key): ?array
    {
        $path = $this->objectPath($key);
        $res = $this->transport->request('HEAD', $this->endpoint.$path, $this->sign('HEAD', $path, hash('sha256', ''), new \DateTimeImmutable()), null, 60);
        if ($res['status'] === 404) {
            return null;
        }
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new \RuntimeException('Lecture refusée (HTTP '.$res['status'].')');
        }

        return ['size' => (int) ($res['headers']['content-length'] ?? 0), 'etag' => trim($res['headers']['etag'] ?? '', '"')];
    }

    /** Un extrait de la réponse, sans jamais pouvoir contenir nos identifiants (ils ne sont dans aucune réponse). */
    private function snippet(string $body): string
    {
        return trim(substr(strip_tags($body), 0, 300));
    }
}
