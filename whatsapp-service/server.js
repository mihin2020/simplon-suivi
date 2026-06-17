'use strict'

const express        = require('express')
const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js')
const qrcode         = require('qrcode')
const fs             = require('fs')
const path           = require('path')
const os             = require('os')
const { execSync }   = require('child_process')

const app  = express()
app.use(express.json({ limit: '50mb' }))

const PORT = process.env.PORT || 3001

// ── Session stockée HORS du projet ──────────────────────────────────────────
// Évite que Vite surveille les fichiers du profil Edge/Chrome et recharge la page.
const AUTH_PATH = path.join(os.homedir(), '.simplon-whatsapp-auth')

// ── Détection navigateur ─────────────────────────────────────────────────────
function findBrowser() {
    const candidates = [
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        path.join('C:\\Users', process.env.USERNAME || '', 'AppData\\Local\\Google\\Chrome\\Application\\chrome.exe'),
    ]
    for (const p of candidates) {
        if (fs.existsSync(p)) { console.log('[WA] Navigateur :', p); return p }
    }
    console.warn('[WA] ⚠️  Aucun navigateur trouvé — Chromium embarqué sera utilisé.')
    return undefined
}

const BROWSER_PATH = findBrowser()

// ── État global ──────────────────────────────────────────────────────────────
let client          = null
let qrDataUrl       = null
let isConnected     = false
let isAuthenticating = false
let isInitializing  = false
let restartTimer    = null
let readyWatchdog   = null  // détecte le blocage authentifié→prêt
let recovering      = false

// Buffer circulaire des messages entrants (max 1000)
const incomingMessages = []

// Buffer des messages révoqués côté apprenant (max 500)
const revokedBuffer = []

// ── Table LID → numéro de téléphone ─────────────────────────────────────────
// WhatsApp multi-device identifie les contacts via un LID (ex: 200197133082709)
// au lieu du numéro de téléphone. Cette table est construite à l'envoi :
// on connait le téléphone, getNumberId() retourne le LID → on stocke le lien.
// Persistée sur disque pour survivre aux redémarrages du serveur.
const lidToPhone = new Map()
const LID_MAP_FILE = path.join(AUTH_PATH, 'lid-map.json')

function loadLidMap() {
    try {
        if (fs.existsSync(LID_MAP_FILE)) {
            const data = JSON.parse(fs.readFileSync(LID_MAP_FILE, 'utf8'))
            Object.entries(data).forEach(([lid, phone]) => lidToPhone.set(lid, phone))
            console.log(`[WA] 🗺️  ${lidToPhone.size} correspondance(s) LID chargée(s)`)
        }
    } catch {}
}

function saveLidMap() {
    try {
        fs.mkdirSync(AUTH_PATH, { recursive: true })
        fs.writeFileSync(LID_MAP_FILE, JSON.stringify(Object.fromEntries(lidToPhone), null, 2))
    } catch {}
}

function registerLid(numberId, realPhone) {
    if (!numberId) return
    const ser = String(numberId._serialized ?? '')
    const usr = String(numberId.user ?? '')
    if (ser.endsWith('@lid') && usr && !lidToPhone.has(usr)) {
        lidToPhone.set(usr, realPhone)
        saveLidMap()
        console.log(`[WA] 🗺️  Nouveau mapping : LID ${usr} → ${realPhone}`)
    }
}

function resetState() {
    isConnected      = false
    isAuthenticating = false
    isInitializing   = false
    if (readyWatchdog) { clearTimeout(readyWatchdog); readyWatchdog = null }
}

// ── Tuer les Chrome/Edge orphelins qui utilisent notre profil ────────────────
// Quand Node est tué brutalement, Chrome reste en vie et bloque le prochain démarrage.
// On les cible précisément via leur ligne de commande (contient le nom du dossier auth).
function killOrphanedBrowsers() {
    const marker = '.simplon-whatsapp-auth'
    try {
        const script = [
            `$procs = Get-WmiObject Win32_Process`,
            `| Where-Object { ($_.Name -like '*chrome*' -or $_.Name -like '*msedge*')`,
            `  -and $_.CommandLine -like '*${marker}*' }`,
            `if ($procs) { $procs | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue } }`,
        ].join(' ')
        execSync(`powershell -NonInteractive -Command "${script}"`, { stdio: 'pipe', timeout: 8000 })
        console.log('[WA] 🔧 Chrome/Edge orphelins nettoyés.')
    } catch { /* non critique */ }
}

