<?php

namespace Modules\Tagtoa\App\Services\Backup;

/** Transport HTTP de S3Client (injectable : cURL en production, faux serveur dans les tests). */
interface S3Transport
{
    /**
     * @param  array<string,string>  $headers
     * @param  string|null  $uploadFile  fichier à envoyer comme corps (flux, jamais chargé en mémoire)
     * @return array{status:int,headers:array<string,string>,body:string}  en-têtes de réponse en minuscules ; corps tronqué à 2000 octets
     */
    public function request(string $method, string $url, array $headers, ?string $uploadFile, int $timeout): array;
}
