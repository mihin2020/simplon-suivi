# Configuration Email ADSEL

## Paramètres de connexion

### Compte Email Central
- **Email** : talentora@adsel.org
- **Mot de passe** : [Mot de passe du compte de messagerie]

### Serveur Entrant (IMAP)
- **Hôte** : mail.adsel.org ou mail84.lwspanel.com
- **Port IMAP** : 993 (SSL/TLS)
- **Port POP3** : 995 (SSL/TLS - non recommandé)

### Serveur Sortant (SMTP)
- **Hôte** : mail.adsel.org ou mail84.lwspanel.com
- **Port SMTP (SSL)** : 465
- **Port SMTP (STARTTLS)** : 587 (recommandé si 465 ne fonctionne pas)

## Configuration .env - Option 1 : SSL (Port 465)

```env
# Configuration Mail (SMTP)
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=mail.adsel.org
MAIL_PORT=465
MAIL_USERNAME=talentora@adsel.org
MAIL_PASSWORD=votre_mot_de_passe
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=talentora@adsel.org
MAIL_FROM_NAME="Talentora Simplon"

# Configuration IMAP (pour la réception des emails)
IMAP_HOST=mail.adsel.org
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_USERNAME=talentora@adsel.org
IMAP_PASSWORD=votre_mot_de_passe
IMAP_VALIDATE_CERT=true
IMAP_DEFAULT_ACCOUNT=default
```

## Configuration .env - Option 2 : STARTTLS (Port 587)

Si le port 465 ne fonctionne pas (erreur de handshake SSL), essayez avec STARTTLS sur le port 587 :

```env
# Configuration Mail (SMTP) - STARTTLS
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=mail.adsel.org
MAIL_PORT=587
MAIL_USERNAME=talentora@adsel.org
MAIL_PASSWORD=votre_mot_de_passe
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=talentora@adsel.org
MAIL_FROM_NAME="Talentora Simplon"

# Configuration IMAP inchangée
IMAP_HOST=mail.adsel.org
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_USERNAME=talentora@adsel.org
IMAP_PASSWORD=votre_mot_de_passe
IMAP_VALIDATE_CERT=true
IMAP_DEFAULT_ACCOUNT=default
```

## Dépannage

### Erreur "SSL: Handshake timed out"
- Essayez de passer du port 465 (SSL) au port 587 (STARTTLS)
- Vérifiez que le pare-feu autorise les connexions sortantes sur ces ports
- Essayez l'autre serveur : `mail84.lwspanel.com` au lieu de `mail.adsel.org`

### Erreur "Connection refused"
- Vérifiez que l'hôte SMTP est correct
- Vérifiez votre connexion internet
- Essayez avec ou sans `MAIL_ENCRYPTION`

### Erreur d'authentification
- Vérifiez que le mot de passe est correct
- Assurez-vous que le compte email est actif
- Vérifiez que l'authentification SMTP est activée sur le serveur

## Notes importantes

1. **SSL vs STARTTLS** : Le port 465 utilise SSL direct, le port 587 utilise STARTTLS (négociation après connexion)
2. **Réception des réponses** : Les réponses des apprenants seront reçues dans la boîte de réception du projet (via IMAP)
3. **CC (Copie à)** : Le champ CC permet d'envoyer une copie de l'email à d'autres destinataires
4. **Threading** : Les emails sont automatiquement regroupés par conversation (thread)

## Fonctionnalités implémentées

- ✅ Envoi d'emails via SMTP (ADSEL)
- ✅ Réception des réponses via IMAP
- ✅ Threading des conversations
- ✅ Champ CC (Copie à)
- ✅ Pièces jointes
- ✅ Interface de composition avec sélecteur de destinataires
