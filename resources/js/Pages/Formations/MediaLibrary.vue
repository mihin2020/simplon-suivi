<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StorageGauge from '@/Components/StorageGauge.vue'

// Laravel refreshes the XSRF-TOKEN cookie on every response — always up-to-date.
const getCsrfToken = (): string => {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '')
}

declare global {
  interface Window {
    cloudinary: any
  }
}

defineOptions({ layout: AdminLayout })

interface Uploader {
  id: string
  first_name: string
  last_name: string
}

interface Media {
  id: string
  title: string | null
  description: string | null
  type: 'photo' | 'video'
  album: string | null
  url: string
  thumbnail_url: string
  file_size: number
  width: number | null
  height: number | null
  duration: number | null
  format: string | null
  formatted_size: string
  formatted_duration: string | null
  uploader: Uploader
  created_at: string
}

interface CloudinaryUsage {
  storage_used: number
  storage_limit: number
  storage_used_formatted: string
  storage_limit_formatted: string
  storage_remaining_formatted: string
  percent_used: number
  alert_level: 'normal' | 'warning' | 'critical'
}

interface Project {
  id: string
  name: string
}

interface Formation {
  id: string
  name: string
  project: Project
}

interface FormationLink {
  id: string
  title: string | null
  url: string
  creator?: Uploader
  created_at: string
}

const props = defineProps<{
  formation: Formation
  medias: Media[]
  albums: string[]
  links: FormationLink[]
  cloudinaryUsage: CloudinaryUsage
  cloudName: string
  uploadPreset: string
}>()

// État
const selectedAlbum = ref<string>('all')
const searchQuery = ref('')
const showDeleteModal = ref(false)
const mediaToDelete = ref<Media | null>(null)
const showEditModal = ref(false)
const mediaToEdit = ref<Media | null>(null)
const editForm = ref({ title: '', album: '', description: '' })
const isUploading = ref(false)
const isScriptLoading = ref(true)
const scriptLoadError = ref(false)

// Sélection multiple
const selectedMedias = ref<string[]>([])
const showAlbumModal = ref(false)
const newAlbumName = ref('')
const isBatchUpdating = ref(false)

// Mode vue dossier
const viewMode = ref<'folders' | 'grid'>('folders')
const currentFolder = ref<string | null>(null)

// Médias groupés par album
const albumsWithMedias = computed(() => {
  const groups: Record<string, Media[]> = {}
  
  // Album sans nom (racine)
  groups['__root__'] = props.medias.filter(m => !m.album)
  
  // Albums avec nom
  props.albums.forEach(album => {
    groups[album] = props.medias.filter(m => m.album === album)
  })
  
  return groups
})

// Ouvrir un dossier (album)
const openFolder = (album: string | null) => {
  currentFolder.value = album
  viewMode.value = 'grid'
}

// Retour à la vue dossiers
const backToFolders = () => {
  currentFolder.value = null
  viewMode.value = 'folders'
}

// Médias du dossier courant
const currentFolderMedias = computed(() => {
  if (!currentFolder.value || currentFolder.value === '__root__') {
    return props.medias.filter(m => !m.album)
  }
  return props.medias.filter(m => m.album === currentFolder.value)
})

// Visualisation (lightbox)
const showViewer = ref(false)
const currentMediaIndex = ref(0)

// Liens externes
const showLinkModal = ref(false)
const linkForm = ref({ title: '', url: '' })
const isSavingLink = ref(false)
const linkToDelete = ref<FormationLink | null>(null)
const showLinkDeleteModal = ref(false)

const openLinkModal = () => {
  linkForm.value = { title: '', url: '' }
  showLinkModal.value = true
}

const saveLink = async () => {
  const url = linkForm.value.url.trim()
  if (!url) return
  isSavingLink.value = true
  try {
    const response = await fetch(`/formations/${props.formation.id}/links`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({
        title: linkForm.value.title.trim() || null,
        url,
      }),
    })
    if (response.ok) {
      showLinkModal.value = false
      router.reload({ only: ['links'] })
      showToast('Lien ajouté ✓', 'success')
    } else if (response.status === 419) {
      showToast('Session expirée — veuillez rafraîchir la page (F5)', 'error')
    } else {
      const data = await response.json().catch(() => ({}))
      showToast(data.message || 'Erreur : vérifiez l\'URL', 'error')
    }
  } catch (e) {
    showToast('Erreur lors de l\'ajout du lien', 'error')
  } finally {
    isSavingLink.value = false
  }
}

const confirmDeleteLink = (link: FormationLink) => {
  linkToDelete.value = link
  showLinkDeleteModal.value = true
}