// ── Supprimer les fichiers verrou du profil Chrome ───────────────────────────
function releaseBrowserLock() {
    const locks = [
        path.join(AUTH_PATH, 'session', 'SingletonLock'),
        path.join(AUTH_PATH, 'session', 'SingletonCookie'),
        path.join(AUTH_PATH, 'session', 'SingletonSocket'),
    ]
    for (const f of locks) {
        try { if (fs.existsSync(f)) { fs.unlinkSync(f); console.log(`[WA] 🔓 Verrou supprimé : ${path.basename(f)}`) } }
        catch {}
    }
}

// ── Initialisation client wwebjs ─────────────────────────────────────────────
async function initClient() {
    if (isInitializing) return
    isInitializing = true

    // 1. Tuer les Chrome orphelins qui bloquent le profil
    killOrphanedBrowsers()
    await sleep(600)         // laisser le temps aux processus de mourir
    // 2. Supprimer les fichiers verrou résiduels
    releaseBrowserLock()

    console.log('[WA] Initialisation du client WhatsApp...')

    client = new Client({
        authStrategy: new LocalAuth({ dataPath: AUTH_PATH }),
        puppeteer: {
            // headless: true = pas de fenêtre visible, plus stable en background
            headless: true,
            executablePath: BROWSER_PATH,
            protocolTimeout: 120_000,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-accelerated-2d-canvas',
                '--disable-features=VizDisplayCompositor,TranslateUI,BlinkGenPropertyTrees',
                '--window-size=1280,720',
                '--disable-extensions',
            ],
        },
        qrMaxRetries:      5,
        authTimeoutMs:     0,  // pas de timeout sur l'auth
        takeoverOnConflict: true,
        takeoverTimeoutMs: 0,
    })

    // ── Événements wwebjs ────────────────────────────────────────────────────

    client.on('loading_screen', (percent) => {
        if (percent % 25 === 0) console.log(`[WA] Chargement ${percent}%`)
    })

    client.on('qr', async (qr) => {
        console.log('[WA] 📱 QR code généré — en attente du scan.')
        isAuthenticating = false
        if (readyWatchdog) { clearTimeout(readyWatchdog); readyWatchdog = null }
        try { qrDataUrl = await qrcode.toDataURL(qr) }
        catch (e) { console.error('[WA] Erreur génération QR :', e.message) }
    })

    client.on('authenticated', () => {
        console.log('[WA] 🔐 QR scanné — synchronisation en cours...')
        isAuthenticating = true
        qrDataUrl = null

        // Si "ready" ne se déclenche pas dans les 120 secondes après l'auth,
        // la session est corrompue → on efface et on redémarre.
        if (readyWatchdog) clearTimeout(readyWatchdog)
        readyWatchdog = setTimeout(async () => {
            console.warn('[WA] ⚠️  Timeout — connexion bloquée. Nettoyage de session...')
            resetState()
            clearAuth()
            await safeDestroy()
            scheduleRestart(3_000)
        }, 120_000)
    })

    client.on('ready', () => {
        console.log('[WA] ✅ Connecté et prêt à envoyer/recevoir.')
        isConnected      = true
        isAuthenticating = false
        qrDataUrl        = null
        isInitializing   = false
        if (readyWatchdog) { clearTimeout(readyWatchdog); readyWatchdog = null }
    })

    // ── Réception de messages ────────────────────────────────────────────────
    client.on('message', async (msg) => {
        if (msg.fromMe) return

        const from = String(msg.from ?? '')
        if (!from) return

        // Bloquer : statuts WhatsApp, groupes, newsletters, broadcasts
        if (
            from.includes('broadcast') ||
            from.includes('status')    ||
            from.endsWith('@g.us')     ||
            from.endsWith('@newsletter')
        ) return

        let rawPhone = ''

        if (from.endsWith('@c.us')) {
            rawPhone = from.replace('@c.us', '')

        } else if (from.endsWith('@lid')) {
            const lidUser = from.replace('@lid', '')

            // Niveau 1 : table LID → téléphone construite à l'envoi (la plus fiable)
            if (lidToPhone.has(lidUser)) {
                rawPhone = lidToPhone.get(lidUser)
                console.log(`[WA] 🗺️  @lid ${lidUser} → ${rawPhone} (via mapping)`)
            } else {
                // Niveau 2 : msg.id.remote (@c.us si dispo)
                const remote = String(msg.id?.remote ?? '')
                if (remote.endsWith('@c.us')) {
                    rawPhone = remote.replace('@c.us', '')
                    console.log(`[WA] 🔍 @lid résolu via id.remote → ${rawPhone}`)
                } else {
                    // Niveau 3 : chat.id._serialized (@c.us si dispo)
                    try {
                        const chat = await msg.getChat()
                        const chatId = String(chat?.id?._serialized ?? '')
                        if (chatId.endsWith('@c.us')) {
                            rawPhone = chatId.replace('@c.us', '')
                            console.log(`[WA] 🔍 @lid résolu via getChat() → ${rawPhone}`)
                        } else {
                            // Niveau 4 : contact.number si longueur valide (≤13)
                            const contact = await msg.getContact()
                            const num = String(contact.number ?? '').replace(/\D/g, '')
                            if (num && num.length <= 13) {
                                rawPhone = num
                                console.log(`[WA] 🔍 @lid résolu via getContact() → ${rawPhone}`)
                            } else {
                                console.warn(`[WA] ⚠️  @lid ${lidUser} non résolvable — ajoutez ce contact puis renvoyez un message pour créer le mapping`)
                                return
                            }
                        }
                    } catch (e) {
                        console.error(`[WA] ❌ Erreur résolution @lid : ${e.message}`)
                        return
                    }
                }
            }

        } else {
            console.log(`[WA] Format inconnu ignoré : ${from}`)
            return
        }

        if (!rawPhone || rawPhone.length < 6) {
            console.warn(`[WA] ⚠️  Numéro invalide extrait : "${rawPhone}" — ignoré`)
            return
        }

        const shortPhone = rawPhone.replace(/^226/, '')

        // Téléchargement de la pièce jointe si présente
        let attachmentData     = null
        let attachmentMimetype = null
        let attachmentFilename = null
        if (msg.hasMedia) {
            try {
                const media = await msg.downloadMedia()
                // Limite : ne garder le base64 que si < 8 Mo (évite les timeouts HTTP)
                const sizeBytes = Math.ceil((media.data.length * 3) / 4)
                if (sizeBytes <= 8 * 1024 * 1024) {
                    attachmentData     = media.data
                    attachmentMimetype = media.mimetype
                    attachmentFilename = media.filename || `fichier_${Date.now()}`
                    console.log(`[WA] 📎 Média reçu : ${attachmentMimetype} — ${(sizeBytes/1024).toFixed(0)} KB`)
                } else {
                    attachmentMimetype = media.mimetype
                    attachmentFilename = media.filename || `fichier_${Date.now()}`
                    console.warn(`[WA] ⚠️  Média trop grand (${(sizeBytes/1024/1024).toFixed(1)} Mo) — base64 non stocké`)
                }
            } catch (e) {
                console.error(`[WA] ⚠️  Échec téléchargement média : ${e.message}`)
            }
        }

        incomingMessages.push({
            from:                rawPhone,
            phone_short:         shortPhone,
            body:                msg.body || '',
            wa_id:               msg.id._serialized,  // identifiant unique du message
            attachment_data:     attachmentData,
            attachment_mimetype: attachmentMimetype,
            attachment_filename: attachmentFilename,
            timestamp:           msg.timestamp,
            received_at:         new Date().toISOString(),
        })

        if (incomingMessages.length > 1000) incomingMessages.shift()

        const preview = attachmentData ? `[${attachmentMimetype}]` : `"${String(msg.body).slice(0, 80)}"`
        console.log(`[WA] 📨 Reçu de +${rawPhone} (short:${shortPhone}) : ${preview}`)
        console.log(`[WA] 📦 Buffer : ${incomingMessages.length} message(s)`)
    })

    // ── Révocation de message côté apprenant ────────────────────────────────
    client.on('message_revoke_everyone', (after, _before) => {
        const waId = String(after?.id?._serialized ?? '')
        if (!waId) return

        const from = String(after?.from ?? '')
        let rawPhone = ''
        if (from.endsWith('@c.us')) {
            rawPhone = from.replace('@c.us', '')
        } else if (from.endsWith('@lid')) {
            const lidUser = from.replace('@lid', '')
            if (lidToPhone.has(lidUser)) rawPhone = lidToPhone.get(lidUser)
        }

        revokedBuffer.push({ wa_id: waId, from: rawPhone, revoked_at: new Date().toISOString() })
        if (revokedBuffer.length > 500) revokedBuffer.shift()
        console.log(`[WA] 🗑️  Révocation reçue : ${waId} de +${rawPhone}`)
    })

    client.on('auth_failure', (msg) => {
        console.error('[WA] ❌ Échec authentification :', msg)
        resetState()
        clearAuth()
        scheduleRestart(5_000)
    })

    client.on('disconnected', (reason) => {
        console.warn('[WA] 🔌 Déconnecté :', reason)
        resetState()
        scheduleRestart(5_000)
    })

    client.initialize().catch(async (err) => {
        console.error('[WA] 💥 Erreur démarrage :', err.message)
        resetState()
        await safeDestroy()   // fermer Chrome proprement
        if (String(err.message).includes('already running')) {
            killOrphanedBrowsers()
            await sleep(1000)
            releaseBrowserLock()
        }
        scheduleRestart(5_000)
    })
}

