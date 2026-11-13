<x-mail::message>
# Bonjour {{ $voter->displayName() }},

Vous êtes invité(e) à voter pour l'élection **{{ $voter->election->title }}** organisée par l'amicale.

Ce lien est personnel et à usage unique : il ne peut servir qu'une seule fois.

<x-mail::button :url="$voter->voteUrl()">
Accéder au vote
</x-mail::button>

Si vous n'êtes pas à l'origine de cette inscription, vous pouvez ignorer cet email.

Merci,<br>
L'équipe de la Commission Électorale Indépendante (CEI)
</x-mail::message>