const deleteLink = async () => {
  if (!linkToDelete.value) return
  try {
    const response = await fetch(`/formations/${props.formation.id}/links/${linkToDelete.value.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
    })
    if (response.ok) {
      showLinkDeleteModal.value = false
      linkToDelete.value = null
      router.reload({ only: ['links'] })
      showToast('Lien supprimé', 'success')
    }
  } catch (e) {
    showToast('Erreur lors de la suppression', 'error')
  }
}

const linkHostname = (url: string): string => {
  try { return new URL(url).hostname.replace(/^www\./, '') } catch { return url }
}

const linkFavicon = (url: string): string => {
  try {
    const u = new URL(url)
    return `https://www.google.com/s2/favicons?domain=${u.hostname}&sz=64`
  } catch { return '' }
}

// Toast notification
const toast = ref({ show: false, message: '', type: 'success' })
const showToast = (message: string, type: 'success' | 'error' = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => { toast.value.show = false }, 3000)
}

const currentMedia = computed(() => {
  if (filteredMedias.value.length === 0) return null
  return filteredMedias.value[currentMediaIndex.value]
})

const openViewer = (media: Media) => {
  const index = filteredMedias.value.findIndex(m => m.id === media.id)
  if (index !== -1) {
    currentMediaIndex.value = index
    showViewer.value = true
  }
}

const nextMedia = () => {
  if (currentMediaIndex.value < filteredMedias.value.length - 1) {
    currentMediaIndex.value++
  }
}

const prevMedia = () => {
  if (currentMediaIndex.value > 0) {
    currentMediaIndex.value--
  }
}

const closeViewer = () => {
  showViewer.value = false
}

// Gestion des touches clavier pour le viewer
const handleKeydown = (e: KeyboardEvent) => {
  if (!showViewer.value) return
  
  switch (e.key) {
    case 'ArrowLeft':
      prevMedia()
      break
    case 'ArrowRight':
      nextMedia()
      break
    case 'Escape':
      closeViewer()
      break
  }
}

// Attacher/détacher l'écouteur clavier
onMounted(() => {
  if (window.cloudinary) {
    isScriptLoading.value = false
    return
  }
  const script = document.createElement('script')
  script.src = 'https://upload-widget.cloudinary.com/global/all.js'
  script.async = true
  script.onload = () => { isScriptLoading.value = false }
  script.onerror = () => { 
    isScriptLoading.value = false
    scriptLoadError.value = true
  }
  document.head.appendChild(script)
  
  // Écouteur clavier pour le viewer
  window.addEventListener('keydown', handleKeydown)
})

// Nettoyer l'écouteur
onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})

// Filtrer les médias
const filteredMedias = computed(() => {
  let filtered = props.medias

  if (selectedAlbum.value !== 'all') {
    filtered = filtered.filter(m => m.album === selectedAlbum.value)
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(m => 
      (m.title?.toLowerCase().includes(query) || false) ||
      (m.album?.toLowerCase().includes(query) || false) ||
      (m.description?.toLowerCase().includes(query) || false)
    )
  }

  return filtered
})

// Statistiques
const stats = computed(() => {
  const total = filteredMedias.value.length
  const photos = filteredMedias.value.filter(m => m.type === 'photo').length
  const videos = filteredMedias.value.filter(m => m.type === 'video').length
  return { total, photos, videos }
})

// Tous les médias sélectionnés ?
const allSelected = computed(() => {
  return filteredMedias.value.length > 0 && selectedMedias.value.length === filteredMedias.value.length
})

// Sélectionner/désélectionner tout
const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedMedias.value = []
  } else {
    selectedMedias.value = filteredMedias.value.map(m => m.id)
  }
}

// Ouvrir modal album
const openAlbumModal = () => {
  if (selectedMedias.value.length === 0) {
    alert('Veuillez sélectionner au moins un média')
    return
  }
  newAlbumName.value = ''
  showAlbumModal.value = true
}

// Ajouter les médias sélectionnés à un album
const addToAlbum = async () => {
  if (!newAlbumName.value.trim()) {
    alert('Veuillez entrer un nom d\'album')
    return
  }
  
  isBatchUpdating.value = true
  try {
    const response = await fetch(`/formations/${props.formation.id}/medias/batch-update`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({
        media_ids: selectedMedias.value,
        album: newAlbumName.value.trim(),
      }),
    })
    
    if (response.ok) {
      const data = await response.json()
      showAlbumModal.value = false
      selectedMedias.value = []
      newAlbumName.value = ''
      // Rafraîchir la page pour voir les changements
      router.reload({ only: ['medias', 'albums'] })
    } else {
      alert('Erreur lors de l\'ajout à l\'album')
    }
  } catch (error) {
    console.error('Error:', error)
    alert('Erreur lors de l\'ajout à l\'album')
  } finally {
    isBatchUpdating.value = false
  }
}

// Ouvrir le widget Cloudinary
const openUploadWidget = () => {
  if (isScriptLoading.value) {
    alert('Chargement de Cloudinary en cours...')
    return
  }
  
  if (!window.cloudinary || scriptLoadError.value) {
    alert('Cloudinary non chargé. Veuillez rafraîchir la page.')
    return
  }

  const widget = window.cloudinary.createUploadWidget(
    {
      cloudName: props.cloudName,
      uploadPreset: props.uploadPreset,
      sources: ['local', 'camera', 'url'],
      resourceType: 'auto',
      multiple: true,
      maxFiles: 20,
      maxFileSize: 10485760,
      maxFileSizeMsg: 'Le fichier est trop volumineux. Maximum: 10 MB.\\n\\nConseils: Compressez avec HandBrake ou réduisez la résolution.',
      folder: `formations/${props.formation.id}`,
      showPoweredBy: false,
    },
    (error: any, result: any) => {
      if (error) {
        console.error('Upload error:', error)
        return
      }
      if (result.event === 'success') {
        saveMediaToDatabase(result.info)
      }
    }
  )

  widget.open()
}

