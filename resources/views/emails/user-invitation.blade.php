<x-mail::message>
# Bienvenue sur Simplon BF

Bonjour **{{ $user->full_name }}**,

Vous avez été invité(e) à rejoindre la plateforme de gestion des formations **Simplon Burkina Faso**.

Cliquez sur le bouton ci-dessous pour activer votre compte et définir votre mot de passe.
Ce lien est valable **{{ $expiresIn }}**.

<x-mail::button :url="$activationUrl" color="red">
Activer mon compte
</x-mail::button>

Si vous n'êtes pas à l'origine de cette invitation, ignorez cet email.

Cordialement,<br>
L'équipe Simplon Burkina Faso
</x-mail::message>
