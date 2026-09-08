<x-mail::message>
# Nouveau message de contact

**De :** {{ $contactMessage->name }} ({{ $contactMessage->email }})
@if ($contactMessage->phone)
**Téléphone :** {{ $contactMessage->phone }}
@endif

**Message :**

{{ $contactMessage->message }}

<x-mail::button :url="route('admin.messages.index')">
Voir les messages
</x-mail::button>
</x-mail::message>