// ── Fonctions utilitaires ────────────────────────────────────────────────────

async function safeDestroy() {
    if (!client) return
    try { await client.destroy() } catch {}
    client = null
}

function scheduleRestart(ms) {
    if (restartTimer) clearTimeout(restartTimer)
    console.log(`[WA] ♻️  Redémarrage dans ${Math.round(ms / 1000)}s...`)
    restartTimer = setTimeout(() => { restartTimer = null; initClient() }, ms)
}

function clearAuth() {
    try {
        if (fs.existsSync(AUTH_PATH)) {
            fs.rmSync(AUTH_PATH, { recursive: true, force: true })
            console.log('[WA] 🗑️  Cache de session effacé.')
        }
    } catch (e) {
        console.warn('[WA] Impossible d\'effacer la session :', e.message)
    }
}

function isBrowserDead(msg) {
    return /detached Frame|Execution context was destroyed|Session closed|Protocol error|Target closed|Cannot read propert/i
        .test(String(msg))
}

async function recoverClient() {
    if (recovering) return
    recovering = true
    console.warn('[WA] 🔄 Récupération automatique de la connexion...')
    resetState()
    await safeDestroy()
    recovering = false
    scheduleRestart(3_000)
}

async function isReallyConnected() {
    if (!isConnected || !client) return false
    try {
        const state = await client.getState()
        return state === 'CONNECTED'
    } catch (err) {
        if (isBrowserDead(String(err.message))) recoverClient()
        return false
    }
}

