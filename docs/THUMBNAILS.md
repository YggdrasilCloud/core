# 🖼️ Système de génération de thumbnails

## Vue d'ensemble

Le système génère automatiquement des vignettes (300x300px max) lors de l'upload de photos.

**Dual-engine** : vipsthumbnail (libvips) en priorité, fallback sur PHP GD.

## 🚀 Méthodes de génération

### 1. vipsthumbnail (libvips) - **Prioritaire**

```
Vitesse: ~23-30 images/seconde
Mémoire: Streaming (10x moins que GD)
Qualité: Excellente (préserve EXIF, meilleure compression)
```

**Avantages:**
- ⚡ 5-10x plus rapide que GD
- 💾 Consommation mémoire minimale
- 🎨 Meilleure qualité d'image
- 📊 Préserve les métadonnées EXIF

**Installation:**
```dockerfile
RUN apt-get install -y libvips-tools
```

### 2. PHP GD - **Fallback**

```
Vitesse: ~3-5 images/seconde
Mémoire: Charge l'image entière en RAM
Qualité: Bonne
```

**Avantages:**
- ✅ Toujours disponible (extension PHP)
- 🛡️ Robuste et testé
- 🔧 Pas de dépendance externe

**Installation:**
```dockerfile
RUN install-php-extensions gd
```

## 📊 Détection automatique

Au démarrage, le service détecte la méthode disponible :

```php
$generator = new ThumbnailGenerator($storagePath);
echo $generator->getMethod();
// → "vipsthumbnail (libvips)" ou "PHP GD"
```

## 🔄 Stratégie de fallback

1. **Démarrage**: Détection de vipsthumbnail
2. **Génération**: Tentative avec vipsthumbnail
3. **Erreur**: Fallback automatique vers GD
4. **Succès**: Retourne le chemin du thumbnail

```php
// Dans generateWithVips()
if ($returnCode !== 0 || !file_exists($thumbnailPath)) {
    return $this->generateWithGd($sourceFilePath, $maxWidth, $maxHeight);
}
```

## 📁 Structure de stockage

```
var/storage/photos/
├── 2025/10/11/
│   └── photo_original.jpg        (800x600, 17.6KB)
└── thumbs/2025/10/11/
    └── photo_original_thumb.jpg  (300x225, 3.9KB)
```

## 🧪 Tests

### Benchmark de performance

```bash
docker compose exec app php /app/scripts/benchmark-thumbnails.php
```

### Tester la méthode utilisée

```bash
docker compose exec app php -r "
require '/app/vendor/autoload.php';
\$gen = new App\Photo\Domain\Service\ThumbnailGenerator('/app/var/storage/photos');
echo \$gen->getMethod();
"
```

### Upload et vérification

```bash
# Upload
curl -X POST http://localhost:8888/api/folders/{id}/photos \
  -F "photo=@test.jpg" \
  -F "ownerId=123..."

# Vérifier le thumbnail
curl http://localhost:8888/api/photos/{id}/thumbnail -o test-thumb.jpg
file test-thumb.jpg
```

## 🎯 API

### Endpoints

```
POST /api/folders/{folderId}/photos
→ Upload photo + génération automatique du thumbnail

GET /api/photos/{photoId}/thumbnail
→ Télécharge le thumbnail (300x300 max, JPEG, cache 1 an)

GET /api/folders/{folderId}/photos
→ Liste avec thumbnailUrl pour chaque photo
```

### Réponse JSON

```json
{
  "id": "...",
  "fileName": "photo.jpg",
  "fileUrl": "/api/photos/.../file",
  "thumbnailUrl": "/api/photos/.../thumbnail",
  "uploadedAt": "2025-10-11T18:00:00Z"
}
```

## ⚙️ Configuration

### services.yaml

```yaml
App\Photo\Domain\Service\ThumbnailGenerator:
    arguments:
        $storagePath: '%photo.storage_path%'
```

### Personnalisation

```php
// Taille personnalisée
$thumbnailPath = $generator->generateThumbnail($sourceFile, 500, 500);
```

## 📈 Performance

| Méthode | Images/sec | Mémoire | Taille thumbnail |
|---------|-----------|---------|------------------|
| **vipsthumbnail** | ~23-30 | ~50MB | 4.1KB |
| **PHP GD** | ~3-5 | ~200MB | 3.9KB |

## 🐛 Dépannage

### Vipsthumbnail non détecté

```bash
# Vérifier l'installation
docker compose exec app which vipsthumbnail

# Vérifier la version
docker compose exec app vipsthumbnail --version
```

### Fallback forcé vers GD

Si vipsthumbnail est buggé, renommez le binaire :
```bash
docker compose exec app mv /usr/bin/vipsthumbnail /usr/bin/vipsthumbnail.disabled
```

### Logs de génération

Les erreurs de génération sont silencieuses par design (continue sans thumbnail).

Pour debugger, modifiez `UploadPhotoToFolderHandler.php`:

```php
} catch (\Exception $e) {
    error_log('Thumbnail error: ' . $e->getMessage());
    // Ou throw $e; pour arrêter l'upload
}
```

## 🚀 Optimisations futures

- [ ] Génération asynchrone (queue)
- [ ] Multiples tailles (small, medium, large)
- [ ] Support WebP
- [ ] CDN integration
- [ ] Lazy generation (à la demande)

## 📚 Ressources

- [libvips](https://libvips.github.io/libvips/) - Documentation officielle
- [vipsthumbnail](https://libvips.github.io/libvips/API/current/using-cli.html) - CLI reference
- [PHP GD](https://www.php.net/manual/fr/book.image.php) - Documentation PHP