// Sauvegarder dans la BDD
const saveMediaToDatabase = async (info: any) => {
  console.log('saveMediaToDatabase appelé avec:', info)
  isUploading.value = true
  try {
    const resourceType = info.resource_type
    console.log('resource_type:', resourceType)
    
    const payload = {
      cloudinary_public_id: info.public_id,
      url: info.secure_url,
      thumbnail_url: resourceType === 'video' 
        ? info.secure_url.replace(/\.[^/.]+$/, ".jpg")
        : info.secure_url,
      type: resourceType === 'video' ? 'video' : 'photo',
      title: info.original_filename || null,
      album: selectedAlbum.value !== 'all' ? selectedAlbum.value : null,
      file_size: Math.round(info.bytes / 1024),
      width: info.width || null,
      height: info.height || null,
      duration: info.duration || null,
      format: info.format || null,
    }
    console.log('Payload:', payload)
    
    const response = await fetch(`/formations/${props.formation.id}/medias`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify(payload),
    })
    
    console.log('Response status:', response.status)
    console.log('Response OK:', response.ok)
    
    if (response.ok) {
      // Toast discret au lieu d'alert
      showToast('✅ Upload réussi !', 'success')
      router.reload({ only: ['medias', 'albums', 'cloudinaryUsage'] })
    } else {
      const errorText = await response.text()
      console.error('Erreur sauvegarde média:', errorText)
      showToast('❌ Erreur lors de la sauvegarde', 'error')
    }
  } catch (error) {
    console.error('Error:', error)
    alert('Erreur: ' + (error as Error).message)
  } finally {
    isUploading.value = false
  }
}

// Supprimer un média
const confirmDelete = (media: Media) => {
  mediaToDelete.value = media
  showDeleteModal.value = true
}

const deleteMedia = async () => {
  if (!mediaToDelete.value) return
  
  try {
    const response = await fetch(`/formations/${props.formation.id}/medias/${mediaToDelete.value.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
    })
    
    if (response.ok) {
      showDeleteModal.value = false
      mediaToDelete.value = null
      router.reload({ only: ['medias', 'albums', 'cloudinaryUsage'] })
    } else {
      alert('Erreur lors de la suppression')
    }
  } catch (error) {
    console.error('Error:', error)
    alert('Erreur lors de la suppression')
  }
}

// Éditer un média
const openEditModal = (media: Media) => {
  mediaToEdit.value = media
  editForm.value = {
    title: media.title || '',
    album: media.album || '',
    description: media.description || '',
  }
  showEditModal.value = true
}

const updateMedia = async () => {
  if (!mediaToEdit.value) return
  
  try {
    const response = await fetch(`/formations/${props.formation.id}/medias/${mediaToEdit.value.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({
        title: editForm.value.title || null,
        album: editForm.value.album || null,
        description: editForm.value.description || null,
      }),
    })
    
    if (response.ok) {
      showEditModal.value = false
      mediaToEdit.value = null
      router.reload({ only: ['medias', 'albums'] })
    } else {
      alert('Erreur lors de la modification')
    }
  } catch (error) {
    console.error('Error:', error)
    alert('Erreur lors de la modification')
  }
}

// Télécharger
const downloadMedia = (media: Media) => {
  window.open(`/formations/${props.formation.id}/medias/${media.id}/download`, '_blank')
}

const downloadAlbum = () => {
  const album = selectedAlbum.value !== 'all' ? selectedAlbum.value : 'all'
  window.open(`/formations/${props.formation.id}/medias/album/download?album=${album}`, '_blank')
}

const fmtDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

</script>

