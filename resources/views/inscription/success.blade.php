@extends('layouts.app')

@section('title', 'Inscription réussie — GOVIBE Academy')

@section('content')
<section class="py-16 px-4">
    <div class="max-w-2xl mx-auto">
        <!-- Success card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden text-center">
            <!-- Top banner -->
            <div class="py-10 px-8" style="background: linear-gradient(135deg, #1e3a5f, #2d5a8e)">
                <div class="w-20 h-20 rounded-full bg-green-400 flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <i class="fas fa-check text-white text-3xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Félicitations !</h1>
                <p class="text-blue-200">Votre inscription a été enregistrée avec succès.</p>
            </div>

            <div class="p-8">
                <!-- Confirmation message -->
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6">
                    <p class="text-green-800 text-sm font-medium">
                        <i class="fas fa-envelope mr-2"></i>
                        Vous recevrez bientôt les informations complémentaires à l'adresse e-mail fournie.
                    </p>
                </div>

                <!-- Info participant -->
                <div class="bg-gray-50 rounded-xl p-5 mb-6 text-left">
                    <h3 class="font-semibold text-gray-800 mb-3 text-center">Récapitulatif de votre inscription</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center py-1.5 border-b border-gray-200">
                            <span class="text-gray-500 text-sm">N° Inscription</span>
                            <span class="font-bold text-blue-800 text-lg">{{ $inscription->numero_inscription }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 border-b border-gray-200">
                            <span class="text-gray-500 text-sm">Nom</span>
                            <span class="font-medium text-gray-800">{{ $inscription->nom_complet }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 border-b border-gray-200">
                            <span class="text-gray-500 text-sm">Formation</span>
                            <span class="font-medium text-gray-800">{{ $formation->nom }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 border-b border-gray-200">
                            <span class="text-gray-500 text-sm">Email</span>
                            <span class="font-medium text-gray-800">{{ $inscription->email }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1.5">
                            <span class="text-gray-500 text-sm">Téléphone</span>
                            <span class="font-medium text-gray-800">{{ $inscription->telephone }}</span>
                        </div>
                    </div>
                </div>

                <!-- QR Code -->
                @if($inscription->qr_code)
                <div class="mb-6">
                    <p class="text-gray-500 text-sm mb-3">Votre QR Code d'identification</p>
                    <img src="data:image/png;base64,{{ $inscription->qr_code }}" alt="QR Code" class="w-32 h-32 mx-auto border-2 border-gray-200 rounded-xl p-1">
                    <p class="text-gray-400 text-xs mt-2">Présentez ce QR Code le jour de la formation</p>
                </div>
                @endif

                <!-- WhatsApp Button -->
                @if($formation->whatsapp_link)
                <a href="{{ $formation->whatsapp_link }}" target="_blank"
                   class="flex items-center justify-center space-x-3 w-full bg-green-500 hover:bg-green-600 text-white font-bold py-4 px-6 rounded-xl transition-all hover:shadow-lg mb-4">
                    <i class="fab fa-whatsapp text-2xl"></i>
                    <span>Rejoindre le groupe WhatsApp</span>
                </a>
                @endif

                <a href="{{ route('inscription.create') }}"
                   class="flex items-center justify-center space-x-2 w-full border-2 border-gray-200 text-gray-600 font-medium py-3 px-6 rounded-xl hover:bg-gray-50 transition-colors">
                    <i class="fas fa-arrow-left text-sm"></i>
                    <span>Retour à l'accueil</span>
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