function normalizePhone(phone) {
    let n = String(phone).replace(/\D/g, '')
    if (n.startsWith('00')) n = n.slice(2)                        // 00226... → 226...
    if (n.startsWith('0') && n.length <= 9) n = '226' + n.slice(1) // 0XXXXXXXX → 226XXXXXXXX
    if (!n.startsWith('226') && n.length <= 8) n = '226' + n       // XXXXXXXX  → 226XXXXXXXX
    return n
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)) }

// ── Routes Express ───────────────────────────────────────────────────────────

app.get('/status', (_req, res) => {
    // Numéro WhatsApp du compte connecté (disponible après l'événement ready)
    let phone = null
    if (isConnected && client?.info?.wid) {
        phone = String(client.info.wid.user || '').replace(/\D/g, '') || null
    }

    res.json({
        connected:      isConnected,
        authenticating: isAuthenticating,
        qr:             qrDataUrl,
        phone,          // ex: "22670123456" — le numéro qui a scanné le QR
    })
})

app.post('/send-bulk', async (req, res) => {
    const { recipients, message, attachment } = req.body

    if (!Array.isArray(recipients) || !recipients.length)
        return res.status(400).json({ error: 'Paramètre recipients manquant' })
    if (!message && !attachment)
        return res.status(400).json({ error: 'Message ou pièce jointe requis' })

    if (!(await isReallyConnected())) {
        return res.json({
            success: 0,
            failed:  recipients.length,
            errors:  recipients.map(r => ({ phone: r.phone, error: 'WhatsApp non connecté' })),
        })
    }

    let media = null
    if (attachment?.data && attachment?.mimetype) {
        media = new MessageMedia(attachment.mimetype, attachment.data, attachment.filename || 'fichier')
    }

    const results = { success: 0, failed: 0, errors: [], sentIds: {} }
    let aborted = false

    for (let i = 0; i < recipients.length; i++) {
        const r = recipients[i]

        if (aborted) {
            results.failed++
            results.errors.push({ phone: r.phone, error: 'Envoi interrompu — reconnexion en cours' })
            continue
        }

        const normalized = normalizePhone(r.phone)

        try {
            // Vérifier que le numéro existe sur WhatsApp avant d'envoyer
            const numberId = await client.getNumberId(normalized)

            if (!numberId) {
                results.failed++
                results.errors.push({ phone: r.phone, error: 'Numéro non enregistré sur WhatsApp' })
                console.log(`[WA] ✗ ${r.phone} (${normalized}) — pas de compte WhatsApp`)
            } else {
                // Stocker le mapping LID → téléphone AVANT d'envoyer.
                // Si ce destinataire est en multi-device, les réponses arriveront
                // en @lid et on ne pourra les matcher qu'avec cette table.
                registerLid(numberId, normalized)

                const chatId = numberId._serialized
                const sentMsg = media
                    ? await client.sendMessage(chatId, media, { caption: message || '' })
                    : await client.sendMessage(chatId, message)
                results.success++
                // Stocker wa_id sous le numéro normalisé ET sans préfixe pays 226
                const waId = sentMsg?.id?._serialized
                if (waId) {
                    results.sentIds[normalized] = waId
                    if (normalized.startsWith('226')) results.sentIds[normalized.slice(3)] = waId
                }
                console.log(`[WA] ✓ Envoyé à ${r.phone} (chatId: ${chatId})`)
            }

        } catch (err) {
            results.failed++
            if (isBrowserDead(String(err.message))) {
                aborted = true
                results.errors.push({ phone: r.phone, error: 'Connexion perdue pendant l\'envoi' })
                console.error(`[WA] 💥 Connexion morte sur ${r.phone} — récupération`)
                recoverClient()
            } else {
                results.errors.push({ phone: r.phone, error: err.message })
                console.error(`[WA] ✗ ${r.phone} — ${err.message}`)
            }
        }

        // 1.2s entre envois (WhatsApp anti-spam)
        if (!aborted && i < recipients.length - 1) await sleep(1200)
    }

    res.json(results)
})

