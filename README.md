# Lagoon Padel

Application de réservation de terrains de padel (Tahiti) : **Flutter** (iOS / Android) + **API REST PHP** + **MySQL / MariaDB**.

## Prérequis

- PHP 8.1+ avec extensions `pdo_mysql`, `json`, `openssl`
- MySQL ou MariaDB
- Flutter SDK (Dart 3.11+)

---

## Base de données

```bash
mysql -u root -p < backend/schema.sql
```

Adapter les identifiants dans `backend/config/config.php` ou via variables d’environnement :

| Variable           | Description        |
|--------------------|--------------------|
| `DB_HOST`          | Hôte MySQL         |
| `DB_PORT`          | Port (défaut 3306) |
| `DB_NAME`          | Base (`lagoon_padel`) |
| `DB_USER` / `DB_PASS` | Utilisateur / mot de passe |
| `LAGOON_JWT_SECRET` | Secret HS256 (obligatoire en production) |
| `CORS_ORIGIN`      | Origine autorisée CORS (défaut `*`) |

---

## Lancer le backend (API)

Depuis le dossier `backend` :

```bash
cd backend
php -S 0.0.0.0:8080 router.php
```

Les routes sont préfixées par **`/api`** :

- `POST /api/register`, `POST /api/login`
- `GET /api/terrains`
- `GET /api/terrains/{id}/slots?date=YYYY-MM-DD`
- `POST /api/reservations` (JWT)
- `GET /api/reservations/me` (JWT)
- `DELETE /api/reservations/{id}` (JWT)

**Apache** : pointer le document root vers `backend` ; le fichier `.htaccess` redirige vers `index.php`.

---

## Lancer l’app Flutter

```bash
flutter pub get
flutter run
```

### URL de l’API

Par défaut (production) : `https://lagoon-padel-api.whiteprovider.net/api` (voir `lib/core/config/app_config.dart`).

Pour pointer vers un **backend local** (émulateur Android → machine hôte) :

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080/api
```

Pour un **appareil physique** sur le même réseau Wi‑Fi, utilisez l’IP locale de votre machine, par exemple :

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8080/api
```

**Sécurité** : le build **release** Android n’autorise pas le HTTP en clair (HTTPS par défaut). En **debug**, le trafic en clair reste possible pour tester contre un serveur local.

---

## Structure du dépôt

- `backend/` — API PHP (contrôleurs, modèles, JWT, validation)
- `lib/` — application Flutter (`core/`, `features/`, `widgets/`)

---

## Fonctionnalités

- Consultation des terrains et créneaux **sans compte**
- Réservation, liste et annulation **avec compte** (JWT)
- Créneaux 1h30 de 07h30 à 18h00, **5 000 XPF** par créneau (calcul côté serveur)
- Contrainte d’unicité terrain + date + heure de début pour éviter les doubles réservations