<template>
  <div class="container">
    <!-- En-tête -->
    <div class="page-header">
      <div class="breadcrumb">
        <Link href="/formations" class="breadcrumb-link">Formations</Link>
        <span class="breadcrumb-separator">/</span>
        <Link :href="`/formations/${formation.id}`" class="breadcrumb-link">{{ formation.name }}</Link>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Médiathèque</span>
      </div>
      <div class="page-header-row">
        <Link :href="`/formations/${formation.id}`" class="icon-back">
          <span class="material-symbols-outlined">arrow_back</span>
        </Link>
        <div class="page-header-icon">
          <span class="material-symbols-outlined">photo_library</span>
        </div>
        <div>
          <h1 class="page-title">Médiathèque</h1>
          <p class="page-subtitle">{{ formation.name }}</p>
        </div>
      </div>
    </div>

    <!-- Jauge de stockage -->
    <StorageGauge :usage="cloudinaryUsage" />

    <!-- Info limitation -->
    <div class="upload-info">
      <span class="material-symbols-outlined">info</span>
      <span>Maximum par fichier : <strong>10 MB</strong>. Compressez les vidéos volumineuses avant upload.</span>
    </div>

    <!-- Barre d'actions -->
    <div class="actions-bar">
      <div class="filters">
        <select v-model="selectedAlbum" class="select-album">
          <option value="all">📁 Tous les albums</option>
          <option v-for="album in albums" :key="album" :value="album">📁 {{ album }}</option>
        </select>
        <input v-model="searchQuery" type="text" placeholder="Rechercher..." class="search-input" />
      </div>
      <div class="actions">
        <button @click="openAlbumModal" class="btn-secondary" :disabled="selectedMedias.length === 0">
          <span class="material-symbols-outlined">create_new_folder</span>
          {{ selectedMedias.length > 0 ? `Ajouter à l'album (${selectedMedias.length})` : "Ajouter à l'album" }}
        </button>
        <button @click="downloadAlbum" class="btn-secondary" :disabled="filteredMedias.length === 0">
          <span class="material-symbols-outlined">download</span>
          Télécharger {{ selectedAlbum !== 'all' ? 'l\'album' : 'tout' }}
        </button>
        <button @click="openUploadWidget" class="btn-primary" :disabled="isUploading || isScriptLoading">
          <span v-if="isUploading || isScriptLoading" class="spinner" />
          <span v-else class="material-symbols-outlined">add_photo_alternate</span>
          {{ isUploading ? 'Upload...' : (isScriptLoading ? 'Chargement...' : 'Ajouter des médias') }}
        </button>
        <button @click="openLinkModal" class="btn-secondary">
          <span class="material-symbols-outlined">link</span>
          Ajouter un lien
        </button>
      </div>
    </div>

    <!-- Liste des liens -->
    <div v-if="links && links.length > 0" class="links-section">
      <div class="links-header">
        <h3 class="links-title">
          <span class="material-symbols-outlined" style="font-size:20px">link</span>
          Liens ({{ links.length }})
        </h3>
      </div>
      <div class="links-grid">
        <a v-for="link in links" :key="link.id" :href="link.url" target="_blank" rel="noopener noreferrer" class="link-card">
          <div class="link-icon">
            <img v-if="linkFavicon(link.url)" :src="linkFavicon(link.url)" alt="" @error="($event.target as HTMLImageElement).style.display='none'" />
            <span class="material-symbols-outlined fallback-icon">public</span>
          </div>
          <div class="link-info">
            <h4 class="link-title">{{ link.title || linkHostname(link.url) }}</h4>
            <span class="link-host">{{ linkHostname(link.url) }}</span>
          </div>
          <button @click.prevent.stop="confirmDeleteLink(link)" class="link-delete" title="Supprimer">
            <span class="material-symbols-outlined">delete</span>
          </button>
        </a>
      </div>
    </div>

    <!-- Sélection multiple -->
    <div v-if="filteredMedias.length > 0" class="selection-bar">
      <label class="checkbox-label">
        <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
        <span>Tout sélectionner</span>
      </label>
      <span v-if="selectedMedias.length > 0" class="selection-count">
        {{ selectedMedias.length }} sélectionné(s)
      </span>
    </div>

    <!-- Stats -->
    <div class="stats-bar">
      <div class="stat-item"><span class="stat-value">{{ stats.total }}</span><span class="stat-label">médias</span></div>
      <div class="stat-item"><span class="stat-value">{{ stats.photos }}</span><span class="stat-label">photos</span></div>
      <div class="stat-item"><span class="stat-value">{{ stats.videos }}</span><span class="stat-label">vidéos</span></div>
    </div>

    <!-- Vue DOSSIERS -->
    <template v-if="viewMode === 'folders'">
      <!-- Bouton retour si on est dans un dossier -->
      <div v-if="currentFolder" class="folder-header">
        <button @click="backToFolders" class="btn-secondary">
          <span class="material-symbols-outlined">arrow_back</span>
          Retour aux dossiers
        </button>
        <h3 class="folder-title">
          📁 {{ currentFolder === '__root__' ? 'Médias sans album' : currentFolder }}
          <span class="folder-count">({{ currentFolderMedias.length }} médias)</span>
        </h3>
      </div>

      <!-- Grille des dossiers (albums) -->
      <div v-if="!currentFolder" class="folders-grid">
        <!-- Dossier "Sans album" -->
        <div v-if="albumsWithMedias['__root__']?.length > 0" 
             class="folder-card" 
             @click="openFolder('__root__')">
          <div class="folder-icon">
            <span class="material-symbols-outlined">folder</span>
          </div>
          <div class="folder-info">
            <h4>Médias sans album</h4>
            <span class="folder-count">{{ albumsWithMedias['__root__'].length }} médias</span>
          </div>
        </div>

        <!-- Dossiers albums -->
        <div v-for="album in albums" :key="album" 
             class="folder-card" 
             @click="openFolder(album)">
          <div class="folder-icon">
            <span class="material-symbols-outlined">folder</span>
          </div>
          <div class="folder-info">
            <h4>{{ album }}</h4>
            <span class="folder-count">{{ albumsWithMedias[album]?.length || 0 }} médias</span>
          </div>
        </div>
      </div>

      <!-- Grille des médias du dossier courant -->
      <div v-else-if="currentFolderMedias.length > 0" class="medias-grid">
        <div v-for="media in currentFolderMedias" :key="media.id" class="media-card" :class="{ selected: selectedMedias.includes(media.id) }">
          <div class="media-checkbox">
            <input type="checkbox" :value="media.id" v-model="selectedMedias" :id="`media-${media.id}`" />
          </div>
          <div class="media-thumbnail" @click="openViewer(media)">
            <img :src="media.thumbnail_url" :alt="media.title || 'Media'" loading="lazy" />
            <div v-if="media.type === 'video'" class="video-overlay">
              <span class="material-symbols-outlined play-icon">play_circle</span>
              <span v-if="media.formatted_duration" class="duration">{{ media.formatted_duration }}</span>
            </div>
            <div class="media-actions" @click.stop>
              <button @click="openEditModal(media)" class="action-btn" title="Modifier"><span class="material-symbols-outlined">edit</span></button>
              <button @click="downloadMedia(media)" class="action-btn" title="Télécharger"><span class="material-symbols-outlined">download</span></button>
              <button @click="confirmDelete(media)" class="action-btn delete" title="Supprimer"><span class="material-symbols-outlined">delete</span></button>
            </div>
          </div>
          <div class="media-info">
            <h4 class="media-title">{{ media.title || 'Sans titre' }}</h4>
            <div class="media-meta">
              <span class="media-size">{{ media.formatted_size }}</span>
              <span class="media-date">{{ fmtDate(media.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state du dossier -->
      <div v-else class="empty-state">
        <span class="material-symbols-outlined empty-icon">folder_open</span>
        <h3>Ce dossier est vide</h3>
        <button @click="openUploadWidget" class="btn-primary">
          <span class="material-symbols-outlined">add_photo_alternate</span>
          Ajouter des médias
        </button>
      </div>
    </template>

    <!-- Vue GRILLE (ancien mode) -->
    <div v-else-if="filteredMedias.length > 0" class="medias-grid">
      <div v-for="media in filteredMedias" :key="media.id" class="media-card" :class="{ selected: selectedMedias.includes(media.id) }">
        <div class="media-checkbox">
          <input type="checkbox" :value="media.id" v-model="selectedMedias" :id="`media-${media.id}`" />
        </div>
        <div class="media-thumbnail" @click="openViewer(media)">
          <img :src="media.thumbnail_url" :alt="media.title || 'Media'" loading="lazy" />
          <div v-if="media.type === 'video'" class="video-overlay">
            <span class="material-symbols-outlined play-icon">play_circle</span>
            <span v-if="media.formatted_duration" class="duration">{{ media.formatted_duration }}</span>
          </div>
          <div class="media-actions" @click.stop>
            <button @click="openEditModal(media)" class="action-btn" title="Modifier"><span class="material-symbols-outlined">edit</span></button>
            <button @click="downloadMedia(media)" class="action-btn" title="Télécharger"><span class="material-symbols-outlined">download</span></button>
            <button @click="confirmDelete(media)" class="action-btn delete" title="Supprimer"><span class="material-symbols-outlined">delete</span></button>
          </div>
        </div>
        <div class="media-info">
          <h4 class="media-title">{{ media.title || 'Sans titre' }}</h4>
          <div class="media-meta">
            <span v-if="media.album" class="media-album">📁 {{ media.album }}</span>
            <span class="media-size">{{ media.formatted_size }}</span>
            <span class="media-date">{{ fmtDate(media.created_at) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="empty-state">
      <span class="material-symbols-outlined empty-icon">photo_library</span>
      <h3>Aucun média</h3>
      <button @click="openUploadWidget" class="btn-primary">
        <span class="material-symbols-outlined">add_photo_alternate</span>
        Ajouter des médias
      </button>
    </div>

    <!-- Modal suppression -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <span class="material-symbols-outlined modal-icon warning">warning</span>
          <h3>Confirmer la suppression</h3>
        </div>
        <div class="modal-body">
          <p>Supprimer <strong>{{ mediaToDelete?.title || 'ce média' }}</strong> ?</p>
          <p class="modal-warning">Action irréversible. Le fichier sera aussi supprimé de Cloudinary.</p>
        </div>
        <div class="modal-footer">
          <button @click="showDeleteModal = false" class="btn-secondary">Annuler</button>
          <button @click="deleteMedia" class="btn-danger">
            <span class="material-symbols-outlined">delete</span> Supprimer
          </button>
        </div>
      </div>
    </div>

    <!-- Modal édition -->
    <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <span class="material-symbols-outlined modal-icon">edit</span>
          <h3>Modifier le média</h3>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Titre</label>
            <input v-model="editForm.title" type="text" class="form-input" placeholder="Titre" />
          </div>
          <div class="form-group">
            <label>Album</label>
            <input v-model="editForm.album" type="text" class="form-input" placeholder="Album" list="albums-list" />
            <datalist id="albums-list">
              <option v-for="album in albums" :key="album" :value="album" />
            </datalist>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="editForm.description" class="form-textarea" rows="3" placeholder="Description"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showEditModal = false" class="btn-secondary">Annuler</button>
          <button @click="updateMedia" class="btn-primary">
            <span class="material-symbols-outlined">save</span> Enregistrer
          </button>
        </div>
      </div>
    </div>

    <!-- Modal création d'album (sélection multiple) -->
    <div v-if="showAlbumModal" class="modal-overlay" @click.self="showAlbumModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <span class="material-symbols-outlined modal-icon">create_new_folder</span>
          <h3>Ajouter à un album</h3>
        </div>
        <div class="modal-body">
          <p class="modal-info">{{ selectedMedias.length }} média(s) sélectionné(s)</p>
          <div class="form-group">
            <label>Nom de l'album</label>
            <input v-model="newAlbumName" type="text" class="form-input" placeholder="Ex: Session 1, Projet Final..." list="existing-albums" autofocus />
            <datalist id="existing-albums">
              <option v-for="album in albums" :key="album" :value="album" />
            </datalist>
          </div>
          <div v-if="albums.length > 0" class="existing-albums">
            <label>Albums existants :</label>
            <div class="album-chips">
              <button v-for="album in albums" :key="album" @click="newAlbumName = album" class="album-chip">
                📁 {{ album }}
              </button>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showAlbumModal = false" class="btn-secondary">Annuler</button>
          <button @click="addToAlbum" class="btn-primary" :disabled="isBatchUpdating || !newAlbumName.trim()">
            <span v-if="isBatchUpdating" class="spinner" />
            <span v-else class="material-symbols-outlined">add</span>
            {{ isBatchUpdating ? 'Ajout en cours...' : "Ajouter à l'album" }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal ajout de lien -->
    <div v-if="showLinkModal" class="modal-overlay" @click.self="showLinkModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <span class="material-symbols-outlined modal-icon">link</span>
          <h3>Ajouter un lien</h3>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Intitulé <span style="color:#94a3b8;font-weight:400">(optionnel)</span></label>
            <input v-model="linkForm.title" type="text" class="form-input" placeholder="Ex : Support de cours, Drive partagé..." />
          </div>
          <div class="form-group">
            <label>URL <span style="color:#E5004C">*</span></label>
            <input v-model="linkForm.url" type="url" class="form-input" placeholder="https://..." autofocus @keydown.enter.prevent="saveLink" />
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showLinkModal = false" class="btn-secondary">Annuler</button>
          <button @click="saveLink" class="btn-primary" :disabled="isSavingLink || !linkForm.url.trim()">
            <span v-if="isSavingLink" class="spinner" />
            <span v-else class="material-symbols-outlined">add</span>
            {{ isSavingLink ? 'Ajout...' : 'Ajouter' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal suppression lien -->
    <div v-if="showLinkDeleteModal" class="modal-overlay" @click.self="showLinkDeleteModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <span class="material-symbols-outlined modal-icon warning">warning</span>
          <h3>Supprimer le lien</h3>
        </div>
        <div class="modal-body">
          <p>Supprimer <strong>{{ linkToDelete?.title || linkToDelete?.url }}</strong> ?</p>
        </div>
        <div class="modal-footer">
          <button @click="showLinkDeleteModal = false" class="btn-secondary">Annuler</button>
          <button @click="deleteLink" class="btn-danger">
            <span class="material-symbols-outlined">delete</span> Supprimer
          </button>
        </div>
      </div>
    </div>

    <!-- Modal visualisation (lightbox) -->
    <div v-if="showViewer && currentMedia" class="viewer-overlay" @click.self="closeViewer">
      <!-- Navigation -->
      <button v-if="currentMediaIndex > 0" @click.stop="prevMedia" class="viewer-nav viewer-prev">
        <span class="material-symbols-outlined">chevron_left</span>
      </button>
      <button v-if="currentMediaIndex < filteredMedias.length - 1" @click.stop="nextMedia" class="viewer-nav viewer-next">
        <span class="material-symbols-outlined">chevron_right</span>
      </button>

      <!-- Fermer -->
      <button @click="closeViewer" class="viewer-close">
        <span class="material-symbols-outlined">close</span>
      </button>

      <!-- Contenu -->
      <div class="viewer-content" @click.stop>
        <!-- Image -->
        <img v-if="currentMedia.type === 'photo'" :src="currentMedia.url" :alt="currentMedia.title || 'Image'" class="viewer-image" />
        
        <!-- Vidéo -->
        <video v-else-if="currentMedia.type === 'video'" :src="currentMedia.url" controls class="viewer-video">
          Votre navigateur ne supporte pas la lecture vidéo.
        </video>

        <!-- Info -->
        <div class="viewer-info">
          <h3>{{ currentMedia.title || 'Sans titre' }}</h3>
          <p v-if="currentMedia.album" class="viewer-album">📁 {{ currentMedia.album }}</p>
          <p class="viewer-meta">{{ currentMedia.formatted_size }} • {{ fmtDate(currentMedia.created_at) }}</p>
          <p class="viewer-counter">{{ currentMediaIndex + 1 }} / {{ filteredMedias.length }}</p>
        </div>
      </div>
    </div>

    <!-- Toast notification -->
    <transition name="toast">
      <div v-if="toast.show" :class="['toast', toast.type]">{{ toast.message }}</div>
    </transition>
  </div>
</template>

<style scoped>
/* Section liens externes */
.links-section { margin: 20px 0 24px; }
.links-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.links-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; color: #515f74; text-transform: uppercase; letter-spacing: 0.04em; }
.links-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
.link-card {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px; background: #fff; border: 1px solid #e0e3e5;
  border-radius: 10px; text-decoration: none; color: inherit;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
  position: relative; overflow: hidden;
}
.link-card:hover { border-color: #E5004C; box-shadow: 0 4px 12px rgba(229,0,76,0.08); transform: translateY(-1px); }
.link-icon {
  flex-shrink: 0; width: 40px; height: 40px;
  display: flex; align-items: center; justify-content: center;
  background: #f8f9fa; border-radius: 8px; position: relative;
}
.link-icon img { width: 24px; height: 24px; border-radius: 4px; position: relative; z-index: 1; }
.link-icon .fallback-icon { position: absolute; color: #9aaabb; font-size: 22px; }
.link-info { flex: 1; min-width: 0; }
.link-title { font-size: 14px; font-weight: 600; color: #191c1e; margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.link-host { font-size: 12px; color: #9aaabb; }
.link-delete {
  flex-shrink: 0; background: transparent; border: none; cursor: pointer;
  color: #9aaabb; padding: 6px; border-radius: 6px; opacity: 0;
  transition: opacity 0.15s, background 0.15s, color 0.15s;
}
.link-card:hover .link-delete { opacity: 1; }
.link-delete:hover { background: #fee2e2; color: #dc2626; }
.toast-enter-active, .toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(10px); }

.container { max-width: 1280px; margin: 0 auto; padding: 24px; }
.page-header { margin-bottom: 24px; }
.breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #9aaabb; margin-bottom: 12px; }
.breadcrumb-link { color: #515f74; text-decoration: none; }
.breadcrumb-link:hover { color: #E5004C; }
.breadcrumb-current { color: #191c1e; font-weight: 500; }
.page-header-row { display: flex; align-items: center; gap: 14px; }
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent;
    text-decoration: none; flex-shrink: 0; transition: background 0.15s, color 0.15s;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }
.page-header-icon {
    display: flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff;
}
.page-header-icon .material-symbols-outlined { font-size: 24px; }
.page-title { font-size: 22px; font-weight: 700; color: #191c1e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #515f74; margin-top: 2px; }
.upload-info { display: flex; align-items: center; gap: 8px; padding: 12px 16px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; font-size: 13px; color: #0369a1; margin-bottom: 20px; }
.actions-bar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.filters { display: flex; gap: 12px; flex-wrap: wrap; }
.select-album { padding: 10px 14px; border: 1px solid #e0e3e5; border-radius: 8px; font-size: 14px; min-width: 200px; }
.search-input { padding: 10px 14px; border: 1px solid #e0e3e5; border-radius: 8px; font-size: 14px; width: 200px; }
.actions { display: flex; gap: 12px; }
.btn-primary { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #E5004C; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #fff; color: #515f74; border: 1px solid #e0e3e5; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-secondary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-danger { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #dc2626; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.stats-bar { display: flex; gap: 24px; padding: 16px 20px; background: #f8f9fa; border-radius: 10px; margin-bottom: 20px; }
.stat-item { display: flex; align-items: baseline; gap: 6px; }
.stat-value { font-size: 20px; font-weight: 700; color: #191c1e; }
.stat-label { font-size: 13px; color: #9aaabb; }
.medias-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
.media-card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; overflow: hidden; }
.media-thumbnail { position: relative; aspect-ratio: 16/9; overflow: hidden; }
.media-thumbnail img { width: 100%; height: 100%; object-fit: cover; }
.video-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.3); }
.play-icon { font-size: 48px; color: #fff; }
.duration { position: absolute; bottom: 8px; right: 8px; padding: 4px 8px; background: rgba(0,0,0,0.7); color: #fff; border-radius: 4px; font-size: 12px; font-weight: 600; }
.media-actions { position: absolute; top: 8px; right: 8px; display: flex; gap: 4px; opacity: 0; transition: opacity 0.15s; }
.media-card:hover .media-actions { opacity: 1; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: rgba(255,255,255,0.9); border: none; border-radius: 6px; cursor: pointer; }
.action-btn.delete:hover { background: #fef2f2; color: #dc2626; }
.media-info { padding: 16px; }
.media-title { font-size: 14px; font-weight: 600; color: #191c1e; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.media-meta { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #9aaabb; }
.media-album { color: #E5004C; font-weight: 500; }
.empty-state { text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 12px; }
.empty-icon { font-size: 64px; color: #ddd; margin-bottom: 16px; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal-content { background: #fff; border-radius: 12px; width: 100%; max-width: 480px; }
.modal-header { display: flex; align-items: center; gap: 12px; padding: 20px 24px; border-bottom: 1px solid #f0f1f3; }
.modal-icon { font-size: 24px; color: #1F3A4D; }
.modal-icon.warning { color: #f59e0b; }
.modal-body { padding: 20px 24px; }
.modal-warning { color: #dc2626; font-size: 13px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 12px; padding: 16px 24px 20px; border-top: 1px solid #f0f1f3; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #191c1e; margin-bottom: 6px; }
.form-input, .form-textarea { width: 100%; padding: 10px 14px; border: 1px solid #e0e3e5; border-radius: 8px; font-size: 14px; }
.form-input:focus, .form-textarea:focus { outline: none; border-color: #E5004C; }
.spinner { width: 16px; height: 16px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Sélection multiple */
.selection-bar { display: flex; align-items: center; gap: 16px; padding: 12px 16px; background: #f8f9fa; border-radius: 8px; margin-bottom: 16px; }
.checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: #515f74; }
.checkbox-label input { width: 18px; height: 18px; accent-color: #E5004C; }
.selection-count { font-size: 13px; color: #E5004C; font-weight: 600; }

/* Checkbox sur les cartes */
.media-card { position: relative; transition: all 0.15s; }
.media-card.selected { border-color: #E5004C; box-shadow: 0 0 0 2px rgba(229,0,76,0.1); }
.media-checkbox { position: absolute; top: 8px; left: 8px; z-index: 10; }
.media-checkbox input { width: 20px; height: 20px; accent-color: #E5004C; cursor: pointer; }

/* Modal album */
.modal-info { font-size: 14px; color: #515f74; margin-bottom: 16px; padding: 12px; background: #f8f9fa; border-radius: 6px; }
.existing-albums { margin-top: 16px; }
.existing-albums label { font-size: 13px; color: #9aaabb; margin-bottom: 8px; display: block; }
.album-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.album-chip { padding: 6px 12px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 20px; font-size: 13px; color: #0369a1; cursor: pointer; transition: all 0.15s; }
.album-chip:hover { background: #0369a1; color: #fff; }

/* Viewer (lightbox) */
.viewer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.95); display: flex; align-items: center; justify-content: center; z-index: 2000; }
.viewer-content { max-width: 90vw; max-height: 90vh; display: flex; flex-direction: column; align-items: center; }
.viewer-image { max-width: 90vw; max-height: 75vh; object-fit: contain; border-radius: 8px; }
.viewer-video { max-width: 90vw; max-height: 75vh; border-radius: 8px; }
.viewer-info { text-align: center; color: #fff; margin-top: 16px; padding: 0 20px; }
.viewer-info h3 { font-size: 18px; font-weight: 600; margin-bottom: 8px; }
.viewer-album { font-size: 14px; color: #E5004C; margin-bottom: 4px; }
.viewer-meta { font-size: 13px; color: #9aaabb; margin-bottom: 8px; }
.viewer-counter { font-size: 13px; color: #666; }
.viewer-nav { position: absolute; top: 50%; transform: translateY(-50%); width: 48px; height: 48px; background: rgba(255,255,255,0.1); border: none; border-radius: 50%; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.15s; }
.viewer-nav:hover { background: rgba(255,255,255,0.2); }
.viewer-prev { left: 20px; }
.viewer-next { right: 20px; }
.viewer-close { position: absolute; top: 20px; right: 20px; width: 44px; height: 44px; background: rgba(255,255,255,0.1); border: none; border-radius: 50%; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.15s; }
.viewer-close:hover { background: rgba(255,255,255,0.2); }

/* Vue Dossiers */
.folders-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
.folder-card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 24px; text-align: center; cursor: pointer; transition: all 0.15s; }
.folder-card:hover { border-color: #E5004C; box-shadow: 0 4px 12px rgba(229,0,76,0.1); transform: translateY(-2px); }
.folder-icon { font-size: 64px; color: #fbbf24; margin-bottom: 12px; }
.folder-icon .material-symbols-outlined { font-size: 64px; }
.folder-info h4 { font-size: 16px; font-weight: 600; color: #191c1e; margin-bottom: 4px; word-break: break-word; }
.folder-count { font-size: 13px; color: #9aaabb; }
.folder-header { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #f0f1f3; }
.folder-title { font-size: 18px; font-weight: 600; color: #191c1e; }
.folder-title .folder-count { font-weight: 400; color: #9aaabb; margin-left: 8px; }

/* Toast notification */
.toast { position: fixed; bottom: 24px; right: 24px; padding: 16px 24px; border-radius: 10px; color: #fff; font-weight: 500; z-index: 3000; animation: slideIn 0.3s ease; }
.toast.success { background: #10b981; }
.toast.error { background: #dc2626; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>