app.get('/messages/incoming', (req, res) => {
    const since = req.query.since ? parseInt(req.query.since, 10) : 0
    const msgs  = since > 0
        ? incomingMessages.filter(m => m.timestamp > since)
        : incomingMessages
    res.json(msgs)
})

app.get('/messages/revoked', (req, res) => {
    const since = req.query.since ? String(req.query.since) : ''
    const msgs = since
        ? revokedBuffer.filter(m => m.revoked_at > since)
        : revokedBuffer
    res.json(msgs)
})

app.post('/revoke-message', async (req, res) => {
    const { wa_id } = req.body
    if (!wa_id) return res.status(400).json({ error: 'wa_id manquant' })
    if (!(await isReallyConnected())) {
        return res.status(503).json({ error: 'WhatsApp non connecté' })
    }
    try {
        const msg = await client.getMessageById(wa_id)
        if (!msg) return res.status(404).json({ error: 'Message introuvable' })
        await msg.delete(true)
        console.log(`[WA] 🗑️  Révocation envoyée : ${wa_id}`)
        res.json({ success: true })
    } catch (e) {
        console.error(`[WA] ✗ Échec révocation : ${e.message}`)
        res.status(500).json({ error: e.message })
    }
})

// Endpoint de diagnostic — permet à Laravel de voir l'état exact du buffer
app.get('/debug', (_req, res) => {
    res.json({
        connected:      isConnected,
        authenticating: isAuthenticating,
        bufferSize:     incomingMessages.length,
        lastMessages:   incomingMessages.slice(-10).map(m => ({
            from:        m.from,
            phone_short: m.phone_short,
            body:        String(m.body).slice(0, 60),
            timestamp:   m.timestamp,
            received_at: m.received_at,
        })),
    })
})

app.post('/logout', async (_req, res) => {
    try { if (client) await client.logout() } catch {}
    clearAuth()
    resetState()
    qrDataUrl = null
    scheduleRestart(3_000)
    res.json({ success: true })
})

// ── Résilience du processus Node ─────────────────────────────────────────────

process.on('uncaughtException', (err) => {
    console.error('[WA] EXCEPTION NON GÉRÉE :', err.message)
    if (isBrowserDead(String(err.message))) recoverClient()
})

process.on('unhandledRejection', (reason) => {
    const msg = reason instanceof Error ? reason.message : String(reason)
    console.error('[WA] PROMESSE REJETÉE :', msg)
    if (isBrowserDead(msg)) recoverClient()
})

// ── Arrêt propre : fermer Chrome AVANT que Node quitte ───────────────────────
// Sans ça, Chrome reste en vie après chaque redémarrage et accumule des verrous.
async function gracefulShutdown(signal) {
    console.log(`[WA] Arrêt (${signal}) — fermeture du navigateur...`)
    try { if (client) await client.destroy() } catch {}
    process.exit(0)
}
process.on('SIGINT',  () => gracefulShutdown('SIGINT'))
process.on('SIGTERM', () => gracefulShutdown('SIGTERM'))

// ── Démarrage ────────────────────────────────────────────────────────────────
app.listen(PORT, '127.0.0.1', () => {
    console.log(`[WA] 🚀 Service démarré sur http://127.0.0.1:${PORT}`)
    console.log(`[WA] 📁 Session stockée dans : ${AUTH_PATH}`)
    loadLidMap()
    initClient()
})
