# corbidev-api

🔐 Symfony Auth API

API d’authentification sécurisée basée sur Symfony 8.

Objectifs :

- simplicité
- sécurité
- contrôle total
- architecture claire (Domain / Application / Infrastructure)

---

🚀 Installation

1. Création du projet

composer create-project symfony/skeleton auth-api
cd auth-api

---

2. Installation des dépendances principales

composer require \
  lexik/jwt-authentication-bundle \
  symfony/orm-pack \
  symfony/validator \
  symfony/monolog-bundle \
  symfony/password-hasher \
  symfony/clock

---

3. Installation des dépendances de développement

composer require --dev \
  phpunit/phpunit \
  symfony/test-pack \
  symfony/maker-bundle

---

📦 Détail des dépendances

---

🔑 lexik/jwt-authentication-bundle

→

✔ Rôle

- Génération de JWT
- Signature sécurisée
- Intégration avec Symfony Security

⚠️ Règles importantes

- Ne jamais faire confiance au token seul
- Toujours valider côté Domain :
  - expiration ("exp")
  - émission ("iat")
  - claims métier

👉 Le bundle signe → ton domaine décide

---

🗄 symfony/orm-pack

→

✔ Rôle

- Accès base de données
- Mapping objets / tables
- Repositories

⚠️ Bonnes pratiques

- Aucune logique métier dans les entités
- Repositories en Infrastructure uniquement
- Domain indépendant de Doctrine

---

✅ symfony/validator

→

✔ Rôle

- Validation des entrées utilisateur
- Protection contre données invalides

✔ Usage

- Login
- Email
- Payload API

---

🪵 symfony/monolog-bundle

→

✔ Rôle

- Logs structurés
- Niveaux (INFO, ERROR…)

⚠️ Règles

- Jamais de données sensibles
- Logs techniques uniquement

---

🔐 symfony/password-hasher

→

✔ Rôle

- Hash sécurisé des mots de passe
- Vérification fiable

✔ Avantage

- Évite les erreurs crypto maison

---

⏱ symfony/clock

→

✔ Rôle

- Abstraction du temps
- Permet des tests fiables

✔ Usage

- Validation JWT (exp, iat)
- Gestion TTL
- Simulation du temps en test

⚠️ Règle critique

NE JAMAIS utiliser :

- time()
- new DateTime()

Toujours utiliser :

- ClockInterface

---

🧪 phpunit/phpunit

→

✔ Rôle

- Tests unitaires
- Base du TDD

⚠️ Règle projet

SI ce n’est pas testé → ça n’existe pas

---

🧪 symfony/test-pack

→

✔ Rôle

- KernelTestCase
- Tests d’intégration

---

🛠 symfony/maker-bundle

→

✔ Rôle

- Génération rapide de code

⚠️ Important

- DEV uniquement
- Pas utilisé en production

---

🧠 Philosophie du projet

- simplicité
- sécurité
- lisibilité
- zéro magie
- contrôle total

---

🔒 Règles critiques

JWT

- toujours validé côté domaine
- jamais trusté aveuglément

Doctrine

- aucune logique métier dans Entity

Temps

- toujours injecté (ClockInterface)

Tests

- TDD obligatoire

---

📁 Structure recommandée

src/
- Auth/
  - Login/
  - Token/
  - Jwt/
- Api/
  - Logs/
- Shared/
  - Domain/
  - Infrastructure/
  - Kernel/

---

🔁 Flow

Controller → Application → Domain → Infrastructure

---

🎯 Objectif

Construire une API :

- sécurisée
- testable
- maintenable
- sans magie# corbidev-logs
