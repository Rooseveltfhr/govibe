<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Support\Qr;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function show(string $number)
    {
        $certificate = Certificate::where('number', $number)->with('registration.category')->firstOrFail();

        return view('pages.certificate', [
            'certificate' => $certificate,
            'qr'          => Qr::svgDataUri(route('certificate.verify', $certificate->number), 200),
        ]);
    }

    /** Vérification publique de l'authenticité d'un certificat. */
    public function verify(Request $request, ?string $number = null)
    {
        $number = $number ?: $request->query('numero');
        $certificate = $number
            ? Certificate::where('number', $number)->with('registration')->first()
            : null;

        return view('pages.certificate-verify', [
            'number'      => $number,
            'certificate' => $certificate,
        ]);
    }
}
